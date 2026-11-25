<?php

namespace App\Services\Sms;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Envío real por sms.to (POST /sms/send, autenticado con API key como
 * Bearer token). La API key se genera en el panel de sms.to; nunca se usa
 * la contraseña de la cuenta.
 */
class SmsToSender implements SmsSender
{
    public function send(string $to, string $message): void
    {
        $apiKey = config('services.smsto.api_key');

        if (! $apiKey) {
            throw new SmsDeliveryException('SMSTO_API_KEY no está configurada.');
        }

        $payload = array_filter([
            'to' => $to,
            'message' => $message,
            'sender_id' => config('services.smsto.sender_id'),
        ]);

        $response = Http::withToken($apiKey)
            ->acceptJson()
            ->timeout(15)
            ->post(rtrim(config('services.smsto.base_url'), '/').'/sms/send', $payload);

        if (! $response->successful() || $response->json('success') === false) {
            // Nunca se registra el cuerpo del mensaje: contiene el código OTP.
            Log::warning('sms.to rechazó un envío', [
                'status' => $response->status(),
                'error' => $response->json('message'),
                'to_suffix' => substr($to, -4),
            ]);

            throw new SmsDeliveryException('No se pudo enviar el SMS (HTTP '.$response->status().').');
        }
    }
}
