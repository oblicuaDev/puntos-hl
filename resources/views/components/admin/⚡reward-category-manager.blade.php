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
    <form wire:submit="save" class="bg-white rounded-brand shadow-sm ring-1 ring-black/5 p-6 space-y-4 max-w-xl">
        <x-card-header :icon="$editingId ? 'o-pencil-square' : 'o-tag'">{{ $editingId ? 'Editar categoría' : 'Nueva categoría' }}</x-card-header>

        <div>
            <label class="block text-sm font-medium">Nombre</label>
            <input type="text" wire:model="name" class="mt-1.5 w-full rounded-xl px-3 py-2.5 border border-black/10 bg-white focus:border-azul focus:ring-2 focus:ring-azul/20 focus:outline-none">
            @error('name') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="block text-sm font-medium">Categoría padre (opcional)</label>
            <select wire:model="parent_id" class="mt-1.5 w-full rounded-xl px-3 py-2.5 border border-black/10 bg-white focus:border-azul focus:ring-2 focus:ring-azul/20 focus:outline-none">
                <option value="">Ninguna (categoría de nivel superior)</option>
                @foreach ($this->parentOptions as $option)
                    <option value="{{ $option->id }}">{{ $option->name }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="block text-sm font-medium">Orden</label>
            <input type="number" wire:model="order" class="mt-1.5 w-full rounded-xl px-3 py-2.5 border border-black/10 bg-white focus:border-azul focus:ring-2 focus:ring-azul/20 focus:outline-none">
        </div>

        <label class="flex items-center gap-2">
            <input type="checkbox" wire:model="is_active">
            <span class="text-sm">Activa</span>
        </label>

        <div class="flex gap-2">
            <x-submit-button target="save" loading-text="Guardando…" class="rounded-xl bg-azul text-white px-5 py-2.5 text-sm font-semibold transition-colors hover:bg-azul-dark">
                {{ $editingId ? 'Guardar cambios' : 'Crear' }}
            </x-submit-button>
            @if ($editingId)
                <button type="button" wire:click="cancel" class="rounded-xl px-4 py-2.5 text-sm font-medium text-muted hover:text-ink hover:bg-black/5">Cancelar</button>
            @endif
        </div>
    </form>

    <div class="bg-white rounded-brand shadow-sm ring-1 ring-black/5 overflow-hidden"><table class="w-full text-sm">
        <thead class="text-left text-[11px] uppercase tracking-wider text-muted bg-[#F7F8FA]">
            <tr>
                <th class="px-5 py-3 font-semibold">Nombre</th>
                <th class="px-5 py-3 font-semibold">Padre</th>
                <th class="px-5 py-3 font-semibold">Activa</th>
                <th class="px-5 py-3 font-semibold"></th>
            </tr>
        </thead>
        <tbody>
            @foreach ($this->categories as $category)
                <tr wire:key="category-{{ $category->id }}" class="border-t border-black/5 hover:bg-[#FAFBFC] transition-colors">
                    <td class="px-5 py-3">{{ $category->name }}</td>
                    <td class="px-5 py-3">{{ $category->parent?->name ?? '—' }}</td>
                    <td class="px-5 py-3"><x-status-badge :active="$category->is_active" true-label="Activa" false-label="Inactiva" /></td>
                    <td class="px-5 py-3 text-right">
                        <button wire:click="edit({{ $category->id }})" class="rounded-lg px-3 py-1.5 font-medium text-azul hover:bg-azul/10 transition-colors">Editar</button>
                        <button wire:click="delete({{ $category->id }})" wire:confirm="¿Eliminar esta categoría?" class="rounded-lg px-3 py-1.5 font-medium text-red-600 hover:bg-red-50 transition-colors">Eliminar</button>
                    </td>
                </tr>
            @endforeach
            @if ($this->categories->isEmpty())
                <x-empty-state-row :colspan="4" icon="o-tag" message="Sin categorías todavía." />
            @endif
        </tbody>
    </table></div>
</div>
