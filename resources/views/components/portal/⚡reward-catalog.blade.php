<?php

use App\Models\Reward;
use App\Models\RewardCategory;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;

new class extends Component
{
    #[Url]
    public string $search = '';

    #[Url]
    public ?int $categoryId = null;

    #[Url]
    public string $sort = 'relevance';

    #[Computed]
    public function balance(): int
    {
        return Auth::guard('patient')->user()?->pointsBalance() ?? 0;
    }

    #[Computed]
    public function categories()
    {
        return RewardCategory::where('is_active', true)->whereNull('parent_id')->orderBy('order')->get();
    }

    #[Computed]
    public function rewards()
    {
        return Reward::query()
            ->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('valid_from')->orWhere('valid_from', '<=', now()))
            ->where(fn ($q) => $q->whereNull('valid_until')->orWhere('valid_until', '>=', now()))
            ->when($this->search, fn ($q) => $q->where(function ($q) {
                $q->where('title', 'like', "%{$this->search}%")
                    ->orWhere('short_description', 'like', "%{$this->search}%");
            }))
            ->when($this->categoryId, fn ($q) => $q->where('category_id', $this->categoryId))
            ->when($this->sort === 'points_asc', fn ($q) => $q->orderBy('price_cop'))
            ->when($this->sort === 'points_desc', fn ($q) => $q->orderByDesc('price_cop'))
            ->when($this->sort === 'newest', fn ($q) => $q->latest())
            ->when($this->sort === 'relevance', fn ($q) => $q->orderByDesc('is_featured')->latest())
            ->with('images')
            ->paginate(12);
    }
};
?>

