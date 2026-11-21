<?php

use App\Models\Reward;
use App\Models\RewardCategory;
use App\Models\RewardImage;
use App\Services\Media\ImageUploadService;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithFileUploads;

new class extends Component
{
    use WithFileUploads;

    public ?int $editingId = null;

    #[Validate('required|string|max:255')]
    public string $title = '';

    #[Validate('nullable|exists:reward_categories,id')]
    public ?int $category_id = null;

    #[Validate('nullable|string|max:255')]
    public string $short_description = '';

    #[Validate('nullable|string')]
    public string $body_html = '';

    #[Validate('required|integer|min:1')]
    public ?int $price_cop = null;

    #[Validate('nullable|integer|min:0')]
    public ?int $stock = null;

    public bool $is_active = true;

    public bool $is_featured = false;

    /** @var \Illuminate\Http\UploadedFile[] */
    #[Validate('nullable|array|max:5')]
    public array $newImages = [];

    #[Computed]
    public function rewards()
    {
        return Reward::with('category', 'images')->latest()->get();
    }

    #[Computed]
    public function categories()
    {
        return RewardCategory::orderBy('name')->get();
    }

    protected function rules(): array
    {
        return ['newImages.*' => 'image|mimes:jpg,jpeg,png,webp|max:4096'];
    }

    public function edit(int $id): void
    {
        $reward = Reward::findOrFail($id);

        $this->editingId = $reward->id;
        $this->title = $reward->title;
        $this->category_id = $reward->category_id;
        $this->short_description = (string) $reward->short_description;
        $this->body_html = (string) $reward->body_html;
        $this->price_cop = $reward->price_cop;
        $this->stock = $reward->stock;
        $this->is_active = $reward->is_active;
        $this->is_featured = $reward->is_featured;
        $this->newImages = [];
    }

    public function save(): void
    {
        $data = $this->validate();
        unset($data['newImages'], $data['newImages.*']);

        if ($data['body_html']) {
            $data['body_html'] = clean($data['body_html']);
        }

        if (! $this->editingId) {
            $slug = Str::slug($this->title);
            $data['slug'] = Reward::where('slug', $slug)->exists() ? $slug.'-'.Str::random(4) : $slug;
        }

        $reward = Reward::updateOrCreate(['id' => $this->editingId], $data);

        if ($this->newImages) {
            $uploader = app(ImageUploadService::class);

            foreach ($this->newImages as $index => $file) {
                RewardImage::create([
                    'reward_id' => $reward->id,
                    'path' => $uploader->store($file, 'rewards'),
                    'order' => $reward->images()->count() + $index,
                ]);
            }
        }

        $this->cancel();
        unset($this->rewards);
    }

    public function removeImage(int $imageId): void
    {
        $image = RewardImage::findOrFail($imageId);
        \Illuminate\Support\Facades\Storage::disk('public')->delete($image->path);
        $image->delete();
        unset($this->rewards);
    }

    public function delete(int $id): void
    {
        Reward::whereKey($id)->delete();
        unset($this->rewards);
    }

    public function cancel(): void
    {
        $this->reset(['editingId', 'title', 'category_id', 'short_description', 'body_html', 'price_cop', 'stock', 'is_active', 'is_featured', 'newImages']);
        $this->is_active = true;
    }
};
?>

