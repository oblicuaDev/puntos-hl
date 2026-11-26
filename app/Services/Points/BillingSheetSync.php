<?php

namespace App\Services\Points;

use App\Mail\BillingSyncReportMail;
use App\Mail\PointsEarnedMail;
use App\Models\BillingRecord;
use App\Models\BillingSyncRun;
use App\Models\Patient;
use App\Services\BillingSheet\BillingSheet;
use App\Services\BillingSheet\BillingSheetException;
use App\Support\PersonName;
use App\Support\Phone;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

/**
 * Carga diaria de facturación desde la hoja de Google Sheets (HL-28).
 *
 * - Solo revisa filas sin X en REGISTRADO (M) y con FECHA FACTURA (N)
 *   posterior a la fecha de corte de los saldos iniciales.
 * - Puntos = PointRates::pointsEarnedFor(VALOR FINAL FACTURADO); la columna
 *   PUNTOS ASIGNAR solo se compara y se informa si no coincide.
 * - Paciente existente: solo se le acreditan los puntos. Paciente nuevo: se
 *   crea si vienen todas las columnas menos OBSERVACION; entra al portal con
 *   documento + SMS al CELULAR CON INDICATIVO (HL-25).
 * - Fila acreditada: X en REGISTRADO y el resultado en NOVEDAD SISTEMA (O).
 *   Fila con problemas: queda sin X, con el motivo en NOVEDAD SISTEMA, y se
 *   vuelve a revisar en la siguiente corrida.
 * - No duplica: cada fila acreditada queda en billing_records con una huella
 *   única; si la X no se alcanzó a escribir, la siguiente corrida solo la marca.
 */
class BillingSheetSync
{
    public const HEADERS = [
        'CEDULA / NIT', 'NOMBRE', 'DEBITOS', 'CREDITOS', 'VALOR FINAL FACTURADO', 'PUNTOS ASIGNAR',
        'CELULAR', 'CORREO', 'INDICATIVO', 'PAIS', 'CELULAR CON INDICATIVO', 'OBSERVACION',
        'REGISTRADO', 'FECHA FACTURA', 'NOVEDAD SISTEMA',
    ];

    private const DOCUMENT = 0;

    private const NAME = 1;

    private const AMOUNT = 4;

    private const SHEET_POINTS = 5;

    private const EMAIL = 7;

    private const FULL_PHONE = 10;

    private const NOTES = 11;

    private const REGISTERED = 12;

    private const INVOICE_DATE = 13;

    private const SYSTEM_NOTE = 14;

    /** Columnas que necesita un paciente nuevo: todas menos OBSERVACION, REGISTRADO y NOVEDAD SISTEMA. */
    private const REQUIRED_FOR_NEW_PATIENT = [0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 13];

    /** @var array<string, true> documentos de pacientes nuevos vistos en esta corrida (simulación) */
    private array $plannedDocuments = [];

    /** @var array<string, true> correos de pacientes nuevos vistos en esta corrida (simulación) */
    private array $plannedEmails = [];

    public function __construct(private BillingSheet $sheet, private PointAccrualService $accrual) {}

    public static function cutoffDate(): CarbonImmutable
    {
        $value = (string) config('services.billing_sheet.cutoff_date');
        $date = preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? self::calendarDate($value) : null;

        if (! $date) {
            throw new BillingSheetException('BILLING_SHEET_CUTOFF_DATE no está configurada (YYYY-MM-DD): sin fecha de corte no se procesa nada, para no sumar dos veces la facturación incluida en los saldos iniciales.');
        }

        return $date;
    }

    /**
     * Revisa la hoja sin escribir nada (ni en la base ni en la hoja).
     *
     * @return list<array{row: int, document: string, name: string, amount: ?int, points: int, action: string, message: string}>
     */
    public function preview(): array
    {
        $cutoff = self::cutoffDate();
        $results = [];

        foreach ($this->pendingRows() as $rowNumber => $cells) {
            $evaluation = $this->evaluate($rowNumber, $cells, $cutoff);

            if ($evaluation['action'] === 'crear') {
                $this->plannedDocuments[$evaluation['document']] = true;
                $this->plannedEmails[$evaluation['email']] = true;
            }

            $results[] = $this->summary($evaluation);
        }

        return $results;
    }

