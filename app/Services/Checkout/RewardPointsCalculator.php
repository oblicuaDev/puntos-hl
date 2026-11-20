<?php

namespace App\Services\Checkout;

use App\Models\Patient;
use App\Models\Reward;

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
        public readonly float $copPerPoint,
    ) {}

    public static function forPatient(Reward $reward, Patient $patient): self
    {
        $copPerPoint = (float) setting('cop_per_point', 100);
        $minPercentage = (float) setting('min_points_percentage', 10);

        $total = $reward->pointsEquivalent();
        $balance = $patient->pointsBalance();

        $minPoints = (int) ceil($total * ($minPercentage / 100));
        $maxPoints = min($balance, $total);

        return new self(
            totalPointsEquivalent: $total,
            minPoints: min($minPoints, $total),
            maxPoints: $maxPoints,
            copPerPoint: $copPerPoint,
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
