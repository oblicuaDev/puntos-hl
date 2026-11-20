<?php

use App\Services\SettingsService;
use Livewire\Attributes\Validate;
use Livewire\Component;

new class extends Component
{
    #[Validate('required|numeric|min:0.01')]
    public float $cop_per_point;

    #[Validate('required|numeric|min:0|max:100')]
    public float $min_points_percentage;

    #[Validate('required|integer|min:1')]
    public int $points_expiration_months;

    #[Validate('required|integer|min:1')]
    public int $abandoned_checkout_timeout_minutes;

    #[Validate('required|email')]
    public string $management_notification_email;

    public bool $saved = false;

    public function mount(): void
    {
        $settings = app(SettingsService::class);

        $this->cop_per_point = (float) $settings->get('cop_per_point');
        $this->min_points_percentage = (float) $settings->get('min_points_percentage');
        $this->points_expiration_months = (int) $settings->get('points_expiration_months');
        $this->abandoned_checkout_timeout_minutes = (int) $settings->get('abandoned_checkout_timeout_minutes');
        $this->management_notification_email = (string) $settings->get('management_notification_email');
    }

    public function save(): void
    {
        $this->validate();

        $settings = app(SettingsService::class);
        $settings->set('cop_per_point', $this->cop_per_point, 'decimal');
        $settings->set('min_points_percentage', $this->min_points_percentage, 'decimal');
        $settings->set('points_expiration_months', $this->points_expiration_months, 'integer');
        $settings->set('abandoned_checkout_timeout_minutes', $this->abandoned_checkout_timeout_minutes, 'integer');
        $settings->set('management_notification_email', $this->management_notification_email, 'string');

        $this->saved = true;
    }
};
?>

<form wire:submit="save" class="bg-white rounded-xl shadow p-6 space-y-4 max-w-xl">
    <h2 class="font-semibold">Configuración general</h2>

    @if ($saved)
        <p class="text-sm text-emerald-600">Guardado correctamente.</p>
    @endif

    <div>
        <label class="block text-sm font-medium">Tasa de cambio (COP por punto)</label>
        <input type="number" step="0.01" wire:model="cop_per_point" class="mt-1 w-full rounded border-gray-300">
        @error('cop_per_point') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="block text-sm font-medium">% mínimo en puntos para pago mixto</label>
        <input type="number" step="0.1" wire:model="min_points_percentage" class="mt-1 w-full rounded border-gray-300">
        @error('min_points_percentage') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="block text-sm font-medium">Vigencia de los puntos (meses)</label>
        <input type="number" wire:model="points_expiration_months" class="mt-1 w-full rounded border-gray-300">
        @error('points_expiration_months') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="block text-sm font-medium">Timeout de checkout abandonado (minutos)</label>
        <input type="number" wire:model="abandoned_checkout_timeout_minutes" class="mt-1 w-full rounded border-gray-300">
        @error('abandoned_checkout_timeout_minutes') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="block text-sm font-medium">Correo de gerencia (notificación de canjes)</label>
        <input type="email" wire:model="management_notification_email" class="mt-1 w-full rounded border-gray-300">
        @error('management_notification_email') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
    </div>

    <button type="submit" class="rounded bg-gray-900 text-white px-4 py-2 text-sm font-medium">Guardar</button>
</form>
