<?php

namespace App\Services\Payments;

use App\Models\Payment;
use App\Models\Redemption;
use Illuminate\Http\Request;

/**
 * Contrato único para cualquier pasarela de pago del checkout mixto
 * (puntos + dinero). Hoy implementado por StubPaymentGateway; cuando
 * Katherine entregue credenciales de Payzen, PayzenGateway implementa
 * este mismo contrato y solo se cambia el binding en AppServiceProvider,
 * sin tocar RedemptionService ni la UI.
 */
interface PaymentGatewayContract
{
    /** Inicia el cobro y devuelve lo que la vista necesita para continuar (form token, URL de redirect, etc.). */
    public function initiate(Redemption $redemption, Payment $payment): PaymentInitiationResult;

    /**
     * Procesa una notificación/callback entrante de la pasarela (IPN o
     * retorno del navegador) y devuelve el resultado ya validado.
     * Debe validar siempre la firma/autenticidad antes de confiar en el payload.
     */
    public function handleCallback(Request $request): PaymentResult;
}
