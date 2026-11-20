<?php

namespace App\Services\Payments;

/**
 * Lo que la vista de checkout necesita para continuar el pago:
 * - 'redirect': el navegador debe ir a $url (ej. flujo PSE clásico de Payzen).
 * - 'embedded': renderizar un formulario embebido con $formToken (ej. Krypton) o, en el stub, una pantalla local de aprobar/rechazar.
 */
class PaymentInitiationResult
{
    public function __construct(
        public readonly string $mode, // 'redirect' | 'embedded'
        public readonly ?string $url = null,
        public readonly ?string $formToken = null,
        public readonly array $extra = [],
    ) {}
}
