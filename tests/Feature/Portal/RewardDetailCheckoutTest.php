<?php

namespace Tests\Feature\Portal;

use App\Models\Patient;
use App\Models\PointBatch;
use App\Models\Redemption;
use App\Models\Reward;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class RewardDetailCheckoutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(SettingsService::class)->set('cop_per_point', 100, 'decimal');
        app(SettingsService::class)->set('min_points_percentage', 10, 'decimal');
    }

    private function patientWithPoints(int $points): Patient
    {
        $patient = Patient::create([
            'document_number' => (string) random_int(1000000000, 1999999999),
            'first_name' => 'Ana',
            'last_name' => 'Pérez',
            'password' => 'x',
            'must_change_password' => false,
        ]);

        PointBatch::create([
            'patient_id' => $patient->id,
            'points_original' => $points,
            'points_remaining' => $points,
            'earned_at' => now(),
            'expires_at' => now()->addMonths(12),
            'status' => 'active',
        ]);

        return $patient;
    }

    public function test_checking_out_100_percent_points_redirects_to_success(): void
    {
        $patient = $this->patientWithPoints(100);
        $this->actingAs($patient, 'patient');

        $reward = Reward::create(['slug' => 'premio', 'title' => 'Premio', 'price_cop' => 10000]); // 100 pts

        Livewire::test('portal.reward-detail', ['reward' => $reward])
            ->set('pointsToUse', 100)
            ->call('checkout')
            ->assertRedirect();

        $redemption = Redemption::where('patient_id', $patient->id)->firstOrFail();
        $this->assertSame('approved', $redemption->status);
        $this->assertSame(0, $patient->pointsBalance());
    }

    public function test_mixed_checkout_redirects_to_the_stub_payment_screen(): void
    {
        $patient = $this->patientWithPoints(100);
        $this->actingAs($patient, 'patient');

        $reward = Reward::create(['slug' => 'premio2', 'title' => 'Premio 2', 'price_cop' => 10000]);

        Livewire::test('portal.reward-detail', ['reward' => $reward])
            ->set('pointsToUse', 20)
            ->call('checkout')
            ->assertRedirect();

        $redemption = Redemption::where('patient_id', $patient->id)->firstOrFail();
        $this->assertSame('pending_payment', $redemption->status);
        $this->assertSame(80, $patient->pointsBalance());
    }

    public function test_slider_defaults_to_the_maximum_points_available(): void
    {
        $patient = $this->patientWithPoints(5);
        $this->actingAs($patient, 'patient');

        $reward = Reward::create(['slug' => 'premio3', 'title' => 'Premio 3', 'price_cop' => 10000]); // 100 pts, paciente solo tiene 5

        Livewire::test('portal.reward-detail', ['reward' => $reward])
            ->assertSet('pointsToUse', 5);
    }

    public function test_cannot_checkout_below_the_minimum_points_percentage(): void
    {
        $patient = $this->patientWithPoints(100);
        $this->actingAs($patient, 'patient');

        $reward = Reward::create(['slug' => 'premio4', 'title' => 'Premio 4', 'price_cop' => 10000]);

        Livewire::test('portal.reward-detail', ['reward' => $reward])
            ->set('pointsToUse', 1)
            ->call('checkout')
            ->assertSet('checkoutError', fn ($value) => ! empty($value));

        $this->assertDatabaseCount('redemptions', 0);
    }
}
