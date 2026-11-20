<?php

use App\Models\Banner;
use App\Services\Media\ImageUploadService;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithFileUploads;

new class extends Component
{
    use WithFileUploads;

    public ?int $editingId = null;

    #[Validate('nullable|string|max:255')]
    public string $title = '';

    #[Validate('nullable|url')]
    public string $link_url = '';

    #[Validate('required|integer|min:0')]
    public int $order = 0;

    #[Validate('nullable|date')]
    public ?string $starts_at = null;

    #[Validate('nullable|date')]
    public ?string $ends_at = null;

    public bool $is_active = true;

    public $imageDesktop = null;

    public $imageMobile = null;

    public ?string $existingImagePath = null;

    public ?string $existingImageMobilePath = null;

    protected function rules(): array
    {
        return [
            'imageDesktop' => ($this->editingId ? 'nullable' : 'required').'|image|mimes:jpg,jpeg,png,webp|max:4096',
            'imageMobile' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:4096',
        ];
    }

    #[Computed]
    public function banners()
    {
        return Banner::orderBy('order')->get();
    }

    public function edit(int $id): void
    {
        $banner = Banner::findOrFail($id);

        $this->editingId = $banner->id;
        $this->title = (string) $banner->title;
        $this->link_url = (string) $banner->link_url;
        $this->order = $banner->order;
        $this->starts_at = $banner->starts_at?->format('Y-m-d');
        $this->ends_at = $banner->ends_at?->format('Y-m-d');
        $this->is_active = $banner->is_active;
        $this->existingImagePath = $banner->image_path;
        $this->existingImageMobilePath = $banner->image_path_mobile;
        $this->imageDesktop = null;
        $this->imageMobile = null;
    }

    public function save(): void
    {
        $data = $this->validate();
        unset($data['imageDesktop'], $data['imageMobile']);

        $data['link_url'] = $data['link_url'] ?: null;
        $data['starts_at'] = $data['starts_at'] ?: null;
        $data['ends_at'] = $data['ends_at'] ?: null;

        $uploader = app(ImageUploadService::class);

        if ($this->imageDesktop) {
            $data['image_path'] = $uploader->store($this->imageDesktop, 'banners');
        }

        if ($this->imageMobile) {
            $data['image_path_mobile'] = $uploader->store($this->imageMobile, 'banners');
        }

        Banner::updateOrCreate(['id' => $this->editingId], $data);

        $this->cancel();
        unset($this->banners);
    }

    public function delete(int $id): void
    {
        Banner::whereKey($id)->delete();
        unset($this->banners);
    }

    public function cancel(): void
    {
        $this->reset(['editingId', 'title', 'link_url', 'order', 'starts_at', 'ends_at', 'is_active', 'imageDesktop', 'imageMobile', 'existingImagePath', 'existingImageMobilePath']);
        $this->is_active = true;
    }
};
?>

<div class="space-y-6">
    <form wire:submit="save" class="bg-white rounded-xl shadow p-6 space-y-4 max-w-xl">
        <h2 class="font-semibold">{{ $editingId ? 'Editar banner' : 'Nuevo banner' }}</h2>

        <div>
            <label class="block text-sm font-medium">Título (opcional)</label>
            <input type="text" wire:model="title" class="mt-1 w-full rounded border-gray-300">
        </div>

        <div>
            <label class="block text-sm font-medium">Enlace (opcional)</label>
            <input type="text" wire:model="link_url" class="mt-1 w-full rounded border-gray-300">
            @error('link_url') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium">Vigente desde</label>
                <input type="date" wire:model="starts_at" class="mt-1 w-full rounded border-gray-300">
            </div>
            <div>
                <label class="block text-sm font-medium">Vigente hasta</label>
                <input type="date" wire:model="ends_at" class="mt-1 w-full rounded border-gray-300">
            </div>
        </div>

        <div>
            <label class="block text-sm font-medium">Orden</label>
            <input type="number" wire:model="order" class="mt-1 w-full rounded border-gray-300">
        </div>

        <label class="flex items-center gap-2">
            <input type="checkbox" wire:model="is_active">
            <span class="text-sm">Activo</span>
        </label>

        @if ($existingImagePath)
            <img src="{{ Storage::url($existingImagePath) }}" class="h-20 rounded">
        @endif

        <div>
            <label class="block text-sm font-medium">Imagen desktop</label>
            <input type="file" wire:model="imageDesktop" accept="image/*" class="mt-1 w-full text-sm">
            @error('imageDesktop') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="block text-sm font-medium">Imagen mobile (opcional)</label>
            <input type="file" wire:model="imageMobile" accept="image/*" class="mt-1 w-full text-sm">
            @error('imageMobile') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
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
                <th class="p-3">Imagen</th>
                <th class="p-3">Título</th>
                <th class="p-3">Vigencia</th>
                <th class="p-3">Activo</th>
                <th class="p-3"></th>
            </tr>
        </thead>
        <tbody>
            @foreach ($this->banners as $banner)
                <tr wire:key="banner-{{ $banner->id }}" class="border-t">
                    <td class="p-3"><img src="{{ Storage::url($banner->image_path) }}" class="h-10 rounded"></td>
                    <td class="p-3">{{ $banner->title ?? '—' }}</td>
                    <td class="p-3 text-xs">
                        {{ $banner->starts_at?->format('d/m/Y') ?? 'Sin inicio' }} — {{ $banner->ends_at?->format('d/m/Y') ?? 'Sin fin' }}
                    </td>
                    <td class="p-3">{{ $banner->is_active ? 'Sí' : 'No' }}</td>
                    <td class="p-3 text-right space-x-2">
                        <button wire:click="edit({{ $banner->id }})" class="text-blue-600">Editar</button>
                        <button wire:click="delete({{ $banner->id }})" wire:confirm="¿Eliminar este banner?" class="text-red-600">Eliminar</button>
                    </td>
                </tr>
            @endforeach
            @if ($this->banners->isEmpty())
                <tr><td colspan="5" class="p-3 text-gray-500">Sin banners todavía.</td></tr>
            @endif
        </tbody>
    </table>
</div>
