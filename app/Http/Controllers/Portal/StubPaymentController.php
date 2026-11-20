<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\Checkout\RedemptionService;
use App\Services\Payments\PaymentGatewayContract;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Pantalla local "Aprobar/Rechazar" que simula la respuesta de la pasarela
 * mientras PAYMENTS_DRIVER=stub. No existe en producción con Payzen real.
 */
class StubPaymentController extends Controller
{
    public function show(Payment $payment): View
    {
        $this->authorizeOwnership($payment);

        return view('portal.checkout.stub', ['payment' => $payment]);
    }

    public function decide(Request $request, Payment $payment, RedemptionService $redemptions, PaymentGatewayContract $gateway): RedirectResponse
    {
        $this->authorizeOwnership($payment);

        $request->merge(['payment_id' => $payment->id]);
        $result = $gateway->handleCallback($request);

        if ($result->approved) {
            $redemptions->approve($payment->redemption);
        } else {
            $redemptions->reject($payment->redemption);
        }

        return redirect()->route('portal.checkout.success', ['code' => $payment->redemption->code]);
    }

    private function authorizeOwnership(Payment $payment): void
    {
        if ($payment->redemption->patient_id !== Auth::guard('patient')->id()) {
            abort(403);
        }
    }
}
