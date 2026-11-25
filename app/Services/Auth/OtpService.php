<?php

namespace App\Services\Auth;

use App\Models\Patient;
use App\Models\PatientLoginCode;
use App\Services\Sms\SmsDeliveryException;
use App\Services\Sms\SmsSender;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Códigos de verificación de 4 dígitos por SMS (HL-25).
 *
 * - El código nunca se guarda en texto plano (HMAC-SHA256 con la APP_KEY).
 * - Vence a los TTL_MINUTES; admite MAX_ATTEMPTS intentos fallidos.
 * - Para reenviar hay que esperar RESEND_COOLDOWN_SECONDS, con un máximo de
 *   MAX_SENDS_PER_HOUR envíos por número (protege el saldo de SMS y evita
 *   abuso).
 * - Pedir un código nuevo invalida los anteriores del mismo número.
 */
class OtpService
{
    public const TTL_MINUTES = 5;

    public const MAX_ATTEMPTS = 5;

    public const RESEND_COOLDOWN_SECONDS = 60;

    public const MAX_SENDS_PER_HOUR = 5;

    public function __construct(private SmsSender $sms) {}

    /** Genera, guarda y envía un código. Devuelve los segundos de vigencia. */
    public function send(string $phone, string $purpose, ?Patient $patient = null): int
    {
        $this->ensureCanSend($phone, $purpose);

        $code = str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);

        $record = DB::transaction(function () use ($phone, $purpose, $patient, $code) {
            PatientLoginCode::where('phone', $phone)->where('purpose', $purpose)
                ->whereNull('consumed_at')->update(['consumed_at' => now()]);

            return PatientLoginCode::create([
                'patient_id' => $patient?->id,
                'purpose' => $purpose,
                'phone' => $phone,
                'code_hash' => $this->hash($phone, $code),
                'expires_at' => now()->addMinutes(self::TTL_MINUTES),
                'ip' => request()?->ip(),
            ]);
        });

        try {
            $this->sms->send($phone, "Tu código de acceso a Puntos Harker Lloreda es {$code}. Vence en ".self::TTL_MINUTES.' minutos. No lo compartas con nadie.');
        } catch (SmsDeliveryException $e) {
            // No cuenta para el límite de reenvíos si el SMS nunca salió.
            $record->delete();
            Log::error('No se pudo enviar el código OTP: '.$e->getMessage());

            throw new OtpException('No pudimos enviar el SMS en este momento. Intenta de nuevo en unos minutos o comunícate con la clínica.');
        }

        return self::TTL_MINUTES * 60;
    }

    /** Valida el código; si es correcto lo marca como usado. Lanza OtpException si no. */
    public function verify(string $phone, string $purpose, string $code): PatientLoginCode
    {
        $record = PatientLoginCode::where('phone', $phone)->where('purpose', $purpose)
            ->whereNull('consumed_at')->latest('id')->first();

        if (! $record) {
            throw new OtpException('Solicita un código nuevo.');
        }

        if ($record->expires_at->isPast()) {
            throw new OtpException('El código venció. Solicita uno nuevo.');
        }

        if ($record->attempts >= self::MAX_ATTEMPTS) {
            throw new OtpException('Superaste el número de intentos. Solicita un código nuevo.');
        }

        $record->increment('attempts');

        if (! preg_match('/^\d{4}$/', $code) || ! hash_equals($record->code_hash, $this->hash($phone, $code))) {
            $left = self::MAX_ATTEMPTS - $record->attempts;

            throw new OtpException($left > 0
                ? "El código es incorrecto. Te quedan {$left} intento(s)."
                : 'Superaste el número de intentos. Solicita un código nuevo.');
        }

        $record->update(['consumed_at' => now()]);

        return $record;
    }

    /** Segundos que faltan para poder pedir otro código (0 si ya se puede). */
    public function secondsUntilResend(string $phone, string $purpose): int
    {
        $last = PatientLoginCode::where('phone', $phone)->where('purpose', $purpose)->latest('id')->first();

        if (! $last) {
            return 0;
        }

        return max(0, self::RESEND_COOLDOWN_SECONDS - (int) $last->created_at->diffInSeconds(now()));
    }

    private function ensureCanSend(string $phone, string $purpose): void
    {
        $wait = $this->secondsUntilResend($phone, $purpose);

        if ($wait > 0) {
            throw new OtpException("Espera {$wait} segundos para solicitar un nuevo código.");
        }

        $sentLastHour = PatientLoginCode::where('phone', $phone)
            ->where('created_at', '>=', now()->subHour())->count();

        if ($sentLastHour >= self::MAX_SENDS_PER_HOUR) {
            throw new OtpException('Solicitaste demasiados códigos. Intenta de nuevo en una hora o comunícate con la clínica.');
        }
    }

    private function hash(string $phone, string $code): string
    {
        return hash_hmac('sha256', $phone.'|'.$code, (string) config('app.key'));
    }
}
