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
    <div class="text-center space-y-2">
        <span class="inline-flex items-center justify-center h-12 w-12 rounded-full bg-azul/10 text-azul">
            @svg('heroicon-o-key', 'h-5 w-5')
        </span>
        <h1 class="text-xl font-semibold">Cambiar contraseña</h1>
    </div>
    <p class="text-sm text-muted text-center">Por seguridad, debes definir una nueva contraseña antes de continuar.</p>

    <form wire:submit="updatePassword" class="space-y-4">
        <div>
            <label class="block text-sm font-medium">Contraseña actual</label>
            <input type="password" wire:model="current_password" class="mt-1 w-full rounded px-3 py-2 border border-black/15 focus:border-azul focus:ring-1 focus:ring-azul focus:outline-none">
            @error('current_password') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="block text-sm font-medium">Nueva contraseña</label>
            <input type="password" wire:model="password" class="mt-1 w-full rounded px-3 py-2 border border-black/15 focus:border-azul focus:ring-1 focus:ring-azul focus:outline-none">
            @error('password') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="block text-sm font-medium">Confirmar nueva contraseña</label>
            <input type="password" wire:model="password_confirmation" class="mt-1 w-full rounded px-3 py-2 border border-black/15 focus:border-azul focus:ring-1 focus:ring-azul focus:outline-none">
        </div>

        <x-submit-button target="updatePassword" loading-text="Guardando…" class="w-full rounded-brand bg-azul text-white py-2.5 font-medium border-2 border-transparent transition-all duration-500 hover:bg-white hover:text-azul hover:border-azul">Guardar</x-submit-button>
    </form>
</div>
