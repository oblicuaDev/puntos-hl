<?php

namespace Tests\Feature;

use App\Mail\RedemptionConfirmedMail;
use App\Mail\RedemptionNotifyManagementMail;
use App\Models\Patient;
use App\Models\PointBatch;
use App\Models\Reward;
use App\Services\Checkout\InvalidCheckoutException;
use App\Services\Checkout\RedemptionService;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class RedemptionServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $settings = app(SettingsService::class);
        $settings->set('redemption_points_per_100_cop', 1, 'decimal');
        $settings->set('min_points_percentage', 10, 'decimal');
        $settings->set('management_notification_email', 'gerencia@harkerlloreda.com', 'string');
    }

    private function makePatientWithPoints(int $points): Patient
    {
        $patient = Patient::create([
            'document_number' => (string) random_int(1000000000, 1999999999),
            'first_name' => 'Ana',
            'last_name' => 'Pérez',
            'email' => 'ana@example.com',
            'password' => 'cedula-hash',
            'registration_source' => 'manual_admin',
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

    private function makeReward(int $priceCop): Reward
    {
        return Reward::create([
            'slug' => 'premio-'.uniqid(),
            'title' => 'Premio de prueba',
            'price_cop' => $priceCop,
        ]);
    }

    public function test_points_only_checkout_approves_immediately_and_sends_mail(): void
    {
        Mail::fake();

        // price 10,000 COP @ 100 COP/punto = 100 puntos.
        $patient = $this->makePatientWithPoints(100);
        $reward = $this->makeReward(10000);

        $outcome = app(RedemptionService::class)->checkout($patient, $reward, 100);

        $this->assertFalse($outcome->requiresPayment());
        $this->assertSame('approved', $outcome->redemption->status);
        $this->assertSame(0, $patient->pointsBalance());

        Mail::assertQueued(RedemptionConfirmedMail::class);
        Mail::assertQueued(RedemptionNotifyManagementMail::class);
    }

    public function test_mixed_checkout_requires_at_least_the_minimum_points_percentage(): void
    {
        // total = 100 puntos, mínimo 10% = 10 puntos.
        $patient = $this->makePatientWithPoints(100);
        $reward = $this->makeReward(10000);

        $this->expectException(InvalidCheckoutException::class);

        app(RedemptionService::class)->checkout($patient, $reward, 5);
    }

    public function test_mixed_checkout_creates_a_pending_payment_via_the_stub_gateway(): void
    {
        $patient = $this->makePatientWithPoints(100);
        $reward = $this->makeReward(10000); // 100 puntos equivalentes

        $outcome = app(RedemptionService::class)->checkout($patient, $reward, 20);

        $this->assertTrue($outcome->requiresPayment());
        $this->assertSame('pending_payment', $outcome->redemption->status);
        $this->assertSame(8000, $outcome->redemption->money_amount_cop); // (100-20)*100
        $this->assertSame(80, $patient->pointsBalance()); // ya se reservaron los 20 puntos
    }

    public function test_rejecting_a_mixed_checkout_restores_the_reserved_points(): void
    {
        $patient = $this->makePatientWithPoints(100);
        $reward = $this->makeReward(10000);

        $outcome = app(RedemptionService::class)->checkout($patient, $reward, 20);
        $this->assertSame(80, $patient->pointsBalance());

        app(RedemptionService::class)->reject($outcome->redemption);

        $this->assertSame(100, $patient->pointsBalance());
        $this->assertSame('rejected', $outcome->redemption->refresh()->status);
    }

    public function test_cannot_checkout_more_points_than_the_patient_balance(): void
    {
        $patient = $this->makePatientWithPoints(10);
        $reward = $this->makeReward(10000); // 100 puntos equivalentes, paciente solo tiene 10

        $this->expectException(InvalidCheckoutException::class);

        app(RedemptionService::class)->checkout($patient, $reward, 50);
    }
}
