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

<div class="space-y-4">
    <div class="aspect-square bg-gray-100 rounded-xl overflow-hidden">
        @if ($reward->images->first())
            <img src="{{ Storage::url($reward->images->first()->path) }}" class="w-full h-full object-cover">
        @endif
    </div>

    <h1 class="text-xl font-semibold">{{ $reward->title }}</h1>
    <p class="text-muted text-sm">{{ $reward->short_description }}</p>

    <div class="prose prose-sm max-w-none">
        {!! $reward->body_html !!}
    </div>

    <div class="bg-white rounded-xl shadow p-4 space-y-4 sticky bottom-16">
        <p class="text-sm text-muted">Tu saldo: {{ number_format($this->patientBalance) }} puntos</p>

        @if ($this->limits->maxPoints <= 0)
            <p class="text-sm text-red-600">No tienes suficientes puntos para este premio.</p>
        @else
            <div x-data="{
                points: {{ $pointsToUse }},
                min: {{ $this->limits->minPoints }},
                max: {{ $this->limits->maxPoints }},
                total: {{ $this->limits->totalPointsEquivalent }},
                copPerPoint: {{ $this->limits->copPerPoint }},
                get money() { return Math.max(0, (this.total - this.points) * this.copPerPoint) }
            }">
                <input type="range" x-model.number="points" :min="min" :max="max"
                       @change="$wire.set('pointsToUse', points)" class="w-full">

                <div class="flex justify-between text-sm mt-2">
                    <span><span x-text="points.toLocaleString('es-CO')"></span> puntos</span>
                    <span>$<span x-text="money.toLocaleString('es-CO')"></span> COP</span>
                </div>
            </div>

            @if ($this->limits->canAffordPointsOnly($this->patientBalance))
                <button wire:click="usePointsOnly" type="button" class="text-sm underline text-muted">
                    Pagar 100% con puntos
                </button>
            @endif
        @endif

        @error('checkout') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
        @if ($checkoutError)
            <p class="text-sm text-red-600">{{ $checkoutError }}</p>
        @endif

        <button wire:click="checkout" {{ $this->limits->maxPoints <= 0 ? 'disabled' : '' }}
                class="w-full rounded-brand bg-azul text-white py-2.5 font-medium border-2 border-transparent transition-all duration-500 hover:bg-white hover:text-azul hover:border-azul disabled:opacity-50 disabled:hover:bg-azul disabled:hover:text-white disabled:hover:border-transparent">
            Canjear
        </button>
    </div>
</div>
