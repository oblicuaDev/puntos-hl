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
    <form wire:submit="save" class="bg-white rounded-xl shadow p-6 space-y-4 max-w-2xl">
        <h2 class="font-semibold">{{ $editingId ? 'Editar premio' : 'Nuevo premio' }}</h2>

        <div>
            <label class="block text-sm font-medium">Título</label>
            <input type="text" wire:model="title" class="mt-1 w-full rounded border-gray-300">
            @error('title') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="block text-sm font-medium">Categoría</label>
            <select wire:model="category_id" class="mt-1 w-full rounded border-gray-300">
                <option value="">Sin categoría</option>
                @foreach ($this->categories as $category)
                    <option value="{{ $category->id }}">{{ $category->name }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="block text-sm font-medium">Descripción corta</label>
            <input type="text" wire:model="short_description" class="mt-1 w-full rounded border-gray-300">
        </div>

        <div wire:ignore x-data="{ content: @entangle('body_html') }">
            <label class="block text-sm font-medium">Descripción detallada</label>
            <input id="trix-reward-{{ $editingId ?? 'new' }}" type="hidden" x-model="content">
            <trix-editor input="trix-reward-{{ $editingId ?? 'new' }}" x-on:trix-change="content = $event.target.value" class="mt-1 block"></trix-editor>
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium">Precio (COP)</label>
                <input type="number" wire:model="price_cop" class="mt-1 w-full rounded border-gray-300">
                @error('price_cop') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                <p class="text-xs text-gray-500 mt-1">≈ {{ number_format($price_cop ? intdiv($price_cop, max((int) setting('cop_per_point', 100), 1)) : 0) }} puntos a la tasa actual.</p>
            </div>
            <div>
                <label class="block text-sm font-medium">Stock (vacío = ilimitado)</label>
                <input type="number" wire:model="stock" class="mt-1 w-full rounded border-gray-300">
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
            <button type="submit" class="rounded bg-gray-900 text-white px-4 py-2 text-sm font-medium">
                {{ $editingId ? 'Guardar cambios' : 'Crear' }}
            </button>
            @if ($editingId)
                <button type="button" wire:click="cancel" class="rounded px-4 py-2 text-sm text-gray-500">Cancelar</button>
            @endif
        </div>
    </form>

    <table class="w-full bg-white rounded-xl shadow overflow-hidden text-sm">
        <thead class="bg-gray-50 text-left">
            <tr>
                <th class="p-3">Título</th>
                <th class="p-3">Categoría</th>
                <th class="p-3">Precio</th>
                <th class="p-3">Activo</th>
                <th class="p-3"></th>
            </tr>
        </thead>
        <tbody>
            @foreach ($this->rewards as $reward)
                <tr wire:key="reward-{{ $reward->id }}" class="border-t">
                    <td class="p-3">{{ $reward->title }}</td>
                    <td class="p-3">{{ $reward->category?->name ?? '—' }}</td>
                    <td class="p-3">${{ number_format($reward->price_cop, 0, ',', '.') }}</td>
                    <td class="p-3">{{ $reward->is_active ? 'Sí' : 'No' }}</td>
                    <td class="p-3 text-right space-x-2">
                        <button wire:click="edit({{ $reward->id }})" class="text-blue-600">Editar</button>
                        <button wire:click="delete({{ $reward->id }})" wire:confirm="¿Eliminar este premio?" class="text-red-600">Eliminar</button>
                    </td>
                </tr>
            @endforeach
            @if ($this->rewards->isEmpty())
                <tr><td colspan="5" class="p-3 text-gray-500">Sin premios todavía.</td></tr>
            @endif
        </tbody>
    </table>
</div>
