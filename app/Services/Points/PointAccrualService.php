<?php

namespace App\Services\Points;

use App\Models\PointBatch;
use App\Models\PointMovement;
use App\Models\Sale;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Acredita puntos a un paciente: crea el lote (point_batches, con su fecha de
 * vencimiento a N meses desde la causación) y el movimiento correspondiente
 * en el historial, ambos ligados al registro que los originó (venta, fila de
 * la hoja de facturación, …).
 */
class PointAccrualService
{
    public function accrueForSale(Sale $sale): PointBatch
    {
        return $this->accrue($sale->patient_id, $sale->points_awarded, $sale, 'Puntos ganados por venta #'.$sale->id, now());
    }

    public function accrue(int $patientId, int $points, Model $source, string $description, CarbonInterface $earnedAt): PointBatch
    {
        return DB::transaction(function () use ($patientId, $points, $source, $description, $earnedAt) {
            $months = (int) setting('points_expiration_months', 12);

            $batch = PointBatch::create([
                'patient_id' => $patientId,
                'points_original' => $points,
                'points_remaining' => $points,
                'earned_at' => $earnedAt,
                'expires_at' => $earnedAt->copy()->addMonths($months),
                'status' => 'active',
                'sourceable_type' => $source->getMorphClass(),
                'sourceable_id' => $source->getKey(),
            ]);

            PointMovement::create([
                'patient_id' => $patientId,
                'type' => 'earned',
                'points' => $points,
                'related_type' => $source->getMorphClass(),
                'related_id' => $source->getKey(),
                'description' => $description,
                'occurred_at' => $earnedAt,
            ]);

            return $batch;
        });
    }
}
