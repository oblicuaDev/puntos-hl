<?php

namespace Tests\Feature\Admin;

use App\Models\AdminUser;
use App\Models\Patient;
use App\Models\PointBatch;
use App\Models\PointMovement;
use App\Models\Sale;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_shows_purchases_with_points_and_redemption_value_and_expired_points(): void
    {
        app(SettingsService::class)->set('redemption_points_per_100_cop', 3, 'decimal');

        $admin = AdminUser::create(['wp_user_id' => 1, 'email' => 'a@harkerlloreda.com', 'display_name' => 'Admin', 'role' => 'admin', 'is_active' => true]);
        $patient = Patient::create(['document_number' => '1', 'first_name' => 'Ana', 'last_name' => 'P', 'password' => 'x']);

        Sale::create([
            'patient_id' => $patient->id, 'admin_user_id' => $admin->id, 'amount_cop' => 3000000,
            'exchange_rate_applied' => 1000, 'points_awarded' => 3000, 'sale_date' => now()->toDateString(),
        ]);

        PointMovement::create(['patient_id' => $patient->id, 'type' => 'expired', 'points' => -450, 'description' => 'x', 'occurred_at' => now()]);
        PointBatch::create([
            'patient_id' => $patient->id, 'points_original' => 700, 'points_remaining' => 700,
            'earned_at' => now()->subYear(), 'expires_at' => now()->addDays(10), 'status' => 'active',
        ]);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Compras registradas')
            ->assertSee('$3.000.000')
            ->assertSee('3.000 pts')
            // 3.000 pts a 3 pts por $100 → $100.000 al redimir (no $3.000.000).
            ->assertSee('$100.000')
            ->assertSee('Puntos vencidos')
            ->assertSee('450')
            ->assertSee('700 vencen en los próximos 30 días');
    }
}
