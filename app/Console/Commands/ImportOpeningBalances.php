<?php

namespace App\Console\Commands;

use App\Models\Patient;
use App\Models\PointBatch;
use App\Models\PointImport;
use App\Models\PointMovement;
use App\Services\Points\PointRates;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Carga masiva de saldos iniciales de puntos desde la base "PUNTOS POR
 * PACIENTE" (CSV con columnas: CEDULA / NIT, NOMBRE, VALOR FINAL FACTURADO,
 * PUNTOS ASIGNAR, CELULAR CON INDICATIVO, CORREO).
 *
 * - Por defecto solo VALIDA y genera el reporte; con --commit importa.
 * - Los puntos se recalculan con la tasa de ACUMULACIÓN vigente sobre el
 *   valor facturado (la columna PUNTOS ASIGNAR de la base se ignora: venía
 *   a 1 pt/$100, decisión de H&L 2026-11-25).
 * - Cada saldo se registra como lote (point_batches) + movimiento 'earned',
 *   ambos ligados a un registro point_imports (archivo, hash, tasa, fechas).
 * - Idempotente: un paciente que ya recibió saldo inicial no se vuelve a cargar.
 * - Pacientes nuevos: sin contraseña; ingresan con documento + código SMS
 *   al celular importado (HL-25).
 * - Un paciente que se registró solo en el portal con OTRO celular no recibe
 *   el saldo (se rechaza): podría ser alguien suplantando esa cédula.
 */
class ImportOpeningBalances extends Command
{
    protected $signature = 'patients:import-opening-balances
        {file : Ruta al CSV}
        {--commit : Ejecuta la importación (sin esto solo valida)}
        {--earned-at= : Fecha de causación de los puntos (YYYY-MM-DD, por defecto hoy)}';

    protected $description = 'Valida e importa los saldos iniciales de puntos de los pacientes desde un CSV.';

    private const HEADERS = ['CEDULA / NIT', 'NOMBRE', 'VALOR FINAL FACTURADO', 'PUNTOS ASIGNAR', 'CELULAR CON INDICATIVO', 'CORREO'];

    public function handle(): int
    {
        $file = $this->argument('file');

        if (! is_readable($file)) {
            $this->error("No se puede leer el archivo: {$file}");

            return self::FAILURE;
        }

        $earnedAt = $this->option('earned-at')
            ? Carbon::createFromFormat('Y-m-d', $this->option('earned-at'))->startOfDay()
            : now();
        $expiresAt = $earnedAt->copy()->addMonths((int) setting('points_expiration_months', 12));
        $rate = PointRates::accrualCopPerPoint();

        $rows = $this->readRows($file);

        if ($rows === null) {
            return self::FAILURE;
        }

        $analysis = $this->analyse($rows, $rate);
        $reportPath = $this->writeReport($analysis);

        $ok = collect($analysis)->where('status', '!=', 'rechazado');
        $this->table(['Resultado', 'Registros'], [
            ['Total en el archivo', count($analysis)],
            ['Listos para importar (paciente nuevo)', $ok->where('action', 'crear')->count()],
            ['Listos para importar (paciente existente)', $ok->where('action', 'actualizar')->count()],
            ['Ya importados antes (se omiten)', collect($analysis)->where('action', 'omitir')->count()],
            ['Rechazados', collect($analysis)->where('status', 'rechazado')->count()],
            ['Con advertencias (se importan)', $ok->where('status', 'advertencia')->count()],
            ['Puntos a cargar', number_format($ok->whereIn('action', ['crear', 'actualizar'])->sum('points'), 0, ',', '.')],
        ]);
        $this->line('Tasa de acumulación: 1 punto por cada $'.number_format($rate, 0, ',', '.').' COP facturados.');
        $this->line('Causación: '.$earnedAt->format('Y-m-d').' — vencimiento: '.$expiresAt->format('Y-m-d'));
        $this->line("Reporte detallado: {$reportPath}");

        if (! $this->option('commit')) {
            $this->warn('Modo validación: no se importó nada. Revisa el reporte y vuelve a correr con --commit.');

            return self::SUCCESS;
        }

        $import = $this->import($analysis, $file, $rate, $earnedAt, $expiresAt);

        $this->info("Importación #{$import->id}: {$import->rows_imported} saldos cargados ({$import->patients_created} pacientes nuevos), "
            .number_format($import->points_total, 0, ',', '.').' puntos.');

        return self::SUCCESS;
    }

