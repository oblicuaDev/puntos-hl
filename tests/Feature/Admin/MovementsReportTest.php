<?php

namespace Tests\Feature\Admin;

use App\Models\AdminUser;
use App\Models\Patient;
use App\Models\PointMovement;
use App\Models\Procedure;
use App\Models\Redemption;
use App\Models\Reward;
use App\Models\Sale;
use App\Services\Reports\PointMovementsReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use OpenSpout\Reader\XLSX\Reader;
use Tests\TestCase;

class MovementsReportTest extends TestCase
{
    use RefreshDatabase;

    private function admin(string $role = 'admin'): AdminUser
    {
        return AdminUser::create(['wp_user_id' => random_int(1, 9999), 'email' => $role.'@harkerlloreda.com', 'display_name' => 'X', 'role' => $role, 'is_active' => true]);
    }

    /** @return array<int, array<int, mixed>> */
    private function readXlsx(string $path): array
    {
        $reader = new Reader;
        $reader->open($path);
        $rows = [];
        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                $rows[] = $row->toArray();
            }
        }
        $reader->close();

        return $rows;
    }

    public function test_report_has_requested_columns_running_balance_money_and_concept(): void
    {
        $admin = $this->admin();
        $patient = Patient::create(['document_number' => '52287290', 'first_name' => 'Sandra', 'last_name' => 'Reyes', 'phone' => '+573125378053', 'password' => 'x']);

        // Movimiento anterior al rango: cuenta para el saldo, no aparece en el reporte.
        PointMovement::create(['patient_id' => $patient->id, 'type' => 'earned', 'points' => 1000, 'description' => 'Saldo inicial', 'occurred_at' => '2026-10-15 10:00:00']);

        $sale = Sale::create([
            'patient_id' => $patient->id, 'admin_user_id' => $admin->id,
            'procedure_id' => Procedure::create(['name' => 'Hydrafacial', 'points_price' => 1])->id,
            'amount_cop' => 450000, 'exchange_rate_applied' => 1000, 'points_awarded' => 450, 'sale_date' => '2026-11-03',
        ]);
        PointMovement::create(['patient_id' => $patient->id, 'type' => 'earned', 'points' => 450, 'related_type' => Sale::class, 'related_id' => $sale->id, 'description' => 'Venta', 'occurred_at' => '2026-11-03 09:00:00']);

        $redemption = Redemption::forceCreate([
            'code' => 'HLF-1', 'patient_id' => $patient->id,
            'reward_id' => Reward::create(['slug' => 'r', 'title' => 'Peeling PRX', 'price_cop' => 500000])->id,
            'reward_title_snapshot' => 'Peeling PRX', 'reward_price_cop_snapshot' => 500000,
            'points_used' => 1200, 'money_amount_cop' => 460000, 'points_percentage_applied' => 8, 'payment_method' => 'mixed', 'status' => 'approved',
        ]);
        PointMovement::create(['patient_id' => $patient->id, 'type' => 'redeemed', 'points' => -1200, 'related_type' => Redemption::class, 'related_id' => $redemption->id, 'description' => 'Canje', 'occurred_at' => '2026-11-10 15:30:00']);

        $path = tempnam(sys_get_temp_dir(), 'rep').'.xlsx';
        (new PointMovementsReport(Carbon::parse('2026-11-01')->startOfDay(), Carbon::parse('2026-11-30')->endOfDay()))->writeTo($path);
        $rows = $this->readXlsx($path);
        @unlink($path);

        $this->assertSame('Fecha y hora', $rows[0][0]);
        $this->assertCount(3, $rows); // encabezado + 2 movimientos del rango

        [, $accrual, $redeem] = $rows;
        $this->assertSame(['Sandra Reyes', '52287290', '+573125378053', 'Acumulación', 450, 1450, 450000], [$accrual[1], $accrual[2], $accrual[3], $accrual[4], $accrual[5], $accrual[6], $accrual[7]]);
        $this->assertSame('Compra: Hydrafacial', $accrual[9]);

        $this->assertSame(['Redención', -1200, 250, 500000, 460000], [$redeem[4], $redeem[5], $redeem[6], $redeem[7], $redeem[8]]);
        $this->assertStringContainsString('Peeling PRX', $redeem[9]);
    }

    public function test_admin_downloads_and_asesor_cannot(): void
    {
        $this->actingAs($this->admin(), 'admin')
            ->get(route('admin.reports.movements', ['desde' => '2026-11-01', 'hasta' => '2026-11-30']))
            ->assertOk()
            ->assertDownload('movimientos-puntos_2026-11-01_a_2026-11-30.xlsx');

        $this->actingAs($this->admin('asesor'), 'admin')
            ->get(route('admin.reports.movements', ['desde' => '2026-11-01', 'hasta' => '2026-11-30']))
            ->assertStatus(403);
    }

    public function test_validates_the_date_range(): void
    {
        $this->actingAs($this->admin(), 'admin')
            ->from(route('admin.dashboard'))
            ->get(route('admin.reports.movements', ['desde' => '2026-11-30', 'hasta' => '2026-11-01']))
            ->assertRedirect(route('admin.dashboard'))
            ->assertSessionHasErrors('hasta');
    }
}
