<?php

namespace App\Services\Checkout;

use App\Events\RedemptionApproved;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\Redemption;
use App\Models\Reward;
use App\Services\Payments\PaymentGatewayContract;
use App\Services\Points\FifoPointConsumer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Orquesta el canje de un premio: valida los límites del slider
 * puntos/dinero de forma autoritativa (nunca confía en el cliente),
 * consume puntos FIFO, y resuelve 100% puntos vs. pago mixto.
 */
class RedemptionService
{
    public function __construct(
        private FifoPointConsumer $pointConsumer,
        private PaymentGatewayContract $gateway,
    ) {}

    /**
     * @param  int  $pointsToUse  lo que el paciente eligió en el slider (intención, no verdad).
     */
    public function checkout(Patient $patient, Reward $reward, int $pointsToUse): CheckoutOutcome
    {
        $limits = RewardPointsCalculator::forPatient($reward, $patient);

        if ($pointsToUse > $limits->maxPoints || $pointsToUse < 0) {
            throw new InvalidCheckoutException("Puntos fuera de rango (0–{$limits->maxPoints}).");
        }

        $isPointsOnly = $pointsToUse === $limits->totalPointsEquivalent;

        if (! $isPointsOnly && $pointsToUse < $limits->minPoints) {
            throw new InvalidCheckoutException("Se requiere un mínimo de {$limits->minPoints} puntos para un pago mixto.");
        }

        if ($isPointsOnly && $patient->pointsBalance() < $limits->totalPointsEquivalent) {
            throw new InvalidCheckoutException('Saldo insuficiente para pagar 100% con puntos.');
        }

        $moneyAmountCop = $isPointsOnly
            ? 0
            : (int) round(($limits->totalPointsEquivalent - $pointsToUse) * $limits->copPerPoint);

        return DB::transaction(function () use ($patient, $reward, $pointsToUse, $moneyAmountCop, $isPointsOnly, $limits) {
            $redemption = Redemption::create([
                'code' => $this->generateCode(),
                'patient_id' => $patient->id,
                'reward_id' => $reward->id,
                'reward_title_snapshot' => $reward->title,
                'reward_price_cop_snapshot' => $reward->price_cop,
                'points_used' => $pointsToUse,
                'money_amount_cop' => $moneyAmountCop,
                'points_percentage_applied' => $limits->totalPointsEquivalent > 0
                    ? round(($pointsToUse / $limits->totalPointsEquivalent) * 100, 2)
                    : 0,
                'redemption_rate_applied' => $limits->redemptionRate,
                'payment_method' => $isPointsOnly ? 'points_only' : 'mixed',
                'status' => 'pending_payment',
            ]);

            $this->pointConsumer->consume($redemption, $pointsToUse);

            if ($isPointsOnly) {
                $this->approve($redemption);

                return new CheckoutOutcome($redemption, null);
            }

            $payment = Payment::create([
                'redemption_id' => $redemption->id,
                'provider' => config('payments.driver'),
                'amount_cop' => $moneyAmountCop,
                'status' => 'pending',
            ]);

            $initiation = $this->gateway->initiate($redemption, $payment);

            return new CheckoutOutcome($redemption, $initiation);
        });
    }

    public function approve(Redemption $redemption): void
    {
        DB::transaction(function () use ($redemption) {
            $redemption->update(['status' => 'approved', 'approved_at' => now()]);

            if ($redemption->payment) {
                $redemption->payment->update(['status' => 'approved', 'confirmed_at' => now()]);
            }
        });

        RedemptionApproved::dispatch($redemption->refresh());
    }

    public function reject(Redemption $redemption): void
    {
        DB::transaction(function () use ($redemption) {
            $redemption->update(['status' => 'rejected']);

            if ($redemption->payment) {
                $redemption->payment->update(['status' => 'declined']);
            }

            $this->pointConsumer->reverse($redemption);
        });
    }

    private function generateCode(): string
    {
        return 'HLF-'.now()->format('ymd').'-'.Str::upper(Str::random(6));
    }
}
