<?php

use App\Models\Reward;
use App\Services\Checkout\InvalidCheckoutException;
use App\Services\Checkout\RedemptionService;
use App\Services\Checkout\RewardPointsCalculator;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    public Reward $reward;

    public int $pointsToUse = 0;

    public ?string $checkoutError = null;

    public function mount(Reward $reward): void
    {
        $this->reward = $reward;
        $this->pointsToUse = $this->limits->maxPoints;
    }

    #[Computed]
    public function limits(): RewardPointsCalculator
    {
        return RewardPointsCalculator::forPatient($this->reward, Auth::guard('patient')->user());
    }

    #[Computed]
    public function patientBalance(): int
    {
        return Auth::guard('patient')->user()->pointsBalance();
    }

    public function usePointsOnly(): void
    {
        $this->pointsToUse = $this->limits->totalPointsEquivalent;
    }

    public function checkout(): void
    {
        $this->checkoutError = null;

        try {
            $outcome = app(RedemptionService::class)->checkout(
                Auth::guard('patient')->user(),
                $this->reward,
                $this->pointsToUse
            );
        } catch (InvalidCheckoutException $e) {
            $this->checkoutError = $e->getMessage();

            return;
        }

        if ($outcome->requiresPayment()) {
            $this->redirect($outcome->paymentInitiation->url);

            return;
        }

        $this->redirectRoute('portal.checkout.success', ['code' => $outcome->redemption->code]);
    }
};
?>

