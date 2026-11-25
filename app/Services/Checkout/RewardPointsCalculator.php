<?php

namespace App\Services\Checkout;

use App\Models\Patient;
use App\Models\Reward;
use App\Services\Points\PointRates;

/**
 * Límites autoritativos del slider puntos/dinero para un premio y un
 * paciente concretos. El servidor siempre recalcula esto; el valor que
 * llega del cliente es solo una intención.
 */
class RewardPointsCalculator
{
    public function __construct(
        public readonly int $totalPointsEquivalent,
        public readonly int $minPoints,
        public readonly int $maxPoints,
        /** Valor en COP de 1 punto al redimir (100 / puntos por cada $100). */
        public readonly float $copPerPoint,
        public readonly float $redemptionRate,
    ) {}

    public static function forPatient(Reward $reward, Patient $patient): self
    {
        $redemptionRate = PointRates::redemptionPointsPer100Cop();
        $minPercentage = (float) setting('min_points_percentage', 10);

        $total = $reward->pointsEquivalent();
        $balance = $patient->pointsBalance();

        $minPoints = (int) ceil($total * ($minPercentage / 100));
        $maxPoints = min($balance, $total);

        return new self(
            totalPointsEquivalent: $total,
            minPoints: min($minPoints, $total),
            maxPoints: $maxPoints,
            copPerPoint: PointRates::copValuePerRedeemedPoint(),
            redemptionRate: $redemptionRate,
        );
    }

    public function canAffordPointsOnly(int $patientBalance): bool
    {
        return $patientBalance >= $this->totalPointsEquivalent;
    }

    public function canCheckout(int $patientBalance): bool
    {
        return $patientBalance >= $this->minPoints;
    }
}
