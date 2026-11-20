<?php

namespace App\Services\Payments;

use App\Models\Payment;
use App\Models\Redemption;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Integración real con Payzen/Izipay (Lyra), vía Krypton embebido.
 *
 * Pendiente de credenciales reales (Katherine): shop ID, public key, API
 * password y claves HMAC test/producción. Hasta entonces PAYMENTS_DRIVER=stub
 * mantiene activo StubPaymentGateway; este gateway implementa el mismo
 * contrato para que el cambio sea solo de binding (ver AppServiceProvider).
 *
 * A diferencia del flujo "Krypton + paid.php" que corre hoy en el sitio
 * principal (que confía ciegamente en el kr-answer del navegador, sin
 * validar firma), aquí la confirmación de pago SIEMPRE se valida con una
 * notificación servidor-a-servidor firmada (ver handleCallback), usando el
 * mismo algoritmo HMAC-SHA256 ya probado en producción (PayzenSignature).
 */
class PayzenGateway implements PaymentGatewayContract
{
    public function initiate(Redemption $redemption, Payment $payment): PaymentInitiationResult
    {
        $shopId = config('services.payzen.shop_id');
        $apiPassword = config('services.payzen.api_password');

        if (! $shopId || ! $apiPassword) {
            throw new RuntimeException('PayzenGateway: faltan credenciales (PAYZEN_SHOP_ID / PAYZEN_API_PASSWORD). Usa PAYMENTS_DRIVER=stub mientras tanto.');
        }

        $response = Http::withBasicAuth($shopId, $apiPassword)
            ->post('https://api.payzen.lat/api-payment/V4/Charge/CreatePayment', [
                'amount' => $payment->amount_cop * 100, // centavos
                'currency' => 'COP',
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
            extra: ['public_key' => config('services.payzen.public_key')],
        );
    }

    public function handleCallback(Request $request): PaymentResult
    {
        $vadsFields = collect($request->all())
            ->filter(fn ($value, $key) => str_starts_with($key, 'vads_'))
            ->all();

        $mode = $request->input('vads_ctx_mode', config('services.payzen.mode', 'TEST'));
        $hmacKey = $mode === 'PRODUCTION'
            ? config('services.payzen.hmac_key_prod')
            : config('services.payzen.hmac_key_test');

        if (! $hmacKey || ! PayzenSignature::verify($vadsFields, (string) $request->input('signature'), $hmacKey)) {
            throw new RuntimeException('Firma de notificación Payzen inválida — posible intento de fraude.');
        }

        $approved = in_array($request->input('vads_trans_status'), ['AUTHORISED', 'CAPTURED', 'ACCEPTED'], true);

        return new PaymentResult(
            paymentId: (int) $request->input('vads_ext_info_payment_id'),
            approved: $approved,
            providerReference: (string) $request->input('vads_trans_id'),
            rawResponse: $vadsFields,
        );
    }
}
