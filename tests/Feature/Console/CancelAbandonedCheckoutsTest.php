<?php

namespace Tests\Feature\Console;

use App\Models\Patient;
use App\Models\PointBatch;
use App\Models\Reward;
use App\Services\Checkout\RedemptionService;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CancelAbandonedCheckoutsTest extends TestCase
{
    use RefreshDatabase;

    public function test_cancels_pending_checkouts_past_the_timeout_and_restores_points(): void
    {
        app(SettingsService::class)->set('redemption_points_per_100_cop', 1, 'decimal');
        app(SettingsService::class)->set('abandoned_checkout_timeout_minutes', 30, 'integer');

        $patient = Patient::create([
            'document_number' => '1',
            'first_name' => 'Ana',
            'last_name' => 'Pérez',
            'password' => '1',
        ]);

        PointBatch::create([
            'patient_id' => $patient->id,
            'points_original' => 100,
            'points_remaining' => 100,
            'earned_at' => now(),
            'expires_at' => now()->addMonths(12),
            'status' => 'active',
        ]);

        $reward = Reward::create(['slug' => 'premio', 'title' => 'Premio', 'price_cop' => 10000]);

        $outcome = app(RedemptionService::class)->checkout($patient, $reward, 20);
        $this->assertSame(80, $patient->pointsBalance());

        $outcome->redemption->forceFill(['created_at' => now()->subMinutes(40)])->saveQuietly();

        $this->artisan('checkout:cancel-abandoned')->assertSuccessful();

        $this->assertSame('cancelled', $outcome->redemption->refresh()->status);
        $this->assertSame(100, $patient->pointsBalance());
    }

    public function test_does_not_cancel_recent_pending_checkouts(): void
    {
        app(SettingsService::class)->set('redemption_points_per_100_cop', 1, 'decimal');
        app(SettingsService::class)->set('abandoned_checkout_timeout_minutes', 30, 'integer');

        $patient = Patient::create([
            'document_number' => '2',
            'first_name' => 'Ana',
            'last_name' => 'Pérez',
            'password' => '2',
        ]);

        PointBatch::create([
            'patient_id' => $patient->id,
            'points_original' => 100,
            'points_remaining' => 100,
            'earned_at' => now(),
            'expires_at' => now()->addMonths(12),
            'status' => 'active',
        ]);

        $reward = Reward::create(['slug' => 'premio2', 'title' => 'Premio 2', 'price_cop' => 10000]);
        $outcome = app(RedemptionService::class)->checkout($patient, $reward, 20);

        $this->artisan('checkout:cancel-abandoned')->assertSuccessful();

        $this->assertSame('pending_payment', $outcome->redemption->refresh()->status);
        $this->assertSame(80, $patient->pointsBalance());
    }
}
