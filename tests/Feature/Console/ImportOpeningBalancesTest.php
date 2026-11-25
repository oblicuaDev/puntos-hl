<?php

namespace Tests\Feature\Console;

use App\Models\Patient;
use App\Models\PointImport;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ImportOpeningBalancesTest extends TestCase
{
    use RefreshDatabase;

    private string $csv;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        app(SettingsService::class)->set('accrual_cop_per_point', 1000, 'decimal');
        app(SettingsService::class)->set('points_expiration_months', 12, 'integer');

        $this->csv = tempnam(sys_get_temp_dir(), 'saldos').'.csv';
        file_put_contents($this->csv, implode("\n", [
            "\xEF\xBB\xBFCEDULA / NIT,NOMBRE,VALOR FINAL FACTURADO,PUNTOS ASIGNAR,CELULAR CON INDICATIVO,CORREO",
            '52287290,REYES SANDRA,26.186.500,261865,573125378053,sandra@example.com',
            '111,SIN TELEFONO,1.000.000,10000,,',
            '222,CORREO A,2.000.000,20000,573000000001,compartido@example.com',
            '333,CORREO B,3.000.000,30000,573000000002,compartido@example.com',
            '444,DUPLICADO,1.000.000,10000,573000000003,',
            '444,DUPLICADO OTRA VEZ,1.000.000,10000,573000000004,',
            '555,EXISTENTE,5.500.999,55009,573000000005,',
        ]));
    }

    protected function tearDown(): void
    {
        @unlink($this->csv);
        parent::tearDown();
    }

    public function test_validation_mode_writes_nothing(): void
    {
        $this->artisan('patients:import-opening-balances', ['file' => $this->csv])->assertSuccessful();

        $this->assertDatabaseCount('patients', 0);
        $this->assertDatabaseCount('point_batches', 0);
    }

    public function test_imports_valid_rows_with_traceable_accrual_movements(): void
    {
        $existing = Patient::create(['document_number' => '555', 'first_name' => 'Ya', 'last_name' => 'Existe', 'password' => 'x']);

        $this->artisan('patients:import-opening-balances', ['file' => $this->csv, '--commit' => true])->assertSuccessful();

        $import = PointImport::sole();
        $this->assertSame(4, $import->rows_imported);
        $this->assertSame(3, $import->rows_rejected); // sin teléfono + 2 documentos duplicados
        $this->assertSame(3, $import->patients_created);

        $sandra = Patient::where('document_number', '52287290')->sole();
        $this->assertSame(26186, $sandra->pointsBalance()); // 1 pt por cada $1.000, no la columna de la base
        $this->assertSame('+573125378053', $sandra->phone);
        $this->assertSame('Reyes Sandra', $sandra->fullName());
        $this->assertFalse(Hash::check('52287290', $sandra->password));

        $movement = $sandra->pointMovements()->sole();
        $this->assertSame('earned', $movement->type);
        $this->assertSame(PointImport::class, $movement->related_type);
        $this->assertSame($import->id, $movement->related_id);

        $batch = $sandra->pointBatches()->sole();
        $this->assertSame(PointImport::class, $batch->sourceable_type);
        $this->assertTrue($batch->expires_at->isSameDay(now()->addMonths(12)));

        // El correo compartido entre dos pacientes no se asigna a ninguno.
        $this->assertNull(Patient::where('document_number', '222')->value('email'));
        $this->assertDatabaseMissing('patients', ['document_number' => '111']);
        $this->assertDatabaseMissing('patients', ['document_number' => '444']);

        // Paciente existente: recibe el saldo y el teléfono, conserva su nombre.
        $existing->refresh();
        $this->assertSame(5500, $existing->pointsBalance());
        $this->assertSame('+573000000005', $existing->phone);
        $this->assertSame('Ya Existe', $existing->fullName());
    }

    public function test_does_not_give_the_balance_to_a_self_registered_account_with_another_phone(): void
    {
        Patient::create([
            'document_number' => '52287290', 'first_name' => 'Otra', 'last_name' => 'Persona',
            'phone' => '+573999999999', 'password' => 'x', 'registration_source' => 'self_registered',
        ]);

        $this->artisan('patients:import-opening-balances', ['file' => $this->csv, '--commit' => true])->assertSuccessful();

        $this->assertSame(0, Patient::where('document_number', '52287290')->sole()->pointsBalance());
    }

    public function test_running_twice_does_not_duplicate_balances(): void
    {
        $this->artisan('patients:import-opening-balances', ['file' => $this->csv, '--commit' => true]);
        $this->artisan('patients:import-opening-balances', ['file' => $this->csv, '--commit' => true]);

        $this->assertSame(26186, Patient::where('document_number', '52287290')->sole()->pointsBalance());
        $this->assertDatabaseCount('point_movements', 4);
    }
}