    private function readRows(string $file): ?array
    {
        $handle = fopen($file, 'r');
        $header = fgetcsv($handle);
        $header = array_map(fn ($h) => trim(preg_replace('/^\xEF\xBB\xBF/', '', (string) $h)), $header ?: []);

        if ($header !== self::HEADERS) {
            $this->error('Encabezados inesperados: '.implode(', ', $header).'. Se esperaban: '.implode(', ', self::HEADERS));

            return null;
        }

        $rows = [];
        $line = 1;

        while (($data = fgetcsv($handle)) !== false) {
            $line++;

            if (count(array_filter($data, fn ($v) => trim((string) $v) !== '')) === 0) {
                continue;
            }

            $rows[] = ['line' => $line] + array_combine(
                ['document', 'name', 'amount', 'sheet_points', 'phone', 'email'],
                array_map(fn ($v) => trim((string) $v), array_pad(array_slice($data, 0, 6), 6, ''))
            );
        }

        fclose($handle);

        return $rows;
    }

    /** Valida cada fila sin escribir nada. */
    private function analyse(array $rows, float $rate): array
    {
        $documentCounts = array_count_values(array_map(fn ($r) => preg_replace('/\D/', '', $r['document']), $rows));
        $emailCounts = array_count_values(array_filter(array_map(fn ($r) => mb_strtolower($r['email']), $rows)));

        $results = [];

        foreach ($rows as $row) {
            $errors = [];
            $warnings = [];

            $document = preg_replace('/\D/', '', $row['document']);
            $phoneDigits = preg_replace('/\D/', '', $row['phone']);
            $amount = (int) preg_replace('/\D/', '', $row['amount']);
            $email = mb_strtolower($row['email']);
            $name = trim(preg_replace('/\s+/', ' ', $row['name']));

            if ($document === '') {
                $errors[] = 'Sin documento';
            } elseif ($documentCounts[$document] > 1) {
                $errors[] = 'Documento duplicado en el archivo';
            } elseif (strlen($document) < 5) {
                $warnings[] = 'Documento muy corto, verificar';
            }

            if ($row['document'] !== '' && $row['document'] !== $document) {
                $warnings[] = "Documento normalizado de «{$row['document']}» a «{$document}»";
            }

            if ($phoneDigits === '') {
                $errors[] = 'Sin teléfono';
            } elseif (strlen($phoneDigits) < 10 || strlen($phoneDigits) > 15) {
                $errors[] = 'Teléfono con longitud inválida';
            }

            if ($name === '') {
                $errors[] = 'Sin nombre';
            }

            $points = PointRates::pointsEarnedFor($amount, $rate);

            if ($amount <= 0) {
                $errors[] = 'Sin valor facturado';
            } elseif ($points <= 0) {
                $errors[] = 'El valor facturado no alcanza para 1 punto';
            }

            if ($email !== '') {
                if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $warnings[] = 'Correo inválido, no se guarda';
                    $email = '';
                } elseif ($emailCounts[$email] > 1) {
                    $warnings[] = 'Correo compartido con otro paciente del archivo, no se guarda';
                    $email = '';
                }
            }

            $action = 'crear';
            $patient = $document !== '' ? Patient::withTrashed()->where('document_number', $document)->first() : null;

            if ($patient) {
                if ($patient->trashed()) {
                    $errors[] = 'Existe un paciente eliminado con este documento';
                } elseif (PointBatch::where('patient_id', $patient->id)->where('sourceable_type', PointImport::class)->exists()) {
                    $action = 'omitir';
                    $warnings[] = 'Ya recibió saldo inicial en una importación anterior';
                } else {
                    $action = 'actualizar';
                    $existingPhone = preg_replace('/\D/', '', (string) $patient->phone);
                    if ($existingPhone !== '' && $phoneDigits !== '' && $existingPhone !== $phoneDigits) {
                        if ($patient->registration_source === 'self_registered') {
                            $errors[] = 'Ya existe una cuenta auto-registrada con este documento y otro celular; verificar identidad antes de asignar el saldo';
                        } else {
                            $warnings[] = 'El paciente ya existe con otro teléfono; se conserva el actual';
                        }
                    }
                }
            }

            if ($email !== '' && Patient::withTrashed()->where('email', $email)
                ->when($patient, fn ($q) => $q->whereKeyNot($patient->id))->exists()) {
                $warnings[] = 'Correo ya usado por otro paciente en la plataforma, no se guarda';
                $email = '';
            }

            $results[] = [
                'line' => $row['line'],
                'document' => $document,
                'name' => $name,
                'phone' => $phoneDigits !== '' ? '+'.$phoneDigits : '',
                'email' => $email,
                'amount' => $amount,
                'sheet_points' => $row['sheet_points'],
                'points' => $points,
                'patient_id' => $patient?->id,
                'action' => $errors ? 'ninguna' : $action,
                'status' => $errors ? 'rechazado' : ($warnings ? 'advertencia' : 'ok'),
                'messages' => implode('; ', [...$errors, ...$warnings]),
            ];
        }

