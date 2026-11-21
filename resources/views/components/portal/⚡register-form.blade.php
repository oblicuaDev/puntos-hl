<?php

use App\Models\Patient;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Validate;
use Livewire\Component;

new class extends Component
{
    #[Validate('required|in:CC,CE,TI,PAS')]
    public string $document_type = 'CC';

    #[Validate('required|string|max:30|unique:patients,document_number')]
    public string $document_number = '';

    #[Validate('required|string|max:255')]
    public string $first_name = '';

    #[Validate('required|string|max:255')]
    public string $last_name = '';

    #[Validate('required|email|unique:patients,email')]
    public string $email = '';

    #[Validate('nullable|string|max:30')]
    public string $phone = '';

    #[Validate('required|string|min:8|confirmed')]
    public string $password = '';

    #[Validate('required|string')]
    public string $password_confirmation = '';

    public function register(): void
    {
        $throttleKey = 'patient-register:'.request()->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $this->addError('document_number', 'Demasiados intentos de registro desde esta conexión. Espera unos minutos.');

            return;
        }

        RateLimiter::hit($throttleKey, 300);

        $data = $this->validate();
        unset($data['password_confirmation']);

        $data['registration_source'] = 'self_registered';
        $data['must_change_password'] = false;
        $data['status'] = 'active';

        $patient = Patient::create($data);

        Auth::guard('patient')->login($patient, remember: true);
        session()->regenerate();

        $this->redirectRoute('portal.dashboard');
    }
};
?>

<div class="bg-white rounded-xl shadow p-6 space-y-4 mt-6">
    <div class="text-center space-y-2">
        <span class="inline-flex items-center justify-center h-12 w-12 rounded-full bg-azul/10 text-azul">
            @svg('heroicon-o-user-plus', 'h-5 w-5')
        </span>
        <h1 class="text-xl font-semibold">Crear cuenta</h1>
    </div>

    <form wire:submit="register" class="space-y-4">
        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium">Tipo doc.</label>
                <select wire:model="document_type" class="mt-1 w-full rounded px-3 py-2 border border-black/15 focus:border-azul focus:ring-1 focus:ring-azul focus:outline-none">
                    <option value="CC">CC</option>
                    <option value="CE">CE</option>
                    <option value="TI">TI</option>
                    <option value="PAS">Pasaporte</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium">Número</label>
                <input type="text" wire:model="document_number" class="mt-1 w-full rounded px-3 py-2 border border-black/15 focus:border-azul focus:ring-1 focus:ring-azul focus:outline-none">
                @error('document_number') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
        </div>

        <div>
            <label class="block text-sm font-medium">Nombres</label>
            <input type="text" wire:model="first_name" class="mt-1 w-full rounded px-3 py-2 border border-black/15 focus:border-azul focus:ring-1 focus:ring-azul focus:outline-none">
            @error('first_name') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="block text-sm font-medium">Apellidos</label>
            <input type="text" wire:model="last_name" class="mt-1 w-full rounded px-3 py-2 border border-black/15 focus:border-azul focus:ring-1 focus:ring-azul focus:outline-none">
            @error('last_name') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="block text-sm font-medium">Correo</label>
            <input type="email" wire:model="email" class="mt-1 w-full rounded px-3 py-2 border border-black/15 focus:border-azul focus:ring-1 focus:ring-azul focus:outline-none">
            @error('email') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="block text-sm font-medium">Teléfono</label>
            <input type="text" wire:model="phone" class="mt-1 w-full rounded px-3 py-2 border border-black/15 focus:border-azul focus:ring-1 focus:ring-azul focus:outline-none">
        </div>

        <div>
            <label class="block text-sm font-medium">Contraseña</label>
            <input type="password" wire:model="password" class="mt-1 w-full rounded px-3 py-2 border border-black/15 focus:border-azul focus:ring-1 focus:ring-azul focus:outline-none">
            @error('password') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="block text-sm font-medium">Confirmar contraseña</label>
            <input type="password" wire:model="password_confirmation" class="mt-1 w-full rounded px-3 py-2 border border-black/15 focus:border-azul focus:ring-1 focus:ring-azul focus:outline-none">
        </div>

        <x-submit-button target="register" loading-text="Creando cuenta…" class="w-full rounded-brand bg-azul text-white py-2.5 font-medium border-2 border-transparent transition-all duration-500 hover:bg-white hover:text-azul hover:border-azul">Crear cuenta</x-submit-button>
    </form>

    <p class="text-sm text-center text-muted">
        ¿Ya tienes cuenta? <a href="{{ route('portal.login') }}" wire:navigate class="underline">Ingresa aquí</a>
    </p>
</div>
