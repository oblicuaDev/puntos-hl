<?php

use App\Models\Patient;
use App\Services\Auth\OtpException;
use App\Services\Auth\OtpService;
use App\Support\Phone;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Component;

/**
 * Ingreso de pacientes con documento + código de 4 dígitos por SMS (HL-25).
 * No hay contraseñas: el celular registrado del paciente es el segundo factor.
 */
new class extends Component
{
    public string $step = 'document';

    public string $document = '';

    public string $code = '';

    public string $maskedPhone = '';

    public function sendCode(): void
    {
        $this->resetErrorBag();
        $this->document = trim($this->document);

        if ($this->document === '') {
            $this->addError('document', 'Ingresa tu número de documento.');

            return;
        }

        // Límite por conexión, además del límite por número dentro de OtpService:
        // evita usar el formulario para enviar SMS masivos a muchos documentos.
        $ipKey = 'patient-otp-ip:'.request()->ip();

        if (RateLimiter::tooManyAttempts($ipKey, 10)) {
            $this->addError('document', 'Demasiados intentos desde esta conexión. Espera unos minutos.');

            return;
        }

        RateLimiter::hit($ipKey, 600);

        $patient = $this->findPatient();

        if (! $patient) {
            $this->addError('document', 'Este documento no está registrado en el plan de puntos. Si eres paciente de Harker Lloreda, comunícate con la clínica.');

            return;
        }

        if ($patient->status !== 'active') {
            $this->addError('document', 'Tu cuenta está bloqueada. Comunícate con la clínica.');

            return;
        }

        $phone = Phone::normalize($patient->phone);

        if (! $phone) {
            $this->addError('document', 'Tu cuenta no tiene un celular asociado. Comunícate con la clínica para actualizarlo.');

            return;
        }

        try {
            app(OtpService::class)->send($phone, 'login', $patient);
        } catch (OtpException $e) {
            $this->addError('document', $e->getMessage());

            return;
        }

        $this->maskedPhone = Phone::mask($phone);
        $this->code = '';
        $this->step = 'code';
    }

    public function verify(): void
    {
        $this->resetErrorBag();
        $patient = $this->findPatient();
        $phone = Phone::normalize($patient?->phone);

        if (! $patient || ! $phone || $patient->status !== 'active') {
            $this->backToDocument();

            return;
        }

        try {
            app(OtpService::class)->verify($phone, 'login', trim($this->code));
        } catch (OtpException $e) {
            $this->addError('code', $e->getMessage());

            return;
        }

        Auth::guard('patient')->login($patient, remember: true);
        session()->regenerate();

        $this->redirectIntended(route('portal.rewards.index'));
    }

    public function resend(): void
    {
        $this->sendCode();

        // Si no se pudo reenviar (p. ej. aún en espera), se queda en la pantalla del código.
        if ($this->getErrorBag()->isNotEmpty()) {
            $this->step = 'code';
        }
    }

    public function backToDocument(): void
    {
        $this->reset(['step', 'code', 'maskedPhone']);
    }

    private function findPatient(): ?Patient
    {
        $digits = preg_replace('/[^0-9A-Za-z]/', '', $this->document);

        return Patient::where('document_number', $this->document)
            ->orWhere('document_number', $digits)
            ->first();
    }
};
?>

<div class="bg-white rounded-xl shadow p-6 space-y-4 mt-6">
    <div class="text-center space-y-2">
        <span class="inline-flex items-center justify-center h-12 w-12 rounded-full bg-azul/10 text-azul">
            @svg($step === 'document' ? 'heroicon-o-lock-closed' : 'heroicon-o-device-phone-mobile', 'h-5 w-5')
        </span>
        <h1 class="text-xl font-semibold">{{ $step === 'document' ? 'Ingresar' : 'Revisa tu celular' }}</h1>
        @if ($step === 'code')
            <p class="text-sm text-muted">Enviamos un código de 4 dígitos al celular terminado en <span class="font-medium text-ink">{{ substr($maskedPhone, -4) }}</span>.</p>
        @endif
    </div>

    @if ($step === 'document')
        <form wire:submit="sendCode" class="space-y-4">
            <div>
                <label for="document" class="block text-sm font-medium">Número de documento</label>
                <div class="relative mt-1">
                    @svg('heroicon-o-identification', 'h-4 w-4 text-steel absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none')
                    <input id="document" type="text" inputmode="numeric" autocomplete="username" wire:model="document" class="w-full rounded px-3 py-2 pl-9 border border-black/15 focus:border-azul focus:ring-1 focus:ring-azul focus:outline-none" autofocus>
                </div>
                @error('document') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>

            <x-submit-button target="sendCode" loading-text="Enviando código…" class="w-full rounded-brand bg-azul text-white py-2.5 font-medium border-2 border-transparent transition-all duration-500 hover:bg-white hover:text-azul hover:border-azul">Recibir código por SMS</x-submit-button>
        </form>
    @else
        <form wire:submit="verify" class="space-y-4">
            <div>
                <label for="code" class="block text-sm font-medium">Código de verificación</label>
                <input id="code" type="text" inputmode="numeric" autocomplete="one-time-code" maxlength="4" pattern="[0-9]*" wire:model="code" class="mt-1 w-full rounded px-3 py-2 border border-black/15 text-center text-2xl tracking-[0.5em] tabular-nums focus:border-azul focus:ring-1 focus:ring-azul focus:outline-none" autofocus>
                @error('code') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
                @error('document') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>

            <x-submit-button target="verify" loading-text="Verificando…" class="w-full rounded-brand bg-azul text-white py-2.5 font-medium border-2 border-transparent transition-all duration-500 hover:bg-white hover:text-azul hover:border-azul">Ingresar</x-submit-button>
        </form>

        <div class="flex items-center justify-between text-sm">
            <button type="button" wire:click="backToDocument" class="text-muted underline">Cambiar documento</button>
            <button type="button" wire:click="resend" wire:loading.attr="disabled" class="text-azul underline">Reenviar código</button>
        </div>
    @endif

    <p class="text-sm text-center text-muted">
        ¿No tienes cuenta? <a href="{{ route('portal.register') }}" wire:navigate class="underline">Regístrate aquí</a>
    </p>
</div>
