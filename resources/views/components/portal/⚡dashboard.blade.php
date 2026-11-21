<?php

use App\Models\PointBatch;
use App\Models\Reward;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    #[Computed]
    public function patient()
    {
        return Auth::guard('patient')->user();
    }

    #[Computed]
    public function balance(): int
    {
        return $this->patient->pointsBalance();
    }

    #[Computed]
    public function nextReward(): ?Reward
    {
        $copPerPoint = (float) setting('cop_per_point', 100);

        return Reward::query()
            ->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('valid_from')->orWhere('valid_from', '<=', now()))
            ->where(fn ($q) => $q->whereNull('valid_until')->orWhere('valid_until', '>=', now()))
            ->where('price_cop', '>', $this->balance * $copPerPoint)
            ->orderBy('price_cop')
            ->first();
    }

    #[Computed]
    public function expiringSoon()
    {
        return PointBatch::where('patient_id', $this->patient->id)
            ->activeAndValid()
            ->where('expires_at', '<=', now()->addDays(60))
            ->orderBy('expires_at')
            ->get();
    }

    #[Computed]
    public function movements()
    {
        return $this->patient->pointMovements()->latest('occurred_at')->limit(30)->get();
    }
};
?>

<div class="space-y-5">
    @php
        $initials = collect([$this->patient->first_name, $this->patient->last_name])->filter()->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->implode('');
    @endphp
    <div class="flex items-center justify-between">
        <div>
            <p class="text-sm text-muted">Hola, {{ $this->patient->first_name }}</p>
            <h1 class="text-xl font-semibold text-marino mt-0.5">Mi cuenta</h1>
        </div>
        <span class="h-10 w-10 rounded-full bg-azul text-white text-sm font-semibold flex items-center justify-center shrink-0">{{ $initials }}</span>
    </div>

    <section class="bg-azul text-white rounded-brand p-5 space-y-5">
        <div class="flex items-start justify-between gap-3">
            <div>
                <p class="text-sm opacity-80">Tu saldo disponible</p>
                <p class="text-4xl font-semibold tracking-tight mt-1">{{ number_format($this->balance) }} <span class="text-base font-medium opacity-90">pts</span></p>
            </div>
            <span class="inline-flex items-center justify-center h-11 w-11 rounded-full bg-white/15 shrink-0">
                @svg('heroicon-s-star', 'h-5 w-5 text-dermo')
            </span>
        </div>

        @if ($this->nextReward)
            @php
                $needed = $this->nextReward->pointsEquivalent();
                $progress = $needed > 0 ? min(100, (int) round($this->balance / $needed * 100)) : 100;
            @endphp
            <div class="space-y-2">
                <div class="flex justify-between text-xs opacity-90">
                    <span class="truncate pr-2">Siguiente: {{ $this->nextReward->title }}</span>
                    <span class="shrink-0">{{ number_format(max(0, $needed - $this->balance)) }} pts por ganar</span>
                </div>
                <div class="h-2 rounded-full bg-white/20 overflow-hidden" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $progress }}">
                    <div class="h-full rounded-full bg-teal" style="width: {{ $progress }}%"></div>
                </div>
            </div>
        @endif

        <a href="{{ route('portal.rewards.index') }}" wire:navigate class="flex items-center justify-center gap-2 w-full rounded-brand bg-white text-azul py-2.5 font-semibold transition-colors hover:bg-dermo">
            @svg('heroicon-o-gift', 'h-4 w-4')
            Ver premios
        </a>
    </section>

    @if ($this->expiringSoon->isNotEmpty())
        <section class="bg-white rounded-brand border border-dermo p-4 flex gap-3">
            <span class="inline-flex items-center justify-center h-9 w-9 rounded-full bg-dermo/60 text-marino shrink-0">
                @svg('heroicon-o-clock', 'h-4 w-4')
            </span>
            <div class="space-y-1">
                <p class="text-sm font-semibold text-marino">Puntos por vencer</p>
                <ul class="text-sm text-muted space-y-0.5">
                    @foreach ($this->expiringSoon as $batch)
                        <li>{{ number_format($batch->points_remaining) }} pts vencen el {{ $batch->expires_at->format('d/m/Y') }}</li>
                    @endforeach
                </ul>
            </div>
        </section>
    @endif

    <section class="space-y-3">
        <h2 class="text-base font-semibold text-marino">Movimientos</h2>

        @forelse ($this->movements->groupBy(fn ($m) => $m->occurred_at->format('m/Y')) as $period => $items)
            <p class="text-[11px] font-semibold uppercase tracking-wider text-muted pt-1">{{ $period }}</p>
            <ul class="bg-white rounded-brand divide-y divide-black/5 px-4">
                @foreach ($items as $movement)
                    <li class="py-3 flex items-center gap-3">
                        <span class="inline-flex items-center justify-center h-9 w-9 rounded-xl shrink-0 {{ $movement->points >= 0 ? 'bg-emerald-50 text-emerald-600' : 'bg-red-50 text-red-600' }}">
                            {{ svg($movement->points >= 0 ? 'heroicon-o-arrow-up' : 'heroicon-o-arrow-down', 'h-4 w-4') }}
                        </span>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm truncate">{{ $movement->description }}</p>
                            <p class="text-xs text-muted">{{ $movement->occurred_at->format('d/m/Y H:i') }}</p>
                        </div>
                        <span class="text-sm font-semibold {{ $movement->points >= 0 ? 'text-emerald-600' : 'text-red-600' }}">
                            {{ $movement->points >= 0 ? '+' : '' }}{{ number_format($movement->points) }}
                        </span>
                    </li>
                @endforeach
            </ul>
        @empty
            <div class="bg-white rounded-brand py-10 flex flex-col items-center gap-2 text-muted">
                @svg('heroicon-o-inbox', 'h-8 w-8 text-steel')
                <p class="text-sm">Aún no tienes movimientos.</p>
                <p class="text-xs">Tus puntos aparecerán aquí después de tu próxima visita.</p>
            </div>
        @endforelse
    </section>
</div>
