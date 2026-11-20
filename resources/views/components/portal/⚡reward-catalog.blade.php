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
    public bool $onlyAffordable = false;

    #[Url]
    public string $sort = 'relevance';

    #[Computed]
    public function categories()
    {
        return RewardCategory::where('is_active', true)->whereNull('parent_id')->orderBy('order')->get();
    }

    #[Computed]
    public function rewards()
    {
        $copPerPoint = (float) setting('cop_per_point', 100);
        $balance = Auth::guard('patient')->user()?->pointsBalance() ?? 0;

        return Reward::query()
            ->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('valid_from')->orWhere('valid_from', '<=', now()))
            ->where(fn ($q) => $q->whereNull('valid_until')->orWhere('valid_until', '>=', now()))
            ->when($this->search, fn ($q) => $q->where(function ($q) {
                $q->where('title', 'like', "%{$this->search}%")
                    ->orWhere('short_description', 'like', "%{$this->search}%");
            }))
            ->when($this->categoryId, fn ($q) => $q->where('category_id', $this->categoryId))
            ->when($this->onlyAffordable, fn ($q) => $q->where('price_cop', '<=', $balance * $copPerPoint))
            ->when($this->sort === 'points_asc', fn ($q) => $q->orderBy('price_cop'))
            ->when($this->sort === 'points_desc', fn ($q) => $q->orderByDesc('price_cop'))
            ->when($this->sort === 'newest', fn ($q) => $q->latest())
            ->when($this->sort === 'relevance', fn ($q) => $q->orderByDesc('is_featured')->latest())
            ->with('images')
            ->paginate(12);
    }
};
?>

<div class="space-y-4">
    <div class="flex gap-2">
        <input type="text" wire:model.live.debounce.400ms="search" placeholder="Buscar premios..." class="flex-1 rounded px-3 py-2 border border-black/15 focus:border-azul focus:ring-1 focus:ring-azul focus:outline-none text-sm">
    </div>

    <div class="flex gap-2 overflow-x-auto pb-1 text-sm">
        <button wire:click="$set('categoryId', null)" class="px-3 py-1.5 rounded-full whitespace-nowrap transition-colors {{ ! $categoryId ? 'bg-azul text-white' : 'bg-black/5 text-ink hover:bg-black/10' }}">Todas</button>
        @foreach ($this->categories as $category)
            <button wire:click="$set('categoryId', {{ $category->id }})" class="px-3 py-1.5 rounded-full whitespace-nowrap transition-colors {{ $categoryId === $category->id ? 'bg-azul text-white' : 'bg-black/5 text-ink hover:bg-black/10' }}">
                {{ $category->name }}
            </button>
        @endforeach
    </div>

    <div class="flex items-center justify-between text-sm">
        <label class="flex items-center gap-2">
            <input type="checkbox" wire:model.live="onlyAffordable">
            Solo con mis puntos
        </label>
        <select wire:model.live="sort" class="rounded px-3 py-2 border border-black/15 focus:border-azul focus:ring-1 focus:ring-azul focus:outline-none text-sm">
            <option value="relevance">Relevancia</option>
            <option value="points_asc">Menor a mayor puntos</option>
            <option value="points_desc">Mayor a menor puntos</option>
            <option value="newest">Más nuevos</option>
        </select>
    </div>

    <div class="grid grid-cols-2 gap-3">
        @foreach ($this->rewards as $reward)
            <a href="{{ route('portal.rewards.show', $reward) }}" class="bg-white rounded-xl shadow overflow-hidden">
                <div class="aspect-square bg-gray-100">
                    @if ($reward->images->first())
                        <img src="{{ Storage::url($reward->images->first()->path) }}" class="w-full h-full object-cover">
                    @endif
                </div>
                <div class="p-3">
                    <p class="text-sm font-medium line-clamp-2">{{ $reward->title }}</p>
                    <p class="text-xs text-muted mt-1">{{ number_format($reward->pointsEquivalent()) }} pts</p>
                </div>
            </a>
        @endforeach
    </div>

    @if ($this->rewards->isEmpty())
        <p class="text-center text-muted py-10">No encontramos premios con esos filtros.</p>
    @endif

    <div class="pt-4">
        {{ $this->rewards->links() }}
    </div>
</div>
