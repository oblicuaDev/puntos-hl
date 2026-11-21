<?php

namespace Tests\Feature;

use App\Models\Patient;
use App\Models\Payment;
use App\Models\PointBatch;
use App\Models\Redemption;
use App\Models\Reward;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayzenGatewayTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.payzen.api_test_password' => 'test-api-password',
            'services.payzen.api_password' => 'prod-api-password',
            'services.payzen.mode' => 'TEST',
            'payments.driver' => 'payzen',
        ]);
    }

    /** @return array{0: string, 1: string} [kr-answer, kr-hash] firmados con la contraseña de API (esquema de la IPN real). */
    private function signedAnswer(array $answer): array
    {
        $json = json_encode($answer);

        return [$json, hash_hmac('sha256', $json, 'test-api-password')];
    }

    private function makePatient(): Patient
    {
        return Patient::create([
            'document_number' => (string) random_int(1000000000, 1999999999),
            'first_name' => 'Ana',
            'last_name' => 'Pérez',
            'email' => 'ana-'.uniqid().'@example.com',
            'password' => 'cedula-hash',
            'registration_source' => 'manual_admin',
        ]);
    }

    private function makeReward(int $priceCop): Reward
    {
        return Reward::create([
            'slug' => 'premio-'.uniqid(),
            'title' => 'Premio de prueba',
            'price_cop' => $priceCop,
        ]);
    }

    private function makePendingRedemption(string $code, int $moneyAmountCop): Redemption
    {
        $patient = $this->makePatient();
        $reward = $this->makeReward($moneyAmountCop);

        $redemption = Redemption::create([
            'code' => $code,
            'patient_id' => $patient->id,
            'reward_id' => $reward->id,
            'reward_title_snapshot' => $reward->title,
            'reward_price_cop_snapshot' => $reward->price_cop,
            'points_used' => 0,
            'money_amount_cop' => $moneyAmountCop,
            'points_percentage_applied' => 0,
            'payment_method' => 'mixed',
            'status' => 'pending_payment',
        ]);

        Payment::create([
            'redemption_id' => $redemption->id,
            'provider' => 'payzen',
            'amount_cop' => $moneyAmountCop,
            'status' => 'pending',
        ]);

        return $redemption;
    }

    public function test_rejects_a_notification_with_an_invalid_hash(): void
    {
        $redemption = $this->makePendingRedemption('HLF-TEST-0001', 50000);

        [$krAnswer] = $this->signedAnswer([
            'orderDetails' => ['orderId' => $redemption->code],
            'orderStatus' => 'PAID',
            'transactions' => [['detailedStatus' => 'AUTHORISED', 'uuid' => 'abc123']],
        ]);

        $this->post(route('portal.checkout.payzen.ipn'), [
            'kr-answer' => $krAnswer,
            'kr-hash' => 'hash-falso',
            'kr-hash-key' => 'password',
            'kr-hash-algorithm' => 'sha256_hmac',
        ])->assertServerError();

        $this->assertSame('pending_payment', $redemption->fresh()->status);
    }

    public function test_approves_the_redemption_when_validly_signed_and_authorised(): void
    {
        $redemption = $this->makePendingRedemption('HLF-TEST-0002', 50000);

        [$krAnswer, $krHash] = $this->signedAnswer([
            'orderDetails' => ['orderId' => $redemption->code],
            'orderStatus' => 'PAID',
            'transactions' => [['detailedStatus' => 'AUTHORISED', 'uuid' => 'abc123']],
        ]);

        $this->post(route('portal.checkout.payzen.ipn'), [
            'kr-answer' => $krAnswer,
            'kr-hash' => $krHash,
            'kr-hash-key' => 'password',
            'kr-hash-algorithm' => 'sha256_hmac',
        ])->assertOk();

        $this->assertSame('approved', $redemption->fresh()->status);
        $this->assertSame('approved', $redemption->fresh()->payment->status);
    }

    public function test_reverses_reserved_points_when_validly_signed_and_declined(): void
    {
        $patient = $this->makePatient();
        $reward = $this->makeReward(30000);

        $batch = PointBatch::create([
            'patient_id' => $patient->id,
            'points_original' => 100,
            'points_remaining' => 0,
            'earned_at' => now(),
            'expires_at' => now()->addYear(),
            'status' => 'active',
        ]);

        $redemption = Redemption::create([
            'code' => 'HLF-TEST-0003',
            'patient_id' => $patient->id,
            'reward_id' => $reward->id,
            'reward_title_snapshot' => $reward->title,
            'reward_price_cop_snapshot' => $reward->price_cop,
            'points_used' => 100,
            'money_amount_cop' => 30000,
            'points_percentage_applied' => 10,
            'payment_method' => 'mixed',
            'status' => 'pending_payment',
        ]);
        $redemption->pointConsumptions()->create(['point_batch_id' => $batch->id, 'points_consumed' => 100]);

        Payment::create(['redemption_id' => $redemption->id, 'provider' => 'payzen', 'amount_cop' => 30000, 'status' => 'pending']);

        [$krAnswer, $krHash] = $this->signedAnswer([
            'orderDetails' => ['orderId' => $redemption->code],
            'orderStatus' => 'UNPAID',
            'transactions' => [['detailedStatus' => 'REFUSED', 'uuid' => 'abc123']],
        ]);

        $this->post(route('portal.checkout.payzen.ipn'), [
            'kr-answer' => $krAnswer,
            'kr-hash' => $krHash,
            'kr-hash-key' => 'password',
            'kr-hash-algorithm' => 'sha256_hmac',
        ])->assertOk();

        $this->assertSame('rejected', $redemption->fresh()->status);
        $this->assertSame(100, $batch->fresh()->points_remaining);
    }
}
