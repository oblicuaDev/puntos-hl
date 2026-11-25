<?php

use App\Services\SettingsService;
use Livewire\Attributes\Validate;
use Livewire\Component;

new class extends Component
{
    #[Validate('required|numeric|min:1')]
    public float $accrual_cop_per_point;

    #[Validate('required|numeric|min:0.01')]
    public float $redemption_points_per_100_cop;

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

        $this->accrual_cop_per_point = (float) $settings->get('accrual_cop_per_point');
        $this->redemption_points_per_100_cop = (float) $settings->get('redemption_points_per_100_cop');
        $this->min_points_percentage = (float) $settings->get('min_points_percentage');
        $this->points_expiration_months = (int) $settings->get('points_expiration_months');
        $this->abandoned_checkout_timeout_minutes = (int) $settings->get('abandoned_checkout_timeout_minutes');
        $this->management_notification_email = (string) $settings->get('management_notification_email');
    }

    public function save(): void
    {
        $this->validate();

        $settings = app(SettingsService::class);
        $settings->set('accrual_cop_per_point', $this->accrual_cop_per_point, 'decimal');
        $settings->set('redemption_points_per_100_cop', $this->redemption_points_per_100_cop, 'decimal');
        $settings->set('min_points_percentage', $this->min_points_percentage, 'decimal');
        $settings->set('points_expiration_months', $this->points_expiration_months, 'integer');
        $settings->set('abandoned_checkout_timeout_minutes', $this->abandoned_checkout_timeout_minutes, 'integer');
        $settings->set('management_notification_email', $this->management_notification_email, 'string');

        $this->saved = true;
    }
};
?>

<form wire:submit="save" class="bg-white rounded-brand shadow-sm ring-1 ring-black/5 p-6 space-y-4 max-w-xl">
    <x-card-header icon="o-cog-6-tooth">Configuración general</x-card-header>

    @if ($saved)
        <p class="text-sm text-emerald-600 flex items-center gap-1.5">
            @svg('heroicon-s-check-circle', 'h-4 w-4')
            Guardado correctamente.
        </p>
    @endif

    <div>
        <label for="accrual_cop_per_point" class="flex items-center gap-1.5 text-sm font-medium">@svg('heroicon-o-banknotes', 'h-4 w-4 text-azul') Acumulación: COP pagados por cada punto ganado</label>
        <input id="accrual_cop_per_point" type="number" step="1" wire:model="accrual_cop_per_point" class="mt-1.5 w-full rounded-xl px-3 py-2.5 border border-black/10 bg-white focus:border-azul focus:ring-2 focus:ring-azul/20 focus:outline-none">
        <p class="text-xs text-muted mt-1">Ej.: 1000 = el paciente gana 1 punto por cada $1.000 COP pagados. Se aplica a las ventas nuevas.</p>
        @error('accrual_cop_per_point') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
    </div>

    <div>
        <label for="redemption_points_per_100_cop" class="flex items-center gap-1.5 text-sm font-medium">@svg('heroicon-o-gift', 'h-4 w-4 text-azul') Redención: puntos por cada $100 COP del procedimiento</label>
        <input id="redemption_points_per_100_cop" type="number" step="0.01" wire:model="redemption_points_per_100_cop" class="mt-1.5 w-full rounded-xl px-3 py-2.5 border border-black/10 bg-white focus:border-azul focus:ring-2 focus:ring-azul/20 focus:outline-none">
        <p class="text-xs text-muted mt-1">Ej.: 3 = un procedimiento de $100.000 cuesta 3.000 puntos. Cambia los precios en puntos del catálogo; no altera canjes ya hechos.</p>
        @error('redemption_points_per_100_cop') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="flex items-center gap-1.5 text-sm font-medium">@svg('heroicon-o-receipt-percent', 'h-4 w-4 text-azul') % mínimo en puntos para pago mixto</label>
        <input type="number" step="0.1" wire:model="min_points_percentage" class="mt-1.5 w-full rounded-xl px-3 py-2.5 border border-black/10 bg-white focus:border-azul focus:ring-2 focus:ring-azul/20 focus:outline-none">
        @error('min_points_percentage') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="flex items-center gap-1.5 text-sm font-medium">@svg('heroicon-o-clock', 'h-4 w-4 text-azul') Vigencia de los puntos (meses)</label>
        <input type="number" wire:model="points_expiration_months" class="mt-1.5 w-full rounded-xl px-3 py-2.5 border border-black/10 bg-white focus:border-azul focus:ring-2 focus:ring-azul/20 focus:outline-none">
        @error('points_expiration_months') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="flex items-center gap-1.5 text-sm font-medium">@svg('heroicon-o-arrow-path', 'h-4 w-4 text-azul') Timeout de checkout abandonado (minutos)</label>
        <input type="number" wire:model="abandoned_checkout_timeout_minutes" class="mt-1.5 w-full rounded-xl px-3 py-2.5 border border-black/10 bg-white focus:border-azul focus:ring-2 focus:ring-azul/20 focus:outline-none">
        @error('abandoned_checkout_timeout_minutes') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="flex items-center gap-1.5 text-sm font-medium">@svg('heroicon-o-envelope', 'h-4 w-4 text-azul') Correo de gerencia (notificación de canjes)</label>
        <input type="email" wire:model="management_notification_email" class="mt-1.5 w-full rounded-xl px-3 py-2.5 border border-black/10 bg-white focus:border-azul focus:ring-2 focus:ring-azul/20 focus:outline-none">
        @error('management_notification_email') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
    </div>

    <x-submit-button target="save" loading-text="Guardando…" class="rounded-xl bg-azul text-white px-5 py-2.5 text-sm font-semibold transition-colors hover:bg-azul-dark">Guardar</x-submit-button>
</form>
