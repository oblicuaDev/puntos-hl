<?php

namespace App\Console\Commands;

use App\Models\PointBatch;
use App\Models\PointMovement;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ExpirePointBatches extends Command
{
    protected $signature = 'points:expire-batches';

    protected $description = 'Marca como vencidos los lotes de puntos cuya vigencia de 1 año ya pasó y registra el movimiento correspondiente.';

    public function handle(): int
    {
        $expired = 0;

        PointBatch::where('status', 'active')
            ->where('expires_at', '<=', now())
            ->where('points_remaining', '>', 0)
            ->chunkById(200, function ($batches) use (&$expired) {
                foreach ($batches as $batch) {
                    DB::transaction(function () use ($batch) {
                        $pointsLost = $batch->points_remaining;

                        $batch->update(['status' => 'expired']);

                        PointMovement::create([
                            'patient_id' => $batch->patient_id,
                            'type' => 'expired',
                            'points' => -$pointsLost,
                            'related_type' => PointBatch::class,
                            'related_id' => $batch->id,
                            'description' => 'Vencimiento de puntos (lote #'.$batch->id.')',
                            'occurred_at' => now(),
                        ]);
                    });

                    $expired++;
                }
            });

        $this->info("Lotes vencidos procesados: {$expired}");

        return self::SUCCESS;
    }
}
