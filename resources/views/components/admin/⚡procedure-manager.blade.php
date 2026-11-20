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
    <form wire:submit="save" class="bg-white rounded-xl shadow p-6 space-y-4 max-w-xl">
        <h2 class="font-semibold">{{ $editingId ? 'Editar procedimiento' : 'Nuevo procedimiento' }}</h2>

        <div>
            <label class="block text-sm font-medium">Nombre</label>
            <input type="text" wire:model="name" class="mt-1 w-full rounded px-3 py-2 border border-black/15 focus:border-azul focus:ring-1 focus:ring-azul focus:outline-none">
            @error('name') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="block text-sm font-medium">Descripción</label>
            <textarea wire:model="description" class="mt-1 w-full rounded px-3 py-2 border border-black/15 focus:border-azul focus:ring-1 focus:ring-azul focus:outline-none"></textarea>
        </div>

        <div>
            <label class="block text-sm font-medium">Precio en puntos</label>
            <input type="number" wire:model="points_price" class="mt-1 w-full rounded px-3 py-2 border border-black/15 focus:border-azul focus:ring-1 focus:ring-azul focus:outline-none">
            @error('points_price') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <label class="flex items-center gap-2">
            <input type="checkbox" wire:model="is_active">
            <span class="text-sm">Activo</span>
        </label>

        <div class="flex gap-2">
            <button type="submit" class="rounded-full bg-azul text-white px-5 py-2 text-sm font-medium border-2 border-transparent transition-all duration-500 hover:bg-white hover:text-azul hover:border-azul">
                {{ $editingId ? 'Guardar cambios' : 'Crear' }}
            </button>
            @if ($editingId)
                <button type="button" wire:click="cancel" class="rounded px-4 py-2 text-sm text-muted">Cancelar</button>
            @endif
        </div>
    </form>

    <table class="w-full bg-white rounded-xl shadow overflow-hidden text-sm">
        <thead class="bg-gray-50 text-left">
            <tr>
                <th class="p-3">Nombre</th>
                <th class="p-3">Puntos</th>
                <th class="p-3">Activo</th>
                <th class="p-3"></th>
            </tr>
        </thead>
        <tbody>
            @foreach ($this->procedures as $procedure)
                <tr wire:key="procedure-{{ $procedure->id }}" class="border-t">
                    <td class="p-3">{{ $procedure->name }}</td>
                    <td class="p-3">{{ number_format($procedure->points_price, 0, ',', '.') }}</td>
                    <td class="p-3">{{ $procedure->is_active ? 'Sí' : 'No' }}</td>
                    <td class="p-3 text-right space-x-2">
                        <button wire:click="edit({{ $procedure->id }})" class="text-azul">Editar</button>
                        <button wire:click="delete({{ $procedure->id }})" wire:confirm="¿Eliminar este procedimiento?" class="text-red-600">Eliminar</button>
                    </td>
                </tr>
            @endforeach
            @if ($this->procedures->isEmpty())
                <tr><td colspan="4" class="p-3 text-muted">Sin procedimientos todavía.</td></tr>
            @endif
        </tbody>
    </table>
</div>
