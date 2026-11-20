<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable([
    'document_type', 'document_number', 'first_name', 'last_name', 'email',
    'phone', 'address', 'city', 'birthdate', 'password', 'must_change_password',
    'registration_source', 'created_by_admin_user_id', 'status',
])]
#[Hidden(['password', 'remember_token'])]
class Patient extends Authenticatable
{
    use HasFactory, Notifiable, SoftDeletes;

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'must_change_password' => 'boolean',
            'birthdate' => 'date',
            'email_verified_at' => 'datetime',
        ];
    }

    public function fullName(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }

    public function createdByAdminUser()
    {
        return $this->belongsTo(AdminUser::class, 'created_by_admin_user_id');
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    public function pointBatches(): HasMany
    {
        return $this->hasMany(PointBatch::class);
    }

    public function pointMovements(): HasMany
    {
        return $this->hasMany(PointMovement::class);
    }

    public function redemptions(): HasMany
    {
        return $this->hasMany(Redemption::class);
    }

    /** Saldo vigente: suma de puntos restantes en lotes activos no vencidos. */
    public function pointsBalance(): int
    {
        return (int) $this->pointBatches()
            ->where('status', 'active')
            ->where('expires_at', '>', now())
            ->sum('points_remaining');
    }
}