    /** Procesa la hoja: acredita, crea pacientes, marca la hoja y notifica. */
    public function sync(): BillingSyncRun
    {
        $cutoff = self::cutoffDate();
        $rate = PointRates::accrualCopPerPoint();

        $run = BillingSyncRun::create([
            'spreadsheet_id' => $this->sheet->id(),
            'cutoff_date' => $cutoff,
            'accrual_cop_per_point' => $rate,
            'started_at' => now(),
        ]);

        $cells = [];
        $pending = [];
        $counts = ['read' => 0, 'processed' => 0, 'created' => 0, 'points' => 0];

        try {
            foreach ($this->pendingRows() as $rowNumber => $rowCells) {
                $counts['read']++;
                $evaluation = $this->evaluate($rowNumber, $rowCells, $cutoff);

                try {
                    $note = match ($evaluation['action']) {
                        'marcar' => $evaluation['message'],
                        'crear', 'acreditar' => $this->accrue($run, $evaluation, $rate),
                        default => null,
                    };
                } catch (Throwable $e) {
                    Log::error('Carga de facturación: error procesando una fila', ['row' => $rowNumber, 'exception' => $e]);
                    $evaluation['action'] = 'pendiente';
                    $evaluation['message'] = 'Error inesperado al registrarla; se reintenta en la siguiente corrida.';
                    $note = null;
                }

                if ($note !== null) {
                    $cells['M'.$rowNumber] = 'X';
                    $cells['O'.$rowNumber] = $note;

                    if ($evaluation['action'] !== 'marcar') {
                        $counts['processed']++;
                        $counts['points'] += $evaluation['points'];
                        $counts['created'] += $evaluation['action'] === 'crear' ? 1 : 0;
                    }
                } else {
                    $cells['O'.$rowNumber] = 'Pendiente: '.$evaluation['message'];
                    $pending[] = $this->summary($evaluation);
                }
            }

            $this->sheet->write($cells);
        } catch (BillingSheetException $e) {
            $run->error = $e->getMessage();
        }

        $run->fill([
            'finished_at' => now(),
            'rows_read' => $counts['read'],
            'rows_processed' => $counts['processed'],
            'rows_pending' => count($pending),
            'patients_created' => $counts['created'],
            'points_total' => $counts['points'],
            'pending_rows' => $pending,
        ])->save();

        if ($run->error || $run->rows_processed > 0 || $run->rows_pending > 0) {
            Mail::to(setting('management_notification_email', 'gerencia@harkerlloreda.com'))
                ->queue(new BillingSyncReportMail($run));
        }

        return $run;
    }

    /**
     * Filas sin X en REGISTRADO y con algún dato, indexadas por número de fila.
     *
     * @return array<int, array<int, mixed>>
     */
    private function pendingRows(): array
    {
        $rows = $this->sheet->rows();
        $header = array_map(fn ($h) => mb_strtoupper(trim((string) $h)), $rows[1] ?? []);

        if (array_slice($header, 0, count(self::HEADERS)) !== self::HEADERS) {
            throw new BillingSheetException('Los encabezados de la hoja no coinciden. Se esperaban, de la A a la O: '.implode(', ', self::HEADERS).'.');
        }

        unset($rows[1]);

        return array_filter(
            array_map(fn ($cells) => array_pad(array_map(fn ($v) => $this->text($v), $cells), count(self::HEADERS), ''), $rows),
            function (array $cells) {
                $data = array_diff_key($cells, array_flip([self::NOTES, self::REGISTERED, self::SYSTEM_NOTE]));

                return $cells[self::REGISTERED] === '' && implode('', $data) !== '';
            }
        );
    }

