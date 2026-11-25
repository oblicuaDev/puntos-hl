<?php

namespace App\Support;

class Phone
{
    /**
     * Normaliza un celular a E.164 (+573001234567). Un número colombiano de
     * 10 dígitos que empieza por 3 recibe el indicativo 57. Devuelve null si
     * el número no tiene una longitud plausible (10 a 15 dígitos).
     */
    public static function normalize(?string $raw): ?string
    {
        $digits = preg_replace('/\D/', '', (string) $raw);

        if (strlen($digits) === 10 && str_starts_with($digits, '3')) {
            $digits = '57'.$digits;
        }

        if (strlen($digits) < 10 || strlen($digits) > 15) {
            return null;
        }

        return '+'.$digits;
    }

    /** "+573125378053" → "*** *** 8053", para mostrar a qué número se envió el código. */
    public static function mask(string $phone): string
    {
        return '*** *** '.substr(preg_replace('/\D/', '', $phone), -4);
    }
}
