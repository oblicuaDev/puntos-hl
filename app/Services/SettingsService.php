<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

/**
 * Lectura/escritura cacheada de la configuración general del plan de
 * fidelización (tasas de acumulación y redención, % mínimo en puntos, vigencia de puntos, etc.).
 */
class SettingsService
{
    private const CACHE_TTL = 3600;

    private const CACHE_KEY = 'hlfid:settings';

    private const DEFAULTS = [
        'accrual_cop_per_point' => ['value' => 1000, 'type' => 'decimal'],
        'redemption_points_per_100_cop' => ['value' => 3, 'type' => 'decimal'],
        'min_points_percentage' => ['value' => 10, 'type' => 'decimal'],
        'points_expiration_months' => ['value' => 12, 'type' => 'integer'],
        'management_notification_email' => ['value' => 'gerencia@harkerlloreda.com', 'type' => 'string'],
        'contact_phones' => ['value' => '[]', 'type' => 'json'],
        'abandoned_checkout_timeout_minutes' => ['value' => 30, 'type' => 'integer'],
    ];

    public function get(string $key, mixed $default = null): mixed
    {
        $all = $this->all();

        if (array_key_exists($key, $all)) {
            return $all[$key];
        }

        return $default ?? (self::DEFAULTS[$key]['value'] ?? null);
    }

    public function set(string $key, mixed $value, string $type = 'string'): void
    {
        Setting::updateOrCreate(['key' => $key], [
            'value' => $type === 'json' ? json_encode($value) : $value,
            'type' => $type,
        ]);

        Cache::forget(self::CACHE_KEY);
    }

    public function all(): array
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL, function () {
            $values = self::DEFAULTS;

            foreach (Setting::all() as $setting) {
                $values[$setting->key] = [
                    'value' => $this->cast($setting->value, $setting->type),
                    'type' => $setting->type,
                ];
            }

            return array_map(fn ($v) => $v['value'], $values);
        });
    }

    private function cast(?string $value, string $type): mixed
    {
        return match ($type) {
            'integer' => (int) $value,
            'decimal' => (float) $value,
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'json' => json_decode($value ?? '[]', true),
            default => $value,
        };
    }
}
