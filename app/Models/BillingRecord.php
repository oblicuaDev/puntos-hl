<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphOne;

#[Fillable([
    'billing_sync_run_id', 'patient_id', 'spreadsheet_id', 'sheet_row', 'fingerprint',
    'document_number', 'patient_name', 'invoice_date', 'amount_cop', 'sheet_points',
    'points', 'accrual_cop_per_point', 'patient_created',
])]
class BillingRecord extends Model
{
    protected function casts(): array
    {
        return [
            'invoice_date' => 'date',
            'accrual_cop_per_point' => 'decimal:4',
            'patient_created' => 'boolean',
        ];
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(BillingSyncRun::class, 'billing_sync_run_id');
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function pointBatch(): MorphOne
    {
        return $this->morphOne(PointBatch::class, 'sourceable');
    }
}
