<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'source_filename', 'source_sha256', 'accrual_cop_per_point', 'earned_at', 'expires_at',
    'rows_total', 'rows_imported', 'rows_rejected', 'patients_created', 'points_total',
])]
class PointImport extends Model
{
    protected function casts(): array
    {
        return [
            'accrual_cop_per_point' => 'decimal:4',
            'earned_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function batches()
    {
        return $this->morphMany(PointBatch::class, 'sourceable');
    }
}
