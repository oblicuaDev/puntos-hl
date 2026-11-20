<?php

namespace App\Services\Payments;

class PaymentResult
{
    public function __construct(
        public readonly int $paymentId,
        public readonly bool $approved,
        public readonly string $providerReference,
        public readonly array $rawResponse = [],
    ) {}
}
