<?php

namespace Tests\Feature\Console;

use App\Mail\BillingSyncReportMail;
use App\Mail\PointsEarnedMail;
use App\Models\BillingRecord;
use App\Models\BillingSyncRun;
use App\Models\Patient;
use App\Services\BillingSheet\BillingSheet;
use App\Services\BillingSheet\BillingSheetException;
use App\Services\Points\BillingSheetSync;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SyncBillingSheetTest extends TestCase
{
    use RefreshDatabase;

    private object $sheet;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-11-26 12:00:00');
        Mail::fake();
        app(SettingsService::class)->set('accrual_cop_per_point', 1000, 'decimal');
        app(SettingsService::class)->set('points_expiration_months', 12, 'integer');
        config(['services.billing_sheet.cutoff_date' => '2026-11-15']);

        $this->sheet = new class implements BillingSheet
        {
            /** @var array<int, array<int, mixed>> */
            public array $rows = [];

            public bool $failWrites = false;

            public function id(): string
            {
                return 'sheet-test';
            }

            public function rows(): array
            {
                return $this->rows;
            }

            public function write(array $cells): void
            {
                if ($this->failWrites) {
                    throw new BillingSheetException('No se pudo escribir en la hoja (HTTP 503).');
                }

                foreach ($cells as $cell => $value) {
                    preg_match('/^([A-Z])(\d+)$/', $cell, $m);
                    $this->rows[(int) $m[2]] = array_pad($this->rows[(int) $m[2]], 15, '');
                    $this->rows[(int) $m[2]][ord($m[1]) - 65] = $value;
                }
            }
        };

        $this->app->instance(BillingSheet::class, $this->sheet);
    }

    /** @param  array<int, mixed>  ...$rows filas de datos, desde la fila 2 */
    private function sheetRows(array ...$rows): void
    {
        $this->sheet->rows = [1 => BillingSheetSync::HEADERS];

        foreach ($rows as $index => $row) {
            $this->sheet->rows[$index + 2] = $row;
        }
    }

    /** Fila completa de un paciente nuevo (A–L y N). */
    private function newPatientRow(string $document, int $amount, string|int $date, string $email): array
    {
        return [$document, 'REYES SANDRA', 0, 0, $amount, $amount / 100, '3125378053', $email, '57', 'COLOMBIA', '573125378053', '', '', $date];
    }

    private function cell(int $row, string $column): string
    {
        return (string) ($this->sheet->rows[$row][ord($column) - 65] ?? '');
    }

    public function test_credits_existing_and_new_patients_and_marks_the_rows(): void
    {
        $existing = Patient::create(['document_number' => '555', 'first_name' => 'Ya', 'last_name' => 'Existe', 'email' => 'ya@example.com', 'password' => 'x']);
        $serial = (int) now()->startOfDay()->diffInDays('1899-12-30', true) - 1; // 25/11/2026 como número de serie de Sheets

        $this->sheetRows(
            ['555', '', '', '', 5500999, '', '', '', '', '', '', 'cliente frecuente', '', '20/11/2026'],
            $this->newPatientRow('52287290', 26186500, $serial, 'sandra@example.com'),
            ['999', 'YA REGISTRADA', 0, 0, 1000000, 10000, '', '', '', '', '', '', 'X', '20/11/2026'],
        );

        $this->artisan('points:sync-billing-sheet')->assertSuccessful();

        // Paciente existente: solo los puntos, con la tasa vigente (no PUNTOS ASIGNAR).
        $this->assertSame(5500, $existing->pointsBalance());
        $this->assertSame('Ya Existe', $existing->refresh()->fullName());

        $record = BillingRecord::where('document_number', '555')->sole();
        $movement = $existing->pointMovements()->sole();
        $this->assertSame([BillingRecord::class, $record->id], [$movement->related_type, $movement->related_id]);
        $this->assertSame(2, $record->sheet_row);
        $this->assertSame('2026-11-20', $record->invoice_date->format('Y-m-d'));
        $this->assertTrue($existing->pointBatches()->sole()->expires_at->isSameDay(now()->addMonths(12)));

        // Paciente nuevo: creado con los datos de la hoja.
        $sandra = Patient::where('document_number', '52287290')->sole();
        $this->assertSame('Reyes Sandra', $sandra->fullName());
        $this->assertSame('+573125378053', $sandra->phone);
        $this->assertSame('sandra@example.com', $sandra->email);
        $this->assertSame(26186, $sandra->pointsBalance());
        $this->assertTrue(BillingRecord::where('patient_id', $sandra->id)->sole()->patient_created);
        $this->assertSame('2026-11-25', BillingRecord::where('patient_id', $sandra->id)->sole()->invoice_date->format('Y-m-d'));

        // La hoja queda marcada; la fila que ya tenía X no se toca.
        $this->assertSame('X', $this->cell(2, 'M'));
        $this->assertSame('X', $this->cell(3, 'M'));
        $this->assertStringContainsString('5.500 puntos', $this->cell(2, 'O'));
        $this->assertStringContainsString('PUNTOS ASIGNAR decía 261.865', $this->cell(3, 'O'));
        $this->assertSame('', $this->cell(4, 'O'));
        $this->assertDatabaseMissing('patients', ['document_number' => '999']);

        Mail::assertQueued(PointsEarnedMail::class, fn ($mail) => $mail->hasTo('sandra@example.com') && $mail->points === 26186 && $mail->balance === 26186);
        Mail::assertQueued(PointsEarnedMail::class, fn ($mail) => $mail->hasTo('ya@example.com') && $mail->points === 5500);

        $run = BillingSyncRun::sole();
        $this->assertSame([2, 2, 1, 0, 31686], [$run->rows_read, $run->rows_processed, $run->patients_created, $run->rows_pending, $run->points_total]);
    }

    public function test_leaves_invalid_rows_pending_without_stopping_the_others(): void
    {
        Patient::create(['document_number' => '777', 'first_name' => 'Otra', 'last_name' => 'Persona', 'email' => 'usado@example.com', 'password' => 'x']);
        Patient::create(['document_number' => '555', 'first_name' => 'Ya', 'last_name' => 'Existe', 'password' => 'x']);

        $incomplete = $this->newPatientRow('111', 1000000, '20/11/2026', 'nuevo@example.com');
        $incomplete[9] = ''; // sin PAIS

        $this->sheetRows(
            ['555', '', '', '', 1000000, '', '', '', '', '', '', '', '', '10/11/2026'],   // anterior al corte
            ['555', '', '', '', 1000000, '', '', '', '', '', '', '', '', ''],             // sin fecha
            $incomplete,
            $this->newPatientRow('222', 1000000, '20/11/2026', 'usado@example.com'),
            ['555', '', '', '', 999, '', '', '', '', '', '', '', '', '20/11/2026'],        // no alcanza 1 punto
            ['555', '', '', '', 2000000, '', '', '', '', '', '', '', '', '20/11/2026'],    // válida
        );

        $this->artisan('points:sync-billing-sheet')->assertSuccessful();

        foreach ([2, 3, 4, 5, 6] as $row) {
            $this->assertSame('', $this->cell($row, 'M'), "La fila {$row} no debía marcarse");
            $this->assertStringStartsWith('Pendiente: ', $this->cell($row, 'O'));
        }

        $this->assertStringContainsString('ya está incluida en el saldo inicial', $this->cell(2, 'O'));
        $this->assertStringContainsString('Falta FECHA FACTURA', $this->cell(3, 'O'));
        $this->assertStringContainsString('faltan: PAIS', $this->cell(4, 'O'));
        $this->assertStringContainsString('CORREO ya pertenece a otro paciente', $this->cell(5, 'O'));
        $this->assertSame('X', $this->cell(7, 'M'));
        $this->assertSame(2000, Patient::where('document_number', '555')->sole()->pointsBalance());
        $this->assertDatabaseMissing('patients', ['document_number' => '111']);
        $this->assertDatabaseMissing('patients', ['document_number' => '222']);

        $run = BillingSyncRun::sole();
        $this->assertSame(5, $run->rows_pending);
        $this->assertSame([2, 3, 4, 5, 6], array_column($run->pending_rows, 'row'));
        Mail::assertQueued(BillingSyncReportMail::class, fn ($mail) => $mail->hasTo('gerencia@harkerlloreda.com'));
    }

    public function test_a_corrected_pending_row_is_processed_on_the_next_run(): void
    {
        $this->sheetRows($this->newPatientRow('111', 1000000, '', 'nuevo@example.com'));

        $this->artisan('points:sync-billing-sheet');
        $this->assertDatabaseMissing('patients', ['document_number' => '111']);

        $this->sheet->rows[2][13] = '20/11/2026';
        $this->artisan('points:sync-billing-sheet');

        $this->assertSame(1000, Patient::where('document_number', '111')->sole()->pointsBalance());
        $this->assertSame('X', $this->cell(2, 'M'));
    }

    public function test_running_twice_does_not_credit_twice(): void
    {
        $this->sheetRows($this->newPatientRow('52287290', 26186500, '20/11/2026', 'sandra@example.com'));

        $this->artisan('points:sync-billing-sheet');
        $this->artisan('points:sync-billing-sheet');

        $this->assertSame(26186, Patient::where('document_number', '52287290')->sole()->pointsBalance());
        $this->assertDatabaseCount('point_movements', 1);
    }

    public function test_if_the_mark_could_not_be_written_the_next_run_only_marks_the_row(): void
    {
        $this->sheetRows($this->newPatientRow('52287290', 26186500, '20/11/2026', 'sandra@example.com'));
        $this->sheet->failWrites = true;

        $this->artisan('points:sync-billing-sheet')->assertFailed();
        $this->assertSame('', $this->cell(2, 'M'));
        $this->assertStringContainsString('No se pudo escribir', BillingSyncRun::sole()->error);
        Mail::assertQueued(BillingSyncReportMail::class);

        $this->sheet->failWrites = false;
        $this->artisan('points:sync-billing-sheet')->assertSuccessful();

        $this->assertSame('X', $this->cell(2, 'M'));
        $this->assertDatabaseCount('point_movements', 1);
        $this->assertSame(0, BillingSyncRun::latest('id')->first()->rows_processed);
    }

    public function test_does_nothing_without_a_cutoff_date(): void
    {
        config(['services.billing_sheet.cutoff_date' => null]);
        $this->sheetRows($this->newPatientRow('52287290', 26186500, '20/11/2026', 'sandra@example.com'));

        $this->artisan('points:sync-billing-sheet')->assertFailed();

        $this->assertDatabaseCount('patients', 0);
        $this->assertDatabaseCount('billing_sync_runs', 0);
        $this->assertSame('', $this->cell(2, 'M'));
    }

    public function test_rejects_a_sheet_with_unexpected_headers(): void
    {
        $this->sheetRows($this->newPatientRow('52287290', 26186500, '20/11/2026', 'sandra@example.com'));
        $this->sheet->rows[1][13] = 'FECHA';

        $this->artisan('points:sync-billing-sheet')->assertFailed();

        $this->assertDatabaseCount('patients', 0);
        $this->assertStringContainsString('encabezados', BillingSyncRun::sole()->error);
    }

    public function test_dry_run_writes_nothing(): void
    {
        $this->sheetRows($this->newPatientRow('52287290', 26186500, '20/11/2026', 'sandra@example.com'));

        $this->artisan('points:sync-billing-sheet', ['--dry-run' => true])->assertSuccessful();

        $this->assertDatabaseCount('patients', 0);
        $this->assertDatabaseCount('billing_sync_runs', 0);
        $this->assertSame('', $this->cell(2, 'M'));
        Mail::assertNothingQueued();
    }
}
