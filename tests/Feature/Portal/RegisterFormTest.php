<?php

namespace Tests\Feature\Portal;

use App\Models\Patient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\FakesSms;
use Tests\TestCase;

class RegisterFormTest extends TestCase
{
    use FakesSms, RefreshDatabase;

    private function filledForm(string $document = '2020202020', string $email = 'camila@example.com')
    {
        return Livewire::test('portal.register-form')
            ->set('document_number', $document)
            ->set('first_name', 'Camila')
            ->set('last_name', 'Rojas')
            ->set('email', $email)
            ->set('phone', '300 123 4567');
    }

    public function test_registers_only_after_verifying_the_phone_by_sms(): void
    {
        $component = $this->filledForm()
            ->call('sendCode')
            ->assertHasNoErrors()
            ->assertSet('step', 'code');

        $this->assertDatabaseCount('patients', 0);
        $this->assertSame('+573001234567', $this->sentSms[0]['to']);

        $component->set('code', $this->lastSmsCode())
            ->call('register')
            ->assertRedirect(route('portal.dashboard'));

        $this->assertAuthenticated('patient');

        $patient = Patient::where('document_number', '2020202020')->sole();
        $this->assertSame('self_registered', $patient->registration_source);
        $this->assertSame('+573001234567', $patient->phone);
        $this->assertFalse($patient->must_change_password);
    }

    public function test_wrong_code_does_not_create_the_account(): void
    {
        $this->filledForm()
            ->call('sendCode')
            ->set('code', $this->lastSmsCode() === '0000' ? '1111' : '0000')
            ->call('register')
            ->assertHasErrors(['code']);

        $this->assertDatabaseCount('patients', 0);
        $this->assertGuest('patient');
    }

    public function test_requires_a_valid_phone(): void
    {
        $this->filledForm()
            ->set('phone', '123')
            ->call('sendCode')
            ->assertHasErrors(['phone']);

        $this->assertCount(0, $this->sentSms);
    }

    public function test_document_number_must_be_unique(): void
    {
        Patient::create(['document_number' => '4040404040', 'first_name' => 'X', 'last_name' => 'Y', 'password' => 'x']);

        $this->filledForm('4040404040', 'otro@example.com')
            ->call('sendCode')
            ->assertHasErrors(['document_number']);

        $this->assertCount(0, $this->sentSms);
    }
}
