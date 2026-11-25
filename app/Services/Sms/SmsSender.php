<?php

namespace App\Services\Sms;

interface SmsSender
{
    /**
     * @param  string  $to  número en formato E.164 (+573001234567)
     *
     * @throws SmsDeliveryException si el proveedor rechaza el envío
     */
    public function send(string $to, string $message): void;
}
