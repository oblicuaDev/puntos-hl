<?php

namespace Tests\Feature;

use App\Models\PatientLoginCode;
use App\Services\Auth\OtpException;
use App\Services\Auth\OtpService;
use App\Services\Sms\SmsDeliveryException;
use App\Services\Sms\SmsSender;
use App\Services\Sms\SmsToSender;
use App\Support\Phone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\FakesSms;
use Tests\TestCase;

class OtpServiceTest extends TestCase
{
    use FakesSms, RefreshDatabase;

    private const PHONE = '+573001112233';

    private function otp(): OtpService
    {
        return app(OtpService::class);
    }

    private function wrongCode(): string
    {
        return $this->lastSmsCode() === '0000' ? '1111' : '0000';
    }

    public function test_a_code_can_only_be_used_once(): void
    {
        $this->otp()->send(self::PHONE, 'login');
        $code = $this->lastSmsCode();

        $this->otp()->verify(self::PHONE, 'login', $code);

        $this->expectException(OtpException::class);
        $this->otp()->verify(self::PHONE, 'login', $code);
    }

    public function test_code_expires(): void
    {
        $this->otp()->send(self::PHONE, 'login');
        $code = $this->lastSmsCode();

        $this->travel(OtpService::TTL_MINUTES + 1)->minutes();

        $this->expectExceptionMessage('venció');
        $this->otp()->verify(self::PHONE, 'login', $code);
    }

    public function test_locks_after_too_many_wrong_attempts_even_with_the_right_code(): void
    {
        $this->otp()->send(self::PHONE, 'login');
        $code = $this->lastSmsCode();

        for ($i = 0; $i < OtpService::MAX_ATTEMPTS; $i++) {
            try {
                $this->otp()->verify(self::PHONE, 'login', $this->wrongCode());
            } catch (OtpException) {
            }
        }

        $this->expectExceptionMessage('intentos');
        $this->otp()->verify(self::PHONE, 'login', $code);
    }

    public function test_resend_requires_waiting_and_invalidates_the_previous_code(): void
    {
        $this->otp()->send(self::PHONE, 'login');
        $first = $this->lastSmsCode();

        try {
            $this->otp()->send(self::PHONE, 'login');
            $this->fail('Debió exigir esperar antes de reenviar.');
        } catch (OtpException $e) {
            $this->assertStringContainsString('Espera', $e->getMessage());
        }

        $this->travel(OtpService::RESEND_COOLDOWN_SECONDS + 1)->seconds();
        $this->otp()->send(self::PHONE, 'login');

        if ($this->lastSmsCode() !== $first) {
            $this->expectException(OtpException::class);
            $this->otp()->verify(self::PHONE, 'login', $first);
        } else {
            $this->assertSame(1, PatientLoginCode::whereNull('consumed_at')->count());
        }
    }

    public function test_hourly_send_limit(): void
    {
        for ($i = 0; $i < OtpService::MAX_SENDS_PER_HOUR; $i++) {
            $this->otp()->send(self::PHONE, 'login');
            $this->travel(OtpService::RESEND_COOLDOWN_SECONDS + 1)->seconds();
        }

        $this->expectExceptionMessage('demasiados códigos');
        $this->otp()->send(self::PHONE, 'login');
    }

    public function test_failed_sms_delivery_is_reported_and_not_counted(): void
    {
        $this->app->instance(SmsSender::class, new class implements SmsSender
        {
            public function send(string $to, string $message): void
            {
                throw new SmsDeliveryException('caído');
            }
        });

        try {
            $this->otp()->send(self::PHONE, 'login');
            $this->fail('Debió fallar.');
        } catch (OtpException $e) {
            $this->assertStringContainsString('No pudimos enviar', $e->getMessage());
        }

        $this->assertDatabaseCount('patient_login_codes', 0);
    }

    public function test_smsto_driver_posts_to_the_api_with_bearer_key(): void
    {
        config([
            'services.smsto.api_key' => 'test-key',
            'services.smsto.sender_id' => 'HarkerLlor',
        ]);

        Http::fake(['api.sms.to/*' => Http::response(['success' => true, 'message_id' => 'abc'])]);

        (new SmsToSender)->send(self::PHONE, 'hola');

        Http::assertSent(fn ($request) => $request->url() === 'https://api.sms.to/sms/send'
            && $request->hasHeader('Authorization', 'Bearer test-key')
            && $request['to'] === self::PHONE
            && $request['sender_id'] === 'HarkerLlor');
    }

    public function test_phone_normalization(): void
    {
        $this->assertSame('+573001234567', Phone::normalize('300 123 4567'));
        $this->assertSame('+573001234567', Phone::normalize('573001234567'));
        $this->assertSame('+12676294282', Phone::normalize('12676294282'));
        $this->assertNull(Phone::normalize('12345'));
        $this->assertNull(Phone::normalize(null));
    }
}