    /**
     * Valida una fila y decide qué hacer con ella, sin escribir nada.
     *
     * @param  array<int, string>  $cells
     * @return array{row: int, document: string, name: string, email: string, phone: ?string, amount: ?int, points: int, sheet_points: string, invoice_date: ?CarbonImmutable, fingerprint: ?string, patient: ?Patient, action: string, message: string}
     */
    private function evaluate(int $rowNumber, array $cells, CarbonImmutable $cutoff): array
    {
        $errors = [];
        $document = preg_replace('/\D/', '', $cells[self::DOCUMENT]);
        $name = trim(preg_replace('/\s+/', ' ', $cells[self::NAME]));
        $email = mb_strtolower($cells[self::EMAIL]);
        $amount = $this->money($cells[self::AMOUNT]);
        $points = $amount !== null ? PointRates::pointsEarnedFor($amount) : 0;
        $invoiceDate = $this->date($cells[self::INVOICE_DATE]);

        $evaluation = [
            'row' => $rowNumber,
            'document' => $document,
            'name' => $name,
            'email' => $email,
            'phone' => Phone::normalize($cells[self::FULL_PHONE]),
            'amount' => $amount,
            'points' => $points,
            'sheet_points' => $cells[self::SHEET_POINTS],
            'invoice_date' => $invoiceDate,
            'fingerprint' => null,
            'patient' => null,
            'action' => 'pendiente',
            'message' => '',
        ];

        if ($document === '') {
            $errors[] = 'falta CEDULA / NIT';
        }

        if ($amount === null) {
            $errors[] = 'falta VALOR FINAL FACTURADO';
        } elseif ($amount <= 0) {
            $errors[] = 'VALOR FINAL FACTURADO debe ser mayor a 0';
        } elseif ($points < 1) {
            $errors[] = 'el VALOR FINAL FACTURADO no alcanza para 1 punto';
        }

        if ($cells[self::INVOICE_DATE] === '') {
            $errors[] = 'falta FECHA FACTURA';
        } elseif (! $invoiceDate) {
            $errors[] = 'FECHA FACTURA no es una fecha válida (usa dd/mm/aaaa)';
        } elseif ($invoiceDate->lte($cutoff)) {
            $errors[] = 'la factura es del '.$invoiceDate->format('d/m/Y').', igual o anterior a la fecha de corte ('.$cutoff->format('d/m/Y').'): ya está incluida en el saldo inicial y no se suma';
        } elseif ($invoiceDate->gt(now('America/Bogota')->startOfDay())) {
            $errors[] = 'FECHA FACTURA está en el futuro';
        }

        if ($errors) {
            return ['action' => 'pendiente', 'message' => $this->sentence($errors)] + $evaluation;
        }

        $evaluation['fingerprint'] = hash('sha256', implode('|', [$this->sheet->id(), $rowNumber, $document, $amount, $invoiceDate->format('Y-m-d')]));

        $record = BillingRecord::where('fingerprint', $evaluation['fingerprint'])->first();

        if ($record) {
            return ['action' => 'marcar', 'message' => $this->registeredNote($record)] + $evaluation;
        }

        $patient = Patient::withTrashed()->where('document_number', $document)->first();

        if ($patient?->trashed()) {
            return ['action' => 'pendiente', 'message' => 'El paciente con este documento fue eliminado de la plataforma.'] + $evaluation;
        }

        if ($patient || isset($this->plannedDocuments[$document])) {
            return ['action' => 'acreditar', 'patient' => $patient] + $evaluation;
        }

        $missing = array_values(array_filter(self::REQUIRED_FOR_NEW_PATIENT, fn ($column) => $cells[$column] === ''));

        if ($missing) {
            $errors[] = 'el paciente no está registrado y para crearlo faltan: '.implode(', ', array_map(fn ($column) => self::HEADERS[$column], $missing));
        }

        if ($email !== '' && ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'CORREO no es válido';
        } elseif ($email !== '' && (isset($this->plannedEmails[$email]) || Patient::withTrashed()->where('email', $email)->exists())) {
            $errors[] = 'el CORREO ya pertenece a otro paciente de la plataforma';
        }

        if ($cells[self::FULL_PHONE] !== '' && ! $evaluation['phone']) {
            $errors[] = 'CELULAR CON INDICATIVO no es válido';
        }

        if ($errors) {
            return ['action' => 'pendiente', 'message' => $this->sentence($errors)] + $evaluation;
        }

        return ['action' => 'crear'] + $evaluation;
    }

