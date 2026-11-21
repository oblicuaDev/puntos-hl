<?php

use App\Models\Procedure;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Validate;
use Livewire\Component;

new class extends Component
{
    public ?int $editingId = null;

    #[Validate('required|string|max:255')]
    public string $name = '';

    #[Validate('nullable|string')]
    public string $description = '';

    #[Validate('required|integer|min:1')]
    public ?int $points_price = null;

    public bool $is_active = true;

    #[Computed]
    public function procedures()
    {
        return Procedure::orderBy('name')->get();
    }

    public function edit(int $id): void
    {
        $procedure = Procedure::findOrFail($id);

        $this->editingId = $procedure->id;
        $this->name = $procedure->name;
        $this->description = (string) $procedure->description;
        $this->points_price = $procedure->points_price;
        $this->is_active = $procedure->is_active;
    }

    public function save(): void
    {
        $data = $this->validate();

        Procedure::updateOrCreate(['id' => $this->editingId], $data);

        $this->reset(['editingId', 'name', 'description', 'points_price', 'is_active']);
        $this->is_active = true;
        unset($this->procedures);
    }

    public function delete(int $id): void
    {
        Procedure::whereKey($id)->delete();
        unset($this->procedures);
    }

    public function cancel(): void
    {
        $this->reset(['editingId', 'name', 'description', 'points_price', 'is_active']);
        $this->is_active = true;
    }
};
?>

<div class="space-y-6">
    <form wire:submit="save" class="bg-white rounded-brand shadow-sm ring-1 ring-black/5 p-6 space-y-4 max-w-xl">
        <x-card-header :icon="$editingId ? 'o-pencil-square' : 'o-clipboard-document-list'">{{ $editingId ? 'Editar procedimiento' : 'Nuevo procedimiento' }}</x-card-header>

        <div>
            <label class="block text-sm font-medium">Nombre</label>
            <input type="text" wire:model="name" class="mt-1.5 w-full rounded-xl px-3 py-2.5 border border-black/10 bg-white focus:border-azul focus:ring-2 focus:ring-azul/20 focus:outline-none">
            @error('name') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="block text-sm font-medium">Descripción</label>
            <textarea wire:model="description" class="mt-1.5 w-full rounded-xl px-3 py-2.5 border border-black/10 bg-white focus:border-azul focus:ring-2 focus:ring-azul/20 focus:outline-none"></textarea>
        </div>

        <div>
            <label class="block text-sm font-medium">Precio en puntos</label>
            <input type="number" wire:model="points_price" class="mt-1.5 w-full rounded-xl px-3 py-2.5 border border-black/10 bg-white focus:border-azul focus:ring-2 focus:ring-azul/20 focus:outline-none">
            @error('points_price') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <label class="flex items-center gap-2">
            <input type="checkbox" wire:model="is_active">
            <span class="text-sm">Activo</span>
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
                <th class="px-5 py-3 font-semibold">Puntos</th>
                <th class="px-5 py-3 font-semibold">Activo</th>
                <th class="px-5 py-3 font-semibold"></th>
            </tr>
        </thead>
        <tbody>
            @foreach ($this->procedures as $procedure)
                <tr wire:key="procedure-{{ $procedure->id }}" class="border-t border-black/5 hover:bg-[#FAFBFC] transition-colors">
                    <td class="px-5 py-3">{{ $procedure->name }}</td>
                    <td class="px-5 py-3">{{ number_format($procedure->points_price, 0, ',', '.') }}</td>
                    <td class="px-5 py-3"><x-status-badge :active="$procedure->is_active" /></td>
                    <td class="px-5 py-3 text-right">
                        <button wire:click="edit({{ $procedure->id }})" class="rounded-lg px-3 py-1.5 font-medium text-azul hover:bg-azul/10 transition-colors">Editar</button>
                        <button wire:click="delete({{ $procedure->id }})" wire:confirm="¿Eliminar este procedimiento?" class="rounded-lg px-3 py-1.5 font-medium text-red-600 hover:bg-red-50 transition-colors">Eliminar</button>
                    </td>
                </tr>
            @endforeach
            @if ($this->procedures->isEmpty())
                <x-empty-state-row :colspan="4" icon="o-clipboard-document-list" message="Sin procedimientos todavía." />
            @endif
        </tbody>
    </table></div>
</div>
