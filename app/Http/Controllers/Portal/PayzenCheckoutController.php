<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\Checkout\RedemptionService;
use App\Services\Payments\PaymentGatewayContract;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Checkout embebido real con Payzen (Krypton). El formToken se calculó una
 * sola vez en RedemptionService::checkout() y viaja por flash session hasta
 * aquí — no se vuelve a llamar a Charge/CreatePayment al mostrar la página.
 *
 * La confirmación autoritativa SIEMPRE llega por `ipn()` (servidor-a-servidor,
 * firmada); el retorno del navegador en `return()` es solo feedback visual.
 */
class PayzenCheckoutController extends Controller
{
    public function show(Payment $payment): View|RedirectResponse
    {
        $this->authorizeOwnership($payment);

        $data = session("payzen_checkout.{$payment->id}");

        if (! $data) {
            return redirect()
                ->route('portal.rewards.show', $payment->redemption->reward)
                ->with('checkoutError', 'Tu sesión de pago expiró. Intenta canjear de nuevo.');
        }

        return view('portal.checkout.payzen', [
            'payment' => $payment,
            'formToken' => $data['formToken'],
            'krPublicKey' => $data['kr_public_key'],
        ]);
    }

    public function return(Payment $payment): View|RedirectResponse
    {
        $this->authorizeOwnership($payment);
        $payment->refresh();

        if ($payment->status === 'approved') {
            return redirect()->route('portal.checkout.success', ['code' => $payment->redemption->code]);
        }

        if ($payment->status === 'declined') {
            return redirect()
                ->route('portal.rewards.show', $payment->redemption->reward)
                ->with('checkoutError', 'El pago no fue aprobado. Puedes intentarlo de nuevo.');
        }

        // El IPN puede tardar unos segundos más que el redirect del navegador.
        return view('portal.checkout.payzen-procesando', ['payment' => $payment]);
    }

    /**
     * Notificación servidor-a-servidor de Payzen. Pública (sin guard de
     * paciente, sin CSRF — ver bootstrap/app.php), autenticada por firma HMAC
     * dentro de PayzenGateway::handleCallback().
     */
    public function ipn(Request $request, RedemptionService $redemptions, PaymentGatewayContract $gateway): Response
    {
        $result = $gateway->handleCallback($request);

        $payment = Payment::findOrFail($result->paymentId);
        $payment->update([
            'provider_reference' => $result->providerReference,
            'raw_response' => $result->rawResponse,
        ]);

        if ($result->approved) {
            $redemptions->approve($payment->redemption);
        } else {
            $redemptions->reject($payment->redemption);
        }

        return response('OK', 200);
    }

    private function authorizeOwnership(Payment $payment): void
    {
        if ($payment->redemption->patient_id !== Auth::guard('patient')->id()) {
            abort(403);
        }
    }
}