<div class="space-y-6">
    <form wire:submit="save" class="bg-white rounded-brand shadow-sm ring-1 ring-black/5 p-6 space-y-4">
        <x-card-header :icon="$editingId ? 'o-pencil-square' : 'o-gift'">{{ $editingId ? 'Editar premio' : 'Nuevo premio' }}</x-card-header>

        <div>
            <label class="block text-sm font-medium">Título</label>
            <input type="text" wire:model="title" class="mt-1 w-full rounded px-3 py-2 border border-black/15 focus:border-azul focus:ring-1 focus:ring-azul focus:outline-none">
            @error('title') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="block text-sm font-medium">Categoría</label>
            <select wire:model="category_id" class="mt-1 w-full rounded px-3 py-2 border border-black/15 focus:border-azul focus:ring-1 focus:ring-azul focus:outline-none">
                <option value="">Sin categoría</option>
                @foreach ($this->categories as $category)
                    <option value="{{ $category->id }}">{{ $category->name }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="block text-sm font-medium">Descripción corta</label>
            <input type="text" wire:model="short_description" class="mt-1 w-full rounded px-3 py-2 border border-black/15 focus:border-azul focus:ring-1 focus:ring-azul focus:outline-none">
        </div>

        <div wire:ignore x-data="{ content: @entangle('body_html') }">
            <label class="block text-sm font-medium">Descripción detallada</label>
            <input id="trix-reward-{{ $editingId ?? 'new' }}" type="hidden" x-model="content">
            <trix-editor input="trix-reward-{{ $editingId ?? 'new' }}" x-on:trix-change="content = $event.target.value" class="mt-1 block"></trix-editor>
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium">Precio (COP)</label>
                <input type="number" wire:model="price_cop" class="mt-1 w-full rounded px-3 py-2 border border-black/15 focus:border-azul focus:ring-1 focus:ring-azul focus:outline-none">
                @error('price_cop') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                <p class="text-xs text-muted mt-1">≈ {{ number_format($price_cop ? intdiv($price_cop, max((int) setting('cop_per_point', 100), 1)) : 0) }} puntos a la tasa actual.</p>
            </div>
            <div>
                <label class="block text-sm font-medium">Stock (vacío = ilimitado)</label>
                <input type="number" wire:model="stock" class="mt-1 w-full rounded px-3 py-2 border border-black/15 focus:border-azul focus:ring-1 focus:ring-azul focus:outline-none">
            </div>
        </div>

        <div class="flex gap-6">
            <label class="flex items-center gap-2">
                <input type="checkbox" wire:model="is_active">
                <span class="text-sm">Activo</span>
            </label>
            <label class="flex items-center gap-2">
                <input type="checkbox" wire:model="is_featured">
                <span class="text-sm">Destacado</span>
            </label>
        </div>

        @if ($editingId && optional(Reward::find($editingId))->images->isNotEmpty())
            <div class="flex gap-2 flex-wrap">
                @foreach (Reward::find($editingId)->images as $image)
                    <div class="relative">
                        <img src="{{ Storage::url($image->path) }}" class="w-20 h-20 object-cover rounded">
                        <button type="button" wire:click="removeImage({{ $image->id }})" class="absolute -top-2 -right-2 bg-red-600 text-white rounded-full w-5 h-5 text-xs">×</button>
                    </div>
                @endforeach
            </div>
        @endif

        <div>
            <label class="block text-sm font-medium">Agregar imágenes</label>
            <input type="file" wire:model="newImages" multiple accept="image/*" class="mt-1 w-full text-sm">
            @error('newImages.*') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div class="flex gap-2">
            <x-submit-button target="save" loading-text="Guardando…" class="rounded-full bg-azul text-white px-5 py-2 text-sm font-medium border-2 border-transparent transition-all duration-500 hover:bg-white hover:text-azul hover:border-azul">
                {{ $editingId ? 'Guardar cambios' : 'Crear' }}
            </x-submit-button>
            @if ($editingId)
                <button type="button" wire:click="cancel" class="rounded px-4 py-2 text-sm text-muted">Cancelar</button>
            @endif
        </div>
    </form>

    <div class="bg-white rounded-brand shadow-sm ring-1 ring-black/5 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="text-left text-[11px] uppercase tracking-wider text-muted bg-[#F7F8FA]">
                    <tr>
                        <th class="px-5 py-3 font-semibold">Premio</th>
                        <th class="px-5 py-3 font-semibold">Categoría</th>
                        <th class="px-5 py-3 font-semibold">Precio</th>
                        <th class="px-5 py-3 font-semibold">Stock</th>
                        <th class="px-5 py-3 font-semibold">Estado</th>
                        <th class="px-5 py-3 font-semibold text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-black/5">
                    @foreach ($this->rewards as $reward)
                        <tr wire:key="reward-{{ $reward->id }}" class="hover:bg-[#FAFBFC] transition-colors">
                            <td class="px-5 py-3">
                                <div class="flex items-center gap-3">
                                    <div class="h-11 w-11 rounded-xl bg-[#EEF3F7] overflow-hidden shrink-0 flex items-center justify-center text-steel">
                                        @if ($reward->images->first())
                                            <img src="{{ Storage::url($reward->images->first()->path) }}" alt="" class="h-full w-full object-cover">
                                        @else
                                            @svg('heroicon-o-photo', 'h-4 w-4')
                                        @endif
                                    </div>
                                    <div class="min-w-0">
                                        <p class="font-medium text-ink truncate">{{ $reward->title }}</p>
                                        @if ($reward->is_featured)
                                            <span class="inline-flex items-center gap-1 mt-0.5 rounded-full bg-dermo/50 text-marino px-2 py-0.5 text-[11px] font-medium">
                                                @svg('heroicon-s-star', 'h-2.5 w-2.5')
                                                Destacado
                                            </span>
                                        @endif
                                    </div>
                                </div>
                            </td>
                            <td class="px-5 py-3 text-ink/80">{{ $reward->category?->name ?? '—' }}</td>
                            <td class="px-5 py-3 tabular-nums text-ink/80">${{ number_format($reward->price_cop, 0, ',', '.') }}</td>
                            <td class="px-5 py-3 tabular-nums">
                                @if (is_null($reward->stock))
                                    <span class="text-ink/80">Ilimitado</span>
                                @elseif ($reward->stock <= 5)
                                    <span class="font-semibold text-amber-700">{{ $reward->stock }}</span>
                                    <span class="ml-1 inline-flex items-center rounded-full bg-amber-50 text-amber-700 px-2 py-0.5 text-[11px] font-semibold">Bajo</span>
                                @else
                                    <span class="text-ink/80">{{ $reward->stock }}</span>
                                @endif
                            </td>
                            <td class="px-5 py-3">
                                @if ($reward->is_active)
                                    <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 text-emerald-700 px-2.5 py-1 text-xs font-medium"><span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>Activo</span>
                                @else
                                    <span class="inline-flex items-center gap-1.5 rounded-full bg-black/5 text-muted px-2.5 py-1 text-xs font-medium"><span class="h-1.5 w-1.5 rounded-full bg-steel"></span>Inactivo</span>
                                @endif
                            </td>
                            <td class="px-5 py-3">
                                <div class="flex items-center justify-end gap-1">
                                    <button wire:click="edit({{ $reward->id }})" class="rounded-lg px-3 py-1.5 text-sm font-medium text-azul hover:bg-azul/10 transition-colors">Editar</button>
                                    <button wire:click="delete({{ $reward->id }})" wire:confirm="¿Eliminar este premio?" class="rounded-lg px-3 py-1.5 text-sm font-medium text-red-600 hover:bg-red-50 transition-colors">Eliminar</button>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                    @if ($this->rewards->isEmpty())
                        <x-empty-state-row :colspan="6" icon="o-gift" message="Sin premios todavía." />
                    @endif
                </tbody>
            </table>
        </div>
    </div>
</div>
