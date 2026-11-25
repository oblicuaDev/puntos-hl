<?php

namespace Tests\Concerns;

use App\Services\Sms\SmsSender;

/**
 * Reemplaza el envío de SMS por un registro en memoria ($this->sentSms) para
 * poder leer el código OTP enviado. Se activa solo con usar el trait.
 */
trait FakesSms
{
    /** @var array<int, array{to: string, message: string}> */
    protected array $sentSms = [];

    protected function setUpFakesSms(): void
    {
        $this->sentSms = [];
        $test = $this;

        $this->app->instance(SmsSender::class, new class($test) implements SmsSender
        {
            public function __construct(private $test) {}

            public function send(string $to, string $message): void
            {
                $this->test->recordSms($to, $message);
            }
        });
    }

    public function recordSms(string $to, string $message): void
    {
        $this->sentSms[] = ['to' => $to, 'message' => $message];
    }

    protected function lastSmsCode(): string
    {
        preg_match('/\b(\d{4})\b/', end($this->sentSms)['message'], $m);

        return $m[1];
    }
}