<div class="space-y-6">
    <a href="{{ route('portal.rewards.index') }}" wire:navigate class="inline-flex items-center gap-1 text-sm font-medium text-muted hover:text-azul">
        @svg('heroicon-o-chevron-left', 'h-4 w-4')
        Volver a premios
    </a>

    <div class="aspect-[4/3] bg-[#EEF3F7] rounded-brand overflow-hidden flex items-center justify-center text-steel">
        @if ($reward->images->first())
            <img src="{{ Storage::url($reward->images->first()->path) }}" alt="{{ $reward->title }}" class="w-full h-full object-cover">
        @else
            @svg('heroicon-o-photo', 'h-12 w-12')
        @endif
    </div>

    <div class="space-y-2">
        <h1 class="text-2xl font-semibold leading-tight text-marino">{{ $reward->title }}</h1>
        @if ($reward->short_description)
            <p class="text-muted text-sm leading-relaxed">{{ $reward->short_description }}</p>
        @endif
    </div>

    <div class="prose prose-sm max-w-none text-ink/80">
        {!! $reward->body_html !!}
    </div>

    <section class="bg-white rounded-brand shadow-sm ring-1 ring-black/5 p-5 space-y-5">
        <div class="flex items-end justify-between gap-3">
            <div>
                <p class="text-xs uppercase tracking-wider text-muted font-semibold">Precio</p>
                <p class="text-3xl font-semibold text-azul">{{ number_format($this->limits->totalPointsEquivalent) }} <span class="text-base font-medium">pts</span></p>
            </div>
            <p class="text-xs text-muted text-right leading-relaxed">
                Tu saldo<br>
                <span class="text-sm font-semibold text-ink">{{ number_format($this->patientBalance) }} pts</span>
                @if ($this->limits->maxPoints > 0)
                    <br><span class="text-emerald-600 font-medium">Te quedarían {{ number_format(max(0, $this->patientBalance - $pointsToUse)) }}</span>
                @endif
            </p>
        </div>

        @if ($this->limits->maxPoints <= 0)
            <div class="flex gap-2 rounded-xl bg-red-50 text-red-700 p-3 text-sm">
                @svg('heroicon-o-exclamation-triangle', 'h-4 w-4 shrink-0 mt-0.5')
                <p>Aún no tienes suficientes puntos para este premio.</p>
            </div>
        @else
            <div x-data="{
                mode: '{{ $pointsToUse >= $this->limits->totalPointsEquivalent ? 'points' : 'mix' }}',
                points: {{ $pointsToUse }},
                min: {{ $this->limits->minPoints }},
                max: {{ $this->limits->maxPoints }},
                total: {{ $this->limits->totalPointsEquivalent }},
                copPerPoint: {{ $this->limits->copPerPoint }},
                get money() { return Math.max(0, (this.total - this.points) * this.copPerPoint) }
            }" class="space-y-4">
                <p class="text-sm font-semibold text-marino">¿Cómo quieres pagarlo?</p>

                <div class="grid grid-cols-2 gap-2" role="radiogroup" aria-label="Forma de pago">
                    <button type="button" role="radio" :aria-checked="mode === 'points'"
                            @click="mode = 'points'; points = total; $wire.set('pointsToUse', total)"
                            :class="mode === 'points' ? 'border-azul bg-azul/5 text-azul' : 'border-black/10 text-ink'"
                            class="rounded-xl border-2 p-3 text-left transition-colors disabled:opacity-40" {{ $this->limits->canAffordPointsOnly($this->patientBalance) ? '' : 'disabled' }}>
                        <span class="block text-sm font-semibold">Solo puntos</span>
                        <span class="block text-xs text-muted">Sin pagos adicionales</span>
                    </button>
                    <button type="button" role="radio" :aria-checked="mode === 'mix'"
                            @click="mode = 'mix'; points = max; $wire.set('pointsToUse', max)"
                            :class="mode === 'mix' ? 'border-azul bg-azul/5 text-azul' : 'border-black/10 text-ink'"
                            class="rounded-xl border-2 p-3 text-left transition-colors">
                        <span class="block text-sm font-semibold">Puntos + dinero</span>
                        <span class="block text-xs text-muted">Usa lo que tienes</span>
                    </button>
                </div>

                <div x-show="mode === 'mix'" x-cloak class="space-y-2">
                    <input type="range" x-model.number="points" :min="min" :max="max"
                           @change="$wire.set('pointsToUse', points)" aria-label="Puntos a usar"
                           class="w-full accent-azul">
                    <div class="flex justify-between text-sm">
                        <span><span class="font-semibold" x-text="points.toLocaleString('es-CO')"></span> puntos</span>
                        <span>$<span class="font-semibold" x-text="money.toLocaleString('es-CO')"></span> COP</span>
                    </div>
                </div>
            </div>
        @endif

        @error('checkout') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
        @if ($checkoutError)
            <div class="flex gap-2 rounded-xl bg-red-50 text-red-700 p-3 text-sm">
                @svg('heroicon-o-exclamation-triangle', 'h-4 w-4 shrink-0 mt-0.5')
                <p>{{ $checkoutError }}</p>
            </div>
        @endif

        <button wire:click="checkout" wire:loading.attr="disabled" wire:target="checkout" {{ $this->limits->maxPoints <= 0 ? 'disabled' : '' }}
                class="w-full rounded-brand bg-azul text-white py-3 font-semibold transition-colors hover:bg-azul-dark disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-2">
            <svg wire:loading wire:target="checkout" class="animate-spin h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
            </svg>
            <span wire:loading.remove wire:target="checkout">@svg('heroicon-o-gift', 'h-4 w-4') Canjear</span>
            <span wire:loading wire:target="checkout">Procesando…</span>
        </button>
        <p class="text-[11px] text-center text-muted">Tu canje se confirma por correo.</p>
    </section>

    <div class="flex gap-2.5 bg-white rounded-brand ring-1 ring-black/5 p-4">
        @svg('heroicon-o-information-circle', 'h-4 w-4 text-azul shrink-0 mt-0.5')
        <p class="text-xs text-muted leading-relaxed">
            Al confirmar te enviaremos un correo con el código del bono. Consulta los
            <a href="{{ route('portal.legal') }}" wire:navigate class="text-azul underline underline-offset-2">términos y condiciones</a>.
        </p>
    </div>
</div>
