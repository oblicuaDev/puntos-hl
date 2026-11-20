<?php

use App\Models\RewardCategory;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Validate;
use Livewire\Component;

new class extends Component
{
    public ?int $editingId = null;

    #[Validate('required|string|max:255')]
    public string $name = '';

    #[Validate('nullable|exists:reward_categories,id')]
    public ?int $parent_id = null;

    #[Validate('required|integer|min:0')]
    public int $order = 0;

    public bool $is_active = true;

    #[Computed]
    public function categories()
    {
        return RewardCategory::with('parent')->orderBy('order')->orderBy('name')->get();
    }

    #[Computed]
    public function parentOptions()
    {
        return $this->categories->reject(fn ($c) => $c->id === $this->editingId);
    }

    public function edit(int $id): void
    {
        $category = RewardCategory::findOrFail($id);

        $this->editingId = $category->id;
        $this->name = $category->name;
        $this->parent_id = $category->parent_id;
        $this->order = $category->order;
        $this->is_active = $category->is_active;
    }

    public function save(): void
    {
        $data = $this->validate();

        $slugSource = $this->name;
        $slug = Str::slug($slugSource);
        $existing = RewardCategory::where('slug', $slug)->where('id', '!=', $this->editingId)->exists();
        $data['slug'] = $existing ? $slug.'-'.Str::random(4) : $slug;
        $data['is_active'] = $this->is_active;

        RewardCategory::updateOrCreate(['id' => $this->editingId], $data);

        $this->cancel();
        unset($this->categories);
    }

    public function delete(int $id): void
    {
        RewardCategory::whereKey($id)->delete();
        unset($this->categories);
    }

    public function cancel(): void
    {
        $this->reset(['editingId', 'name', 'parent_id', 'order', 'is_active']);
        $this->is_active = true;
    }
};
?>

<div class="space-y-6">
    <form wire:submit="save" class="bg-white rounded-xl shadow p-6 space-y-4 max-w-xl">
        <h2 class="font-semibold">{{ $editingId ? 'Editar categoría' : 'Nueva categoría' }}</h2>

        <div>
            <label class="block text-sm font-medium">Nombre</label>
            <input type="text" wire:model="name" class="mt-1 w-full rounded border-gray-300">
            @error('name') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="block text-sm font-medium">Categoría padre (opcional)</label>
            <select wire:model="parent_id" class="mt-1 w-full rounded border-gray-300">
                <option value="">Ninguna (categoría de nivel superior)</option>
                @foreach ($this->parentOptions as $option)
                    <option value="{{ $option->id }}">{{ $option->name }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="block text-sm font-medium">Orden</label>
            <input type="number" wire:model="order" class="mt-1 w-full rounded border-gray-300">
        </div>

        <label class="flex items-center gap-2">
            <input type="checkbox" wire:model="is_active">
            <span class="text-sm">Activa</span>
        </label>

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
                <th class="p-3">Nombre</th>
                <th class="p-3">Padre</th>
                <th class="p-3">Activa</th>
                <th class="p-3"></th>
            </tr>
        </thead>
        <tbody>
            @foreach ($this->categories as $category)
                <tr wire:key="category-{{ $category->id }}" class="border-t">
                    <td class="p-3">{{ $category->name }}</td>
                    <td class="p-3">{{ $category->parent?->name ?? '—' }}</td>
                    <td class="p-3">{{ $category->is_active ? 'Sí' : 'No' }}</td>
                    <td class="p-3 text-right space-x-2">
                        <button wire:click="edit({{ $category->id }})" class="text-blue-600">Editar</button>
                        <button wire:click="delete({{ $category->id }})" wire:confirm="¿Eliminar esta categoría?" class="text-red-600">Eliminar</button>
                    </td>
                </tr>
            @endforeach
            @if ($this->categories->isEmpty())
                <tr><td colspan="4" class="p-3 text-gray-500">Sin categorías todavía.</td></tr>
            @endif
        </tbody>
    </table>
</div>
