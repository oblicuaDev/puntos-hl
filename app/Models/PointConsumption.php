<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['point_batch_id', 'redemption_id', 'points_consumed', 'reversed_at'])]
class PointConsumption extends Model
{
    protected function casts(): array
    {
        return [
            'reversed_at' => 'datetime',
        ];
    }

    public function pointBatch()
    {
        return $this->belongsTo(PointBatch::class);
    }

    public function redemption()
    {
        return $this->belongsTo(Redemption::class);
    }
}
