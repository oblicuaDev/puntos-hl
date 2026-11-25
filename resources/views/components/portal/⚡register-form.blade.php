<?php

use App\Models\Patient;
use App\Services\Auth\OtpException;
use App\Services\Auth\OtpService;
use App\Support\Phone;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Livewire\Component;

/**
 * Registro de pacientes sin contraseña (HL-25): la cuenta solo se crea
 * después de verificar el celular con un código de 4 dígitos por SMS, porque
 * ese celular es lo que después le permite ingresar.
 */
new class extends Component
{
    public string $step = 'form';

    public string $document_type = 'CC';

    public string $document_number = '';

    public string $first_name = '';

    public string $last_name = '';

    public string $email = '';

    public string $phone = '';

    public string $code = '';

    protected function rules(): array
    {
        return [
            'document_type' => 'required|in:CC,CE,TI,PAS',
            'document_number' => 'required|string|max:30|unique:patients,document_number',
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email|unique:patients,email',
            'phone' => 'required|string|max:30',
        ];
    }

    public function sendCode(): void
    {
        $this->document_number = preg_replace('/[^0-9A-Za-z]/', '', $this->document_number);
        $this->validate();

        $phone = Phone::normalize($this->phone);

        if (! $phone) {
            $this->addError('phone', 'Ingresa un celular válido (10 dígitos, o con indicativo si es de otro país).');

            return;
        }

        $throttleKey = 'patient-register:'.request()->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $this->addError('document_number', 'Demasiados intentos de registro desde esta conexión. Espera unos minutos.');

            return;
        }

        RateLimiter::hit($throttleKey, 600);

        try {
            app(OtpService::class)->send($phone, 'register');
        } catch (OtpException $e) {
            $this->addError('phone', $e->getMessage());

            return;
        }

        $this->code = '';
        $this->step = 'code';
    }

    public function register(): void
    {
        $data = $this->validate();
        $phone = Phone::normalize($this->phone);

        if (! $phone) {
            $this->step = 'form';

            return;
        }

        try {
            app(OtpService::class)->verify($phone, 'register', trim($this->code));
        } catch (OtpException $e) {
            $this->addError('code', $e->getMessage());

            return;
        }

        $patient = Patient::create([
            ...$data,
            'phone' => $phone,
            // Sin contraseña real: el ingreso es solo por código SMS.
            'password' => Str::random(64),
            'must_change_password' => false,
            'registration_source' => 'self_registered',
            'status' => 'active',
        ]);

        Auth::guard('patient')->login($patient, remember: true);
        session()->regenerate();

        $this->redirectRoute('portal.dashboard');
    }

    public function editData(): void
    {
        $this->reset(['step', 'code']);
    }
};
?>

<div class="bg-white rounded-xl shadow p-6 space-y-4 mt-6">
    <div class="text-center space-y-2">
        <span class="inline-flex items-center justify-center h-12 w-12 rounded-full bg-azul/10 text-azul">
            @svg($step === 'form' ? 'heroicon-o-user-plus' : 'heroicon-o-device-phone-mobile', 'h-5 w-5')
        </span>
        <h1 class="text-xl font-semibold">{{ $step === 'form' ? 'Crear cuenta' : 'Verifica tu celular' }}</h1>
        @if ($step === 'code')
            <p class="text-sm text-muted">Enviamos un código de 4 dígitos al celular terminado en <span class="font-medium text-ink">{{ substr(preg_replace('/\D/', '', $phone), -4) }}</span>.</p>
        @endif
    </div>

    @if ($step === 'form')
        <form wire:submit="sendCode" class="space-y-4">
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label for="document_type" class="block text-sm font-medium">Tipo doc.</label>
                    <select id="document_type" wire:model="document_type" class="mt-1 w-full rounded px-3 py-2 border border-black/15 focus:border-azul focus:ring-1 focus:ring-azul focus:outline-none">
                        <option value="CC">CC</option>
                        <option value="CE">CE</option>
                        <option value="TI">TI</option>
                        <option value="PAS">Pasaporte</option>
                    </select>
                </div>
                <div>
                    <label for="document_number" class="block text-sm font-medium">Número</label>
                    <input id="document_number" type="text" wire:model="document_number" class="mt-1 w-full rounded px-3 py-2 border border-black/15 focus:border-azul focus:ring-1 focus:ring-azul focus:outline-none">
                    @error('document_number') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>

            <div>
                <label for="first_name" class="block text-sm font-medium">Nombres</label>
                <input id="first_name" type="text" wire:model="first_name" class="mt-1 w-full rounded px-3 py-2 border border-black/15 focus:border-azul focus:ring-1 focus:ring-azul focus:outline-none">
                @error('first_name') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="last_name" class="block text-sm font-medium">Apellidos</label>
                <input id="last_name" type="text" wire:model="last_name" class="mt-1 w-full rounded px-3 py-2 border border-black/15 focus:border-azul focus:ring-1 focus:ring-azul focus:outline-none">
                @error('last_name') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="email" class="block text-sm font-medium">Correo</label>
                <input id="email" type="email" wire:model="email" class="mt-1 w-full rounded px-3 py-2 border border-black/15 focus:border-azul focus:ring-1 focus:ring-azul focus:outline-none">
                @error('email') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="phone" class="block text-sm font-medium">Celular</label>
                <input id="phone" type="tel" inputmode="tel" autocomplete="tel" wire:model="phone" placeholder="300 123 4567" class="mt-1 w-full rounded px-3 py-2 border border-black/15 focus:border-azul focus:ring-1 focus:ring-azul focus:outline-none">
                <p class="text-xs text-muted mt-1">Te enviaremos un código por SMS cada vez que ingreses. Si es de otro país, incluye el indicativo (+1, +34…).</p>
                @error('phone') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <x-submit-button target="sendCode" loading-text="Enviando código…" class="w-full rounded-brand bg-azul text-white py-2.5 font-medium border-2 border-transparent transition-all duration-500 hover:bg-white hover:text-azul hover:border-azul">Continuar</x-submit-button>
        </form>
    @else
        <form wire:submit="register" class="space-y-4">
            <div>
                <label for="code" class="block text-sm font-medium">Código de verificación</label>
                <input id="code" type="text" inputmode="numeric" autocomplete="one-time-code" maxlength="4" pattern="[0-9]*" wire:model="code" class="mt-1 w-full rounded px-3 py-2 border border-black/15 text-center text-2xl tracking-[0.5em] tabular-nums focus:border-azul focus:ring-1 focus:ring-azul focus:outline-none" autofocus>
                @error('code') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
                @foreach (['document_number', 'email', 'phone'] as $field)
                    @error($field) <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
                @endforeach
            </div>

            <x-submit-button target="register" loading-text="Creando cuenta…" class="w-full rounded-brand bg-azul text-white py-2.5 font-medium border-2 border-transparent transition-all duration-500 hover:bg-white hover:text-azul hover:border-azul">Crear cuenta</x-submit-button>
        </form>

        <div class="flex items-center justify-between text-sm">
            <button type="button" wire:click="editData" class="text-muted underline">Corregir mis datos</button>
            <button type="button" wire:click="sendCode" wire:loading.attr="disabled" class="text-azul underline">Reenviar código</button>
        </div>
    @endif

    <p class="text-sm text-center text-muted">
        ¿Ya tienes cuenta? <a href="{{ route('portal.login') }}" wire:navigate class="underline">Ingresa aquí</a>
    </p>
</div>
