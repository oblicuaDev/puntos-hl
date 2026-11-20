<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * Reflejo cacheado del rol de WordPress. No tiene password propio: la
 * autenticación real ocurre vía el handshake SSO (ver App\Services\Sso).
 */
#[Fillable(['wp_user_id', 'email', 'display_name', 'role', 'is_active', 'last_synced_at'])]
class AdminUser extends Authenticatable
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'last_synced_at' => 'datetime',
        ];
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isAsesor(): bool
    {
        return $this->role === 'asesor';
    }

    public function sales()
    {
        return $this->hasMany(Sale::class);
    }
}
