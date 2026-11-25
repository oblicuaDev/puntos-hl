<?php

namespace App\Services\Sms;

use Illuminate\Support\Facades\Log;

/**
 * Driver de desarrollo/pruebas: no envía nada, deja el mensaje en el log
 * (incluido el código OTP). Nunca usar en producción.
 */
class LogSmsSender implements SmsSender
{
    public function send(string $to, string $message): void
    {
        Log::info('[SMS simulado] a '.$to.': '.$message);
    }
}
