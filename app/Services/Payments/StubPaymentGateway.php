<?php

namespace App\Services\Payments;

use App\Models\Payment;
use App\Models\Redemption;
use Illuminate\Http\Request;

/**
 * Pasarela simulada activa mientras no haya credenciales reales de Payzen
 * (PAYMENTS_DRIVER=stub en .env). Permite construir y probar todo el flujo
 * de checkout mixto (aprobado/rechazado) sin ninguna integración externa.
 */
class StubPaymentGateway implements PaymentGatewayContract
{
    public function initiate(Redemption $redemption, Payment $payment): PaymentInitiationResult
    {
        return new PaymentInitiationResult(
            mode: 'embedded',
            url: route('portal.checkout.stub.show', $payment),
        );
    }

    public function handleCallback(Request $request): PaymentResult
    {
        $payment = Payment::findOrFail($request->input('payment_id'));
        $approved = $request->boolean('approve');

        return new PaymentResult(
            paymentId: $payment->id,
            approved: $approved,
            providerReference: 'STUB-'.$payment->id.'-'.now()->timestamp,
            rawResponse: ['stub' => true, 'approved' => $approved],
        );
    }
}
