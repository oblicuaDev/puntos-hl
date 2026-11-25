<?php

namespace Tests\Feature\Portal;

use App\Models\Patient;
use App\Models\PatientLoginCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\FakesSms;
use Tests\TestCase;

class LoginFormTest extends TestCase
{
    use FakesSms, RefreshDatabase;

    private function patient(array $overrides = []): Patient
    {
        return Patient::create(array_merge([
            'document_number' => '1010101010',
            'first_name' => 'Ana',
            'last_name' => 'Pérez',
            'phone' => '+573001112233',
            'password' => 'no-se-usa',
        ], $overrides));
    }

    public function test_sends_a_code_and_logs_in_with_it(): void
    {
        $this->patient();

        $component = Livewire::test('portal.login-form')
            ->set('document', '1010101010')
            ->call('sendCode')
            ->assertHasNoErrors()
            ->assertSet('step', 'code');

        $this->assertCount(1, $this->sentSms);
        $this->assertSame('+573001112233', $this->sentSms[0]['to']);

        $component->set('code', $this->lastSmsCode())
            ->call('verify')
            ->assertRedirect(route('portal.rewards.index'));

        $this->assertAuthenticated('patient');
    }

    public function test_code_is_not_stored_in_plain_text(): void
    {
        $this->patient();

        Livewire::test('portal.login-form')->set('document', '1010101010')->call('sendCode');

        $record = PatientLoginCode::sole();
        $this->assertNotSame($this->lastSmsCode(), $record->code_hash);
        $this->assertSame(64, strlen($record->code_hash));
    }

    public function test_rejects_a_wrong_code(): void
    {
        $this->patient();

        Livewire::test('portal.login-form')
            ->set('document', '1010101010')
            ->call('sendCode')
            ->set('code', $this->lastSmsCode() === '0000' ? '1111' : '0000')
            ->call('verify')
            ->assertHasErrors(['code']);

        $this->assertGuest('patient');
    }

    public function test_unknown_document_gets_a_clear_message_and_no_sms(): void
    {
        Livewire::test('portal.login-form')
            ->set('document', '999')
            ->call('sendCode')
            ->assertHasErrors(['document'])
            ->assertSet('step', 'document');

        $this->assertCount(0, $this->sentSms);
    }

    public function test_patient_without_phone_is_told_to_contact_the_clinic(): void
    {
        $this->patient(['phone' => null]);

        Livewire::test('portal.login-form')
            ->set('document', '1010101010')
            ->call('sendCode')
            ->assertHasErrors(['document']);

        $this->assertCount(0, $this->sentSms);
    }

    public function test_blocked_patient_cannot_login(): void
    {
        $this->patient(['status' => 'blocked']);

        Livewire::test('portal.login-form')
            ->set('document', '1010101010')
            ->call('sendCode')
            ->assertHasErrors(['document']);

        $this->assertCount(0, $this->sentSms);
        $this->assertGuest('patient');
    }
}
