<?php

namespace App\Services\Points;

use App\Models\PointBatch;
use App\Models\PointMovement;
use App\Models\Sale;
use Illuminate\Support\Facades\DB;

/**
 * Acredita puntos a un paciente cuando se registra una venta: crea el lote
 * (point_batches, con su fecha de vencimiento a N meses) y el movimiento
 * correspondiente en el historial.
 */
class PointAccrualService
{
    public function accrueForSale(Sale $sale): PointBatch
    {
        return DB::transaction(function () use ($sale) {
            $months = (int) setting('points_expiration_months', 12);
            $earnedAt = now();

            $batch = PointBatch::create([
                'patient_id' => $sale->patient_id,
                'points_original' => $sale->points_awarded,
                'points_remaining' => $sale->points_awarded,
                'earned_at' => $earnedAt,
                'expires_at' => $earnedAt->copy()->addMonths($months),
                'status' => 'active',
                'sourceable_type' => Sale::class,
                'sourceable_id' => $sale->id,
            ]);

            PointMovement::create([
                'patient_id' => $sale->patient_id,
                'type' => 'earned',
                'points' => $sale->points_awarded,
                'related_type' => Sale::class,
                'related_id' => $sale->id,
                'description' => 'Puntos ganados por venta #'.$sale->id,
                'occurred_at' => $earnedAt,
            ]);

            return $batch;
        });
    }
}
