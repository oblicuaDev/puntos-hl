<?php

namespace Tests\Feature;

use App\Models\Patient;
use App\Models\PointBatch;
use App\Models\Reward;
use App\Services\Checkout\RedemptionService;
use App\Services\Points\PointRates;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PointRatesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $settings = app(SettingsService::class);
        $settings->set('accrual_cop_per_point', 1000, 'decimal');
        $settings->set('redemption_points_per_100_cop', 3, 'decimal');
        $settings->set('min_points_percentage', 10, 'decimal');
    }

    public function test_accrual_awards_one_point_per_complete_1000_cop(): void
    {
        $this->assertSame(26186, PointRates::pointsEarnedFor(26186500));
        $this->assertSame(0, PointRates::pointsEarnedFor(999));
        $this->assertSame(1, PointRates::pointsEarnedFor(1000));
    }

    public function test_redemption_charges_three_points_per_100_cop(): void
    {
        $reward = Reward::create(['slug' => 'venus', 'title' => 'Venus', 'price_cop' => 1270000]);

        $this->assertSame(38100, $reward->pointsEquivalent());
        $this->assertSame(2640, Reward::make(['price_cop' => 88000])->pointsEquivalent());
    }

    public function test_rates_are_independent(): void
    {
        app(SettingsService::class)->set('accrual_cop_per_point', 500, 'decimal');

        $reward = Reward::create(['slug' => 'r', 'title' => 'R', 'price_cop' => 100000]);

        $this->assertSame(3000, $reward->pointsEquivalent());
        $this->assertSame(200, PointRates::pointsEarnedFor(100000));
    }

    public function test_mixed_checkout_charges_money_at_the_redemption_rate_and_snapshots_it(): void
    {
        Mail::fake();

        $patient = Patient::create(['document_number' => '1', 'first_name' => 'Ana', 'last_name' => 'P', 'password' => '1']);
        PointBatch::create([
            'patient_id' => $patient->id, 'points_original' => 3000, 'points_remaining' => 3000,
            'earned_at' => now(), 'expires_at' => now()->addYear(), 'status' => 'active',
        ]);

        // $200.000 → 6.000 puntos. Con 3.000 puntos queda la mitad: $100.000.
        $reward = Reward::create(['slug' => 'p', 'title' => 'P', 'price_cop' => 200000]);

        $outcome = app(RedemptionService::class)->checkout($patient, $reward, 3000);

        $this->assertSame(100000, (int) $outcome->redemption->money_amount_cop);
        $this->assertEquals(3, $outcome->redemption->redemption_rate_applied);

        // Cambiar la tasa después no altera el canje ya registrado.
        app(SettingsService::class)->set('redemption_points_per_100_cop', 5, 'decimal');

        $redemption = $outcome->redemption->fresh();
        $this->assertSame(3000, (int) $redemption->points_used);
        $this->assertSame(100000, (int) $redemption->money_amount_cop);
        $this->assertSame(10000, $reward->fresh()->pointsEquivalent());
    }
}