<div class="space-y-5">
    <div>
        <h1 class="text-2xl font-semibold text-marino">Premios</h1>
        <p class="text-sm text-muted mt-1">Tienes <span class="font-semibold text-azul">{{ number_format($this->balance) }} pts</span> para canjear.</p>
    </div>

    @php $catalogTargets = 'search,categoryId,sort'; @endphp

    <div class="relative">
        <span wire:loading.remove wire:target="{{ $catalogTargets }}" class="absolute left-4 top-1/2 -translate-y-1/2 pointer-events-none">
            @svg('heroicon-o-magnifying-glass', 'h-4 w-4 text-steel')
        </span>
        <svg wire:loading wire:target="{{ $catalogTargets }}" class="animate-spin h-4 w-4 text-azul absolute left-4 top-1/2 -translate-y-1/2 pointer-events-none" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
        </svg>
        <input type="text" wire:model.live.debounce.400ms="search" placeholder="Buscar premios o procedimientos" aria-label="Buscar premios" class="w-full rounded-2xl pl-11 pr-4 h-12 bg-white border border-black/10 focus:border-azul focus:ring-2 focus:ring-azul/20 focus:outline-none text-sm">
    </div>

    <div class="flex gap-2 overflow-x-auto pb-1 -mx-5 px-5 text-sm [scrollbar-width:none]">
        <button wire:click="$set('categoryId', null)" class="px-4 py-2 rounded-full whitespace-nowrap font-medium transition-colors {{ ! $categoryId ? 'bg-azul text-white' : 'bg-white text-ink border border-black/10 hover:border-azul' }}">Todos</button>
        @foreach ($this->categories as $category)
            <button wire:click="$set('categoryId', {{ $category->id }})" class="px-4 py-2 rounded-full whitespace-nowrap font-medium transition-colors {{ $categoryId === $category->id ? 'bg-azul text-white' : 'bg-white text-ink border border-black/10 hover:border-azul' }}">
                {{ $category->name }}
            </button>
        @endforeach
    </div>

    <div class="flex items-center justify-end gap-3 text-sm">
        <select wire:model.live="sort" aria-label="Ordenar premios" class="rounded-xl pl-3 pr-8 py-2 bg-white border border-black/10 focus:border-azul focus:ring-2 focus:ring-azul/20 focus:outline-none text-sm">
            <option value="relevance">Destacados</option>
            <option value="points_asc">Menos puntos</option>
            <option value="points_desc">Más puntos</option>
            <option value="newest">Más nuevos</option>
        </select>
    </div>

    @php
        $hero = (! $categoryId && ! $search) ? $this->rewards->firstWhere('is_featured', true) : null;
    @endphp

    <div wire:loading.class="opacity-40" wire:target="{{ $catalogTargets }}" class="space-y-5 transition-opacity">
    @if ($hero)
        @php
            $heroPoints = $hero->pointsEquivalent();
            $heroMissing = max(0, $heroPoints - $this->balance);
        @endphp
        <a href="{{ route('portal.rewards.show', $hero) }}" wire:navigate class="flex gap-3 items-center bg-azul text-white rounded-brand p-3">
            <div class="h-[84px] w-[84px] rounded-2xl overflow-hidden shrink-0 bg-teal flex items-center justify-center">
                @if ($hero->images->first())
                    <img src="{{ Storage::url($hero->images->first()->path) }}" alt="" class="w-full h-full object-cover">
                @else
                    @svg('heroicon-o-photo', 'h-8 w-8 text-white/70')
                @endif
            </div>
            <div class="flex-1 min-w-0">
                <span class="inline-flex items-center rounded-full bg-dermo text-marino px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider">Destacado</span>
                <p class="mt-1.5 text-[15px] font-semibold leading-tight">{{ $hero->title }}</p>
                <p class="mt-1 text-sm text-white/90">{{ number_format($heroPoints) }} pts · {{ $heroMissing === 0 ? 'Ya lo puedes canjear' : 'Faltan '.number_format($heroMissing) }}</p>
            </div>
        </a>
    @endif

    <div class="grid grid-cols-2 gap-3">
        @foreach ($this->rewards->reject(fn ($r) => $hero && $r->id === $hero->id) as $reward)
            @php
                $points = $reward->pointsEquivalent();
                $missing = max(0, $points - $this->balance);
            @endphp
            <a href="{{ route('portal.rewards.show', $reward) }}" wire:navigate class="group bg-white rounded-brand overflow-hidden flex flex-col shadow-sm ring-1 ring-black/5 transition-shadow hover:shadow-md">
                <div class="aspect-[4/3] bg-[#EEF3F7] relative flex items-center justify-center text-steel">
                    @if ($reward->images->first())
                        <img src="{{ Storage::url($reward->images->first()->path) }}" alt="" class="w-full h-full object-cover transition-transform duration-300 group-hover:scale-105">
                    @else
                        @svg('heroicon-o-photo', 'h-8 w-8')
                    @endif
                    @if ($reward->is_featured)
                        <span class="absolute top-2 left-2 inline-flex items-center gap-1 rounded-full bg-dermo text-marino px-2 py-0.5 text-[11px] font-semibold">
                            @svg('heroicon-s-star', 'h-2.5 w-2.5')
                            Destacado
                        </span>
                    @endif
                </div>
                <div class="p-3 flex flex-col gap-2 flex-1">
                    <p class="text-sm font-semibold leading-snug line-clamp-2 text-ink">{{ $reward->title }}</p>
                    <div class="mt-auto flex items-center justify-between gap-2">
                        <span class="text-sm font-semibold text-azul">{{ number_format($points) }} pts</span>
                        @if ($missing === 0)
                            <span class="text-[11px] font-semibold text-emerald-700 bg-emerald-50 rounded-full px-2 py-0.5">Canjeable</span>
                        @else
                            <span class="text-[11px] font-medium text-muted bg-black/5 rounded-full px-2 py-0.5">Faltan {{ number_format($missing) }}</span>
                        @endif
                    </div>
                </div>
            </a>
        @endforeach
    </div>

    @if ($this->rewards->isEmpty())
        <div class="flex flex-col items-center gap-2 text-muted py-12">
            @svg('heroicon-o-magnifying-glass', 'h-8 w-8 text-steel')
            <p class="text-center text-sm">No encontramos premios con esos filtros.</p>
            <button wire:click="$set('search', '')" class="text-sm font-medium text-azul underline">Limpiar búsqueda</button>
        </div>
    @endif
    </div>

    <div class="pt-2">
        {{ $this->rewards->links() }}
    </div>
</div>
