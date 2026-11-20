<?php

namespace Tests\Feature\Console;

use App\Models\Patient;
use App\Models\PointBatch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExpirePointBatchesTest extends TestCase
{
    use RefreshDatabase;

    public function test_expires_batches_past_their_due_date_and_logs_the_movement(): void
    {
        $patient = Patient::create([
            'document_number' => '1',
            'first_name' => 'Ana',
            'last_name' => 'Pérez',
            'password' => '1',
        ]);

        $expired = PointBatch::create([
            'patient_id' => $patient->id,
            'points_original' => 100,
            'points_remaining' => 40,
            'earned_at' => now()->subMonths(13),
            'expires_at' => now()->subDay(),
            'status' => 'active',
        ]);

        $notYetExpired = PointBatch::create([
            'patient_id' => $patient->id,
            'points_original' => 50,
            'points_remaining' => 50,
            'earned_at' => now(),
            'expires_at' => now()->addMonths(12),
            'status' => 'active',
        ]);

        $this->artisan('points:expire-batches')->assertSuccessful();

        $this->assertSame('expired', $expired->refresh()->status);
        $this->assertSame('active', $notYetExpired->refresh()->status);

        $this->assertDatabaseHas('point_movements', [
            'patient_id' => $patient->id,
            'type' => 'expired',
            'points' => -40,
        ]);
    }

    public function test_does_not_touch_already_exhausted_batches(): void
    {
        $patient = Patient::create([
            'document_number' => '2',
            'first_name' => 'Ana',
            'last_name' => 'Pérez',
            'password' => '2',
        ]);

        PointBatch::create([
            'patient_id' => $patient->id,
            'points_original' => 100,
            'points_remaining' => 0,
            'earned_at' => now()->subMonths(13),
            'expires_at' => now()->subDay(),
            'status' => 'exhausted',
        ]);

        $this->artisan('points:expire-batches')->assertSuccessful();

        $this->assertDatabaseCount('point_movements', 0);
    }
}
