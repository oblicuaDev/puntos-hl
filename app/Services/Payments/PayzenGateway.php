<?php

namespace App\Services\Payments;

use App\Models\Payment;
use App\Models\Redemption;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Integración real con Payzen/Izipay (Lyra), vía Krypton embebido y su API
 * REST V4 (Charge/CreatePayment).
 *
 * La confirmación de pago SIEMPRE se valida con la notificación
 * servidor-a-servidor (ver handleCallback), nunca con el POST que Krypton
 * manda al navegador (eso es solo feedback visual, ver PayzenCheckoutController).
 *
 * Importante: la API REST firma sus notificaciones con un esquema propio
 * (kr-answer/kr-hash/kr-hash-key), DISTINTO del esquema clásico vads_*
 * +HMAC que usa el formulario V1/V2 del sitio principal (harker_lloreda) —
 * no son intercambiables. Ver https://docs.lyra.com/es/rest/V4.0/api/kb/ipn_usage.html.
 * kr-hash-key indica qué llave usar: "password" (la de API, test/prod) para
 * la IPN, o "sha256_hmac" (la HMAC-SHA-256, test/prod) para el retorno al navegador.
 */
class PayzenGateway implements PaymentGatewayContract
{
    public function initiate(Redemption $redemption, Payment $payment): PaymentInitiationResult
    {
        $shopId = config('services.payzen.shop_id');
        $apiPassword = $this->apiPasswordForCurrentMode();

        if (! $shopId || ! $apiPassword) {
            throw new RuntimeException('PayzenGateway: faltan credenciales (PAYZEN_SHOP_ID / PAYZEN_API_PASSWORD[_TEST]). Usa PAYMENTS_DRIVER=stub mientras tanto.');
        }

        $response = Http::withBasicAuth($shopId, $apiPassword)
            ->post('https://api.payzen.lat/api-payment/V4/Charge/CreatePayment', [
                'amount' => $payment->amount_cop * 100, // centavos
                'currency' => 'COP',
                // orderId es la correlación autoritativa con la notificación
                // (vads_order_id) — nunca un conteo de filas como hace hoy el
                // sitio principal, que genera condiciones de carrera.
                'orderId' => $redemption->code,
                'customer' => [
                    'reference' => (string) $redemption->patient_id,
                ],
                'metadata' => [
                    'redemption_id' => $redemption->id,
                    'payment_id' => $payment->id,
                ],
            ])
            ->throw()
            ->json();

        return new PaymentInitiationResult(
            mode: 'embedded',
            formToken: $response['answer']['formToken'] ?? null,
            // kr-public-key va combinado "shopId:publicKey" (formato que
            // exige el script de Krypton), no el public_key solo. La public
            // key también es distinta por modo TEST/PRODUCTION en Payzen —
            // usar la que no corresponde hace que Krypton rechace toda
            // tarjeta sin siquiera intentarla contra el banco.
            extra: ['kr_public_key' => $shopId.':'.$this->publicKeyForCurrentMode()],
        );
    }

    public function handleCallback(Request $request): PaymentResult
    {
        $krAnswerRaw = (string) $request->input('kr-answer', '');
        $krHash = (string) $request->input('kr-hash', '');
        $krHashKey = (string) $request->input('kr-hash-key', 'password');

        // Payzen firma el string crudo, solo normalizando los '\/' escapados
        // a '/' (igual que el ejemplo oficial de Lyra) — nunca re-serializar
        // el JSON decodificado antes de firmar, el hash no coincidiría.
        $normalized = str_replace('\/', '/', $krAnswerRaw);
        $key = $this->keyForHash($krHashKey);

        if ($krAnswerRaw === '' || ! $key || ! hash_equals(hash_hmac('sha256', $normalized, $key), $krHash)) {
            Log::warning('Payzen: notificación con firma inválida', ['kr-hash-key' => $krHashKey, 'payload' => $request->all()]);

            throw new RuntimeException('Firma de notificación Payzen inválida — posible intento de fraude.');
        }

        $answer = json_decode($normalized, true) ?? [];

        // Se loguea el payload completo (sin datos de tarjeta: Krypton nunca
        // los manda a nuestro servidor) para poder ajustar los nombres de
        // campo exactos si difieren de lo documentado, sin repetir la prueba.
        Log::info('Payzen: notificación válida recibida', ['answer' => $answer]);

        $orderId = data_get($answer, 'orderDetails.orderId') ?? data_get($answer, 'orderId');

        if (! $orderId) {
            throw new RuntimeException('Notificación Payzen válida pero sin orderId reconocible — ver storage/logs para el payload completo.');
        }

        $redemption = Redemption::where('code', $orderId)->firstOrFail();
        $payment = $redemption->payment()->firstOrFail();

        $transaction = data_get($answer, 'transactions.0', $answer);
        $status = data_get($transaction, 'detailedStatus') ?? data_get($transaction, 'status') ?? data_get($answer, 'orderStatus');
        $reference = data_get($transaction, 'uuid') ?? data_get($transaction, 'transactionId') ?? '';

        $approved = in_array($status, ['PAID', 'AUTHORISED', 'CAPTURED', 'ACCEPTED'], true);

        return new PaymentResult(
            paymentId: $payment->id,
            approved: $approved,
            providerReference: (string) $reference,
            rawResponse: $answer,
        );
    }

    /**
     * kr-hash-key dice cuál de las dos llaves se usó para firmar: "password"
     * (la de API REST, test/prod) para la IPN servidor-a-servidor, o
     * "sha256_hmac" (la HMAC-SHA-256, test/prod) para el retorno al navegador.
     */
    private function keyForHash(string $krHashKey): ?string
    {
        $mode = config('services.payzen.mode', 'TEST');

        if ($krHashKey === 'sha256_hmac') {
            return $mode === 'PRODUCTION'
                ? config('services.payzen.hmac_key_prod')
                : config('services.payzen.hmac_key_test');
        }

        return $this->apiPasswordForCurrentMode();
    }

    private function apiPasswordForCurrentMode(): ?string
    {
        $mode = config('services.payzen.mode', 'TEST');

        return $mode === 'PRODUCTION'
            ? config('services.payzen.api_password')
            : (config('services.payzen.api_test_password') ?: config('services.payzen.api_password'));
    }

    private function publicKeyForCurrentMode(): ?string
    {
        $mode = config('services.payzen.mode', 'TEST');

        return $mode === 'PRODUCTION'
            ? config('services.payzen.public_key')
            : (config('services.payzen.public_key_test') ?: config('services.payzen.public_key'));
    }
}
