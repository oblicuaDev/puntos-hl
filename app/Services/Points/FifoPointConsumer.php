<?php

namespace App\Services\Points;

use App\Models\PointBatch;
use App\Models\PointConsumption;
use App\Models\PointMovement;
use App\Models\Redemption;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Consume puntos de un paciente en orden FIFO por fecha de vencimiento
 * (se gasta primero lo que vence antes), con locking para evitar condiciones
 * de carrera entre redenciones concurrentes del mismo paciente.
 */
class FifoPointConsumer
{
    /**
     * @return Collection<int, PointConsumption>
     *
     * @throws InsufficientPointsException
     */
    public function consume(Redemption $redemption, int $pointsNeeded): Collection
    {
        return DB::transaction(function () use ($redemption, $pointsNeeded) {
            $batches = PointBatch::where('patient_id', $redemption->patient_id)
                ->activeAndValid()
                ->orderBy('expires_at', 'asc')
                ->lockForUpdate()
                ->get();

            $available = (int) $batches->sum('points_remaining');

            if ($available < $pointsNeeded) {
                throw new InsufficientPointsException(
                    "El paciente #{$redemption->patient_id} tiene {$available} puntos vigentes, se necesitan {$pointsNeeded}."
                );
            }

            $remaining = $pointsNeeded;
            $consumptions = collect();

            foreach ($batches as $batch) {
                if ($remaining <= 0) {
                    break;
                }

                $take = min($remaining, $batch->points_remaining);

                $batch->points_remaining -= $take;
                $batch->status = $batch->points_remaining === 0 ? 'exhausted' : 'active';
                $batch->save();

                $consumptions->push(PointConsumption::create([
                    'point_batch_id' => $batch->id,
                    'redemption_id' => $redemption->id,
                    'points_consumed' => $take,
                ]));

                $remaining -= $take;
            }

            PointMovement::create([
                'patient_id' => $redemption->patient_id,
                'type' => 'redeemed',
                'points' => -$pointsNeeded,
                'related_type' => Redemption::class,
                'related_id' => $redemption->id,
                'description' => "Canje {$redemption->code}",
                'occurred_at' => now(),
            ]);

            return $consumptions;
        });
    }

    /** Revierte los puntos consumidos por una redención (p.ej. pago mixto rechazado). */
    public function reverse(Redemption $redemption): void
    {
        DB::transaction(function () use ($redemption) {
            $consumptions = PointConsumption::where('redemption_id', $redemption->id)
                ->whereNull('reversed_at')
                ->lockForUpdate()
                ->get();

            if ($consumptions->isEmpty()) {
                return;
            }

            $totalReversed = 0;

            foreach ($consumptions as $consumption) {
                $batch = PointBatch::lockForUpdate()->find($consumption->point_batch_id);

                if (! $batch) {
                    throw new RuntimeException("PointBatch #{$consumption->point_batch_id} no encontrado al revertir.");
                }

                $batch->points_remaining += $consumption->points_consumed;
                $batch->status = $batch->expires_at->isPast() ? 'expired' : 'active';
                $batch->save();

                $consumption->update(['reversed_at' => now()]);
                $totalReversed += $consumption->points_consumed;
            }

            PointMovement::create([
                'patient_id' => $redemption->patient_id,
                'type' => 'adjustment_credit',
                'points' => $totalReversed,
                'related_type' => Redemption::class,
                'related_id' => $redemption->id,
                'description' => "Reverso de puntos — canje {$redemption->code} rechazado/cancelado",
                'occurred_at' => now(),
            ]);
        });
    }
}
