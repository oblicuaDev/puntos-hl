<?php

namespace App\Services\Sso;

use App\Models\AdminUser;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

/**
 * Lado Laravel del handshake SSO con el plugin de WordPress "HL Puntos SSO Bridge".
 *
 * Flujo: wp-admin genera un JWT corto (HS256) y redirige al navegador del
 * staff a /admin/sso/callback?token=... . Este servicio valida ese JWT y
 * deja al admin_user autenticado en el guard "admin" de Laravel, con una
 * sesión propia e independiente de la de WordPress.
 */
class WordPressSsoService
{
    public function handle(string $token): AdminUser
    {
        $claims = $this->decodeAndValidate($token);

        $role = $this->mapRole($claims->role ?? null);

        if ($role === null) {
            throw new SsoException("El usuario de WordPress no tiene un rol admin/asesor reconocido (rol recibido: {$claims->role}).");
        }

        return AdminUser::updateOrCreate(
            ['wp_user_id' => $claims->wp_user_id],
            [
                'email' => $claims->email,
                'display_name' => $claims->display_name ?? $claims->email,
                'role' => $role,
                'is_active' => true,
                'last_synced_at' => now(),
            ]
        );
    }

    private function decodeAndValidate(string $token): object
    {
        $secret = config('sso.shared_secret');

        if (! $secret) {
            throw new RuntimeException('WP_SSO_SHARED_SECRET no está configurado.');
        }

        try {
            $claims = JWT::decode($token, new Key($secret, 'HS256'));
        } catch (\Throwable $e) {
            throw new SsoException('Token SSO inválido o expirado: '.$e->getMessage(), previous: $e);
        }

        $this->assertNotReplayed($claims->jti ?? null);

        $issuer = config('sso.issuer');
        if ($issuer && ($claims->iss ?? null) !== $issuer) {
            throw new SsoException('El token SSO no proviene del emisor esperado.');
        }

        foreach (['wp_user_id', 'email', 'role', 'jti'] as $required) {
            if (! isset($claims->$required)) {
                throw new SsoException("El token SSO no incluye el claim requerido \"{$required}\".");
            }
        }

        return $claims;
    }

    /** Anti-replay: cada jti solo puede usarse una vez. */
    private function assertNotReplayed(?string $jti): void
    {
        if ($jti === null) {
            throw new SsoException('El token SSO no incluye jti.');
        }

        $cacheKey = "sso:jti:{$jti}";

        if (Cache::has($cacheKey)) {
            throw new SsoException('Este token SSO ya fue utilizado (posible replay).');
        }

        Cache::put($cacheKey, true, now()->addSeconds(config('sso.token_ttl') * 2));
    }

    private function mapRole(?string $wpRole): ?string
    {
        return config("sso.role_map.{$wpRole}");
    }
}