        return $results;
    }

    private function writeReport(array $analysis): string
    {
        $path = 'imports/saldos-iniciales-'.now()->format('Ymd-His').'.csv';
        $out = fopen('php://temp', 'r+');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, ['Fila', 'Documento', 'Nombre', 'Teléfono', 'Correo', 'Valor facturado', 'Puntos base original', 'Puntos a cargar', 'Acción', 'Estado', 'Observaciones']);

        foreach ($analysis as $r) {
            fputcsv($out, [$r['line'], $r['document'], $r['name'], $r['phone'], $r['email'], $r['amount'], $r['sheet_points'], $r['points'], $r['action'], $r['status'], $r['messages']]);
        }

        rewind($out);
        Storage::disk('local')->put($path, stream_get_contents($out));
        fclose($out);

        return Storage::disk('local')->path($path);
    }

    private function import(array $analysis, string $file, float $rate, Carbon $earnedAt, Carbon $expiresAt): PointImport
    {
        return DB::transaction(function () use ($analysis, $file, $rate, $earnedAt, $expiresAt) {
            $import = PointImport::create([
                'source_filename' => basename($file),
                'source_sha256' => hash_file('sha256', $file),
                'accrual_cop_per_point' => $rate,
                'earned_at' => $earnedAt,
                'expires_at' => $expiresAt,
                'rows_total' => count($analysis),
                'rows_rejected' => collect($analysis)->where('status', 'rechazado')->count(),
            ]);

            $imported = $created = $points = 0;

            foreach ($analysis as $row) {
                if (! in_array($row['action'], ['crear', 'actualizar'], true)) {
                    continue;
                }

                if ($row['action'] === 'crear') {
                    [$first, $last] = $this->splitName($row['name']);

                    $patient = Patient::create([
                        'document_type' => 'CC',
                        'document_number' => $row['document'],
                        'first_name' => $first,
                        'last_name' => $last,
                        'email' => $row['email'] ?: null,
                        'phone' => $row['phone'],
                        // Sin contraseña: el ingreso al portal es por código SMS (HL-25).
                        'password' => Str::random(64),
                        'must_change_password' => false,
                        'registration_source' => 'manual_admin',
                        'status' => 'active',
                    ]);
                    $created++;
                } else {
                    $patient = Patient::findOrFail($row['patient_id']);
                    $patient->fill(array_filter([
                        'phone' => $patient->phone ? null : $row['phone'],
                        'email' => $patient->email ? null : ($row['email'] ?: null),
                    ]))->save();
                }

                PointBatch::create([
                    'patient_id' => $patient->id,
                    'points_original' => $row['points'],
                    'points_remaining' => $row['points'],
                    'earned_at' => $earnedAt,
                    'expires_at' => $expiresAt,
                    'status' => 'active',
                    'sourceable_type' => PointImport::class,
                    'sourceable_id' => $import->id,
                ]);

                PointMovement::create([
                    'patient_id' => $patient->id,
                    'type' => 'earned',
                    'points' => $row['points'],
                    'related_type' => PointImport::class,
                    'related_id' => $import->id,
                    'description' => 'Saldo inicial del plan de puntos (compras por $'.number_format($row['amount'], 0, ',', '.').')',
                    'occurred_at' => $earnedAt,
                ]);

                $imported++;
                $points += $row['points'];
            }

            $import->update([
                'rows_imported' => $imported,
                'patients_created' => $created,
                'points_total' => $points,
            ]);

            return $import;
        });
    }

    /**
     * La base trae el nombre en un solo campo y en orden variable
     * ("REYES SANDRA" / "MARIA CLAUDIA RUIZ DURAN"), así que no se puede
     * separar nombre/apellido con certeza. Se parte por la mitad para que
     * nombre + apellido reproduzca exactamente el nombre original.
     */
    private function splitName(string $name): array
    {
        $words = explode(' ', mb_convert_case(mb_strtolower($name), MB_CASE_TITLE, 'UTF-8'));

        if (count($words) === 1) {
            return [$words[0], '-'];
        }

        $half = intdiv(count($words), 2);

        return [implode(' ', array_slice($words, 0, $half)), implode(' ', array_slice($words, $half))];
    }
}
