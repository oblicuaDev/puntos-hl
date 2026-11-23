<?php

namespace Tests\Feature\Portal;

use App\Models\Patient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class LoginFormTest extends TestCase
{
    use RefreshDatabase;

    private function patient(array $overrides = []): Patient
    {
        return Patient::create(array_merge([
            'document_number' => '1010101010',
            'first_name' => 'Ana',
            'last_name' => 'Pérez',
            'email' => 'ana@example.com',
            'password' => '1010101010',
            'must_change_password' => true,
        ], $overrides));
    }

    public function test_logs_in_with_document_number_and_redirects_to_change_password(): void
    {
        $this->patient();

        Livewire::test('portal.login-form')
            ->set('identifier', '1010101010')
            ->set('password', '1010101010')
            ->call('login')
            ->assertRedirect(route('portal.password.edit'));

        $this->assertAuthenticated('patient');
    }

    public function test_logs_in_with_email_when_password_already_changed(): void
    {
        $this->patient(['must_change_password' => false]);

        Livewire::test('portal.login-form')
            ->set('identifier', 'ana@example.com')
            ->set('password', '1010101010')
            ->call('login')
            ->assertRedirect(route('portal.rewards.index'));
    }

    public function test_rejects_wrong_password(): void
    {
        $this->patient();

        Livewire::test('portal.login-form')
            ->set('identifier', '1010101010')
            ->set('password', 'incorrecta')
            ->call('login')
            ->assertHasErrors(['identifier']);

        $this->assertGuest('patient');
    }

    public function test_blocked_patient_cannot_login(): void
    {
        $this->patient(['status' => 'blocked']);

        Livewire::test('portal.login-form')
            ->set('identifier', '1010101010')
            ->set('password', '1010101010')
            ->call('login')
            ->assertHasErrors(['identifier']);

        $this->assertGuest('patient');
    }
}
