<?php

namespace App\Services\Checkout;

use App\Models\Redemption;
use App\Services\Payments\PaymentInitiationResult;

class CheckoutOutcome
{
    public function __construct(
        public readonly Redemption $redemption,
        public readonly ?PaymentInitiationResult $paymentInitiation,
    ) {}

    public function requiresPayment(): bool
    {
        return $this->paymentInitiation !== null;
    }
}
