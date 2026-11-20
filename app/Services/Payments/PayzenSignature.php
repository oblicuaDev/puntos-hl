<?php

namespace App\Services\Payments;

/**
 * Algoritmo de firma HMAC-SHA256 de Payzen/Izipay (Lyra), portado del flujo
 * PSE que ya funciona en producción en harker_lloreda/get/paysignature.php
 * y set/paynotifications.php: ksort de los campos vads_*, concatenación con
 * "+", hash_hmac('sha256', ..., $key, true) + base64.
 */
class PayzenSignature
{
    public static function compute(array $vadsFields, string $hmacKey): string
    {
        ksort($vadsFields);

        $concatenated = implode('+', $vadsFields).'+'.$hmacKey;

        return base64_encode(hash_hmac('sha256', $concatenated, $hmacKey, true));
    }

    public static function verify(array $vadsFields, string $signature, string $hmacKey): bool
    {
        return hash_equals(self::compute($vadsFields, $hmacKey), $signature);
    }
}
