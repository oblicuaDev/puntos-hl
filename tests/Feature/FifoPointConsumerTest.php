<?php

namespace Tests\Feature;

use App\Models\Patient;
use App\Models\PointBatch;
use App\Models\Redemption;
use App\Models\Reward;
use App\Services\Points\FifoPointConsumer;
use App\Services\Points\InsufficientPointsException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FifoPointConsumerTest extends TestCase
{
    use RefreshDatabase;

    private function makePatient(): Patient
    {
        return Patient::create([
            'document_number' => '1234567890',
            'first_name' => 'Ana',
            'last_name' => 'Pérez',
            'password' => 'cedula-hash',
            'registration_source' => 'manual_admin',
        ]);
    }

    private function makeRedemption(Patient $patient): Redemption
    {
        $reward = Reward::create([
            'slug' => 'premio-test',
            'title' => 'Premio de prueba',
            'price_cop' => 100000,
        ]);

        return Redemption::create([
            'code' => 'HLF-TEST-'.uniqid(),
            'patient_id' => $patient->id,
            'reward_id' => $reward->id,
            'reward_title_snapshot' => $reward->title,
            'reward_price_cop_snapshot' => $reward->price_cop,
            'points_used' => 0,
            'money_amount_cop' => 0,
            'points_percentage_applied' => 0,
            'payment_method' => 'points_only',
            'status' => 'pending_payment',
        ]);
    }

    public function test_consumes_the_batch_that_expires_soonest_first(): void
    {
        $patient = $this->makePatient();

        $soon = PointBatch::create([
            'patient_id' => $patient->id,
            'points_original' => 50,
            'points_remaining' => 50,
            'earned_at' => now()->subMonths(6),
            'expires_at' => now()->addDays(10),
            'status' => 'active',
        ]);

        $later = PointBatch::create([
            'patient_id' => $patient->id,
            'points_original' => 100,
            'points_remaining' => 100,
            'earned_at' => now(),
            'expires_at' => now()->addMonths(11),
            'status' => 'active',
        ]);

        $redemption = $this->makeRedemption($patient);

        app(FifoPointConsumer::class)->consume($redemption, 70);

        $soon->refresh();
        $later->refresh();

        $this->assertSame(0, $soon->points_remaining);
        $this->assertSame('exhausted', $soon->status);
        $this->assertSame(80, $later->points_remaining);
        $this->assertSame('active', $later->status);

        $this->assertSame(80, $patient->pointsBalance());
    }

    public function test_throws_when_balance_is_insufficient(): void
    {
        $patient = $this->makePatient();

        PointBatch::create([
            'patient_id' => $patient->id,
            'points_original' => 10,
            'points_remaining' => 10,
            'earned_at' => now(),
            'expires_at' => now()->addMonths(12),
            'status' => 'active',
        ]);

        $redemption = $this->makeRedemption($patient);

        $this->expectException(InsufficientPointsException::class);

        app(FifoPointConsumer::class)->consume($redemption, 50);
    }

    public function test_ignores_expired_batches(): void
    {
        $patient = $this->makePatient();

        PointBatch::create([
            'patient_id' => $patient->id,
            'points_original' => 100,
            'points_remaining' => 100,
            'earned_at' => now()->subMonths(13),
            'expires_at' => now()->subDay(),
            'status' => 'active',
        ]);

        $this->assertSame(0, $patient->pointsBalance());

        $redemption = $this->makeRedemption($patient);

        $this->expectException(InsufficientPointsException::class);

        app(FifoPointConsumer::class)->consume($redemption, 1);
    }

    public function test_reverse_restores_points_to_their_original_batches(): void
    {
        $patient = $this->makePatient();

        $batch = PointBatch::create([
            'patient_id' => $patient->id,
            'points_original' => 100,
            'points_remaining' => 100,
            'earned_at' => now(),
            'expires_at' => now()->addMonths(12),
            'status' => 'active',
        ]);

        $redemption = $this->makeRedemption($patient);

        app(FifoPointConsumer::class)->consume($redemption, 60);
        $batch->refresh();
        $this->assertSame(40, $batch->points_remaining);

        app(FifoPointConsumer::class)->reverse($redemption);
        $batch->refresh();

        $this->assertSame(100, $batch->points_remaining);
        $this->assertSame(100, $patient->pointsBalance());
    }
}