    /**
     * Crea el paciente si hace falta, registra la fila y acredita los puntos.
     *
     * @return string nota para NOVEDAD SISTEMA
     */
    private function accrue(BillingSyncRun $run, array $evaluation, float $rate): string
    {
        $record = DB::transaction(function () use ($run, $evaluation, $rate) {
            $patient = $evaluation['patient'] ?? Patient::where('document_number', $evaluation['document'])->first();
            $created = false;

            if (! $patient) {
                [$first, $last] = PersonName::split($evaluation['name']);

                $patient = Patient::create([
                    'document_type' => 'CC',
                    'document_number' => $evaluation['document'],
                    'first_name' => $first,
                    'last_name' => $last,
                    'email' => $evaluation['email'],
                    'phone' => $evaluation['phone'],
                    // Sin contraseña: el ingreso al portal es por código SMS (HL-25).
                    'password' => Str::random(64),
                    'must_change_password' => false,
                    'registration_source' => 'manual_admin',
                    'status' => 'active',
                ]);
                $created = true;
            }

            $record = BillingRecord::create([
                'billing_sync_run_id' => $run->id,
                'patient_id' => $patient->id,
                'spreadsheet_id' => $run->spreadsheet_id,
                'sheet_row' => $evaluation['row'],
                'fingerprint' => $evaluation['fingerprint'],
                'document_number' => $evaluation['document'],
                'patient_name' => $evaluation['name'],
                'invoice_date' => $evaluation['invoice_date'],
                'amount_cop' => $evaluation['amount'],
                'sheet_points' => $evaluation['sheet_points'] !== '' ? $evaluation['sheet_points'] : null,
                'points' => $evaluation['points'],
                'accrual_cop_per_point' => $rate,
                'patient_created' => $created,
            ]);

            $this->accrual->accrue(
                $patient->id,
                $evaluation['points'],
                $record,
                'Puntos por facturación del '.$evaluation['invoice_date']->format('d/m/Y').' ($'.number_format($evaluation['amount'], 0, ',', '.').')',
                now(),
            );

            return $record;
        });

        $patient = $record->patient;

        if ($patient->email) {
            Mail::to($patient->email)->queue(new PointsEarnedMail($patient, $record->points, $patient->pointsBalance()));
        }

        $note = $this->registeredNote($record);
        $sheetPoints = $this->money($evaluation['sheet_points']);

        if ($sheetPoints !== null && $sheetPoints !== $record->points) {
            $note .= ' PUNTOS ASIGNAR decía '.number_format($sheetPoints, 0, ',', '.').'; se asignaron los calculados con la tasa vigente.';
        }

        return $note;
    }

    private function registeredNote(BillingRecord $record): string
    {
        return 'Registrado el '.$record->created_at->timezone('America/Bogota')->format('d/m/Y H:i').': '
            .number_format($record->points, 0, ',', '.').' puntos'
            .($record->patient_created ? ' (paciente nuevo)' : '')
            .' — registro #'.$record->id.'.';
    }

    /** @return array{row: int, document: string, name: string, amount: ?int, points: int, action: string, message: string} */
    private function summary(array $evaluation): array
    {
        return [
            'row' => $evaluation['row'],
            'document' => $evaluation['document'],
            'name' => $evaluation['name'],
            'amount' => $evaluation['amount'],
            'points' => $evaluation['points'],
            'action' => $evaluation['action'],
            'message' => $evaluation['message'],
        ];
    }

    /** @param  list<string>  $errors */
    private function sentence(array $errors): string
    {
        return Str::ucfirst(implode('; ', $errors)).'.';
    }

    /** Convierte una celda a texto; los números enteros grandes (cédulas) sin notación científica. */
    private function text(mixed $value): string
    {
        if (is_float($value) && floor($value) === $value) {
            return sprintf('%.0f', $value);
        }

        return trim((string) $value);
    }

    /** "$ 1.234.567", "1234567,50" o 1234567.5 → 1234567 (pesos completos). */
    private function money(string $value): ?int
    {
        $value = trim($value);

        if ($value === '') {
            return null;
        }

        if (preg_match('/^-?\d{1,3}(\.\d{3})+$/', $value)) {
            return (int) str_replace('.', '', $value);
        }

        if (is_numeric($value)) {
            return (int) floor((float) $value);
        }

        $negative = str_contains($value, '-');
        $digits = preg_replace('/\D/', '', preg_replace('/[.,]\d{1,2}$/', '', $value));

        if ($digits === '') {
            return null;
        }

        return $negative ? -(int) $digits : (int) $digits;
    }

    /** Número de serie de Sheets (días desde 1899-12-30) o texto dd/mm/aaaa / aaaa-mm-dd. */
    private function date(string $value): ?CarbonImmutable
    {
        if ($value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return CarbonImmutable::create(1899, 12, 30)->addDays((int) floor((float) $value));
        }

        return self::calendarDate($value);
    }

    /** "26/11/2026", "26-11-2026" o "2026-11-26" → fecha; null si no existe en el calendario. */
    private static function calendarDate(string $value): ?CarbonImmutable
    {
        if (preg_match('/^(\d{1,2})[\/-](\d{1,2})[\/-](\d{4})$/', $value, $m)) {
            [, $day, $month, $year] = $m;
        } elseif (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})$/', $value, $m)) {
            [, $year, $month, $day] = $m;
        } else {
            return null;
        }

        return checkdate((int) $month, (int) $day, (int) $year)
            ? CarbonImmutable::create((int) $year, (int) $month, (int) $day)
            : null;
    }
}
