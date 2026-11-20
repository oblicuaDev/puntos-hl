<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Livewire\Attributes\Validate;
use Livewire\Component;

new class extends Component
{
    #[Validate('required|string')]
    public string $current_password = '';

    #[Validate('required|string|min:8|confirmed')]
    public string $password = '';

    #[Validate('required|string')]
    public string $password_confirmation = '';

    public function updatePassword(): void
    {
        $this->validate();

        $patient = Auth::guard('patient')->user();

        if (! Hash::check($this->current_password, $patient->password)) {
            $this->addError('current_password', 'La contraseña actual no es correcta.');

            return;
        }

        $patient->update([
            'password' => $this->password,
            'must_change_password' => false,
        ]);

        $this->redirectRoute('portal.dashboard');
    }
};
?>

<div class="bg-white rounded-xl shadow p-6 space-y-4 mt-6">
    <h1 class="text-xl font-semibold">Cambiar contraseña</h1>
    <p class="text-sm text-gray-500">Por seguridad, debes definir una nueva contraseña antes de continuar.</p>

    <form wire:submit="updatePassword" class="space-y-4">
        <div>
            <label class="block text-sm font-medium">Contraseña actual</label>
            <input type="password" wire:model="current_password" class="mt-1 w-full rounded border-gray-300">
            @error('current_password') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="block text-sm font-medium">Nueva contraseña</label>
            <input type="password" wire:model="password" class="mt-1 w-full rounded border-gray-300">
            @error('password') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="block text-sm font-medium">Confirmar nueva contraseña</label>
            <input type="password" wire:model="password_confirmation" class="mt-1 w-full rounded border-gray-300">
        </div>

        <button type="submit" class="w-full rounded-lg bg-gray-900 text-white py-2.5 font-medium">Guardar</button>
    </form>
</div>
