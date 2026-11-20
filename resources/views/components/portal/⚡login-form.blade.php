<?php

use App\Models\Patient;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Validate;
use Livewire\Component;

new class extends Component
{
    #[Validate('required|string')]
    public string $identifier = '';

    #[Validate('required|string')]
    public string $password = '';

    public function login(): void
    {
        $this->validate();

        $throttleKey = 'patient-login:'.request()->ip().'|'.mb_strtolower($this->identifier);

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $this->addError('identifier', 'Demasiados intentos. Espera un minuto e inténtalo de nuevo.');

            return;
        }

        $patient = Patient::where('document_number', $this->identifier)
            ->orWhere('email', $this->identifier)
            ->first();

        if (! $patient || ! Hash::check($this->password, $patient->password)) {
            RateLimiter::hit($throttleKey, 60);
            $this->addError('identifier', 'Documento/correo o contraseña incorrectos.');

            return;
        }

        if ($patient->status !== 'active') {
            $this->addError('identifier', 'Tu cuenta está bloqueada. Comunícate con la clínica.');

            return;
        }

        RateLimiter::clear($throttleKey);
        Auth::guard('patient')->login($patient, remember: true);
        session()->regenerate();

        $this->redirectRoute($patient->must_change_password ? 'portal.password.edit' : 'portal.dashboard');
    }
};
?>

<div class="bg-white rounded-xl shadow p-6 space-y-4 mt-6">
    <h1 class="text-xl font-semibold">Ingresar</h1>

    <form wire:submit="login" class="space-y-4">
        <div>
            <label class="block text-sm font-medium">Documento o correo</label>
            <input type="text" wire:model="identifier" class="mt-1 w-full rounded px-3 py-2 border border-black/15 focus:border-azul focus:ring-1 focus:ring-azul focus:outline-none" autofocus>
            @error('identifier') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="block text-sm font-medium">Contraseña</label>
            <input type="password" wire:model="password" class="mt-1 w-full rounded px-3 py-2 border border-black/15 focus:border-azul focus:ring-1 focus:ring-azul focus:outline-none">
            @error('password') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <button type="submit" class="w-full rounded-brand bg-azul text-white py-2.5 font-medium border-2 border-transparent transition-all duration-500 hover:bg-white hover:text-azul hover:border-azul">Ingresar</button>
    </form>

    <p class="text-sm text-center text-muted">
        ¿No tienes cuenta? <a href="{{ route('portal.register') }}" class="underline">Regístrate aquí</a>
    </p>
</div>
