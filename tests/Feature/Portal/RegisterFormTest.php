<?php

namespace Tests\Feature\Portal;

use App\Models\Patient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class RegisterFormTest extends TestCase
{
    use RefreshDatabase;

    public function test_registers_a_patient_and_logs_them_in(): void
    {
        Livewire::test('portal.register-form')
            ->set('document_number', '2020202020')
            ->set('first_name', 'Camila')
            ->set('last_name', 'Rojas')
            ->set('email', 'camila@example.com')
            ->set('password', 'secret123')
            ->set('password_confirmation', 'secret123')
            ->call('register')
            ->assertRedirect(route('portal.dashboard'));

        $this->assertAuthenticated('patient');

        $patient = Patient::where('document_number', '2020202020')->first();
        $this->assertSame('self_registered', $patient->registration_source);
        $this->assertFalse($patient->must_change_password);
    }

    public function test_requires_matching_password_confirmation(): void
    {
        Livewire::test('portal.register-form')
            ->set('document_number', '3030303030')
            ->set('first_name', 'Luis')
            ->set('last_name', 'Gómez')
            ->set('email', 'luis@example.com')
            ->set('password', 'secret123')
            ->set('password_confirmation', 'diferente')
            ->call('register')
            ->assertHasErrors(['password']);
    }

    public function test_document_number_must_be_unique(): void
    {
        Patient::create(['document_number' => '4040404040', 'first_name' => 'X', 'last_name' => 'Y', 'password' => '4040404040']);

        Livewire::test('portal.register-form')
            ->set('document_number', '4040404040')
            ->set('first_name', 'Otro')
            ->set('last_name', 'Paciente')
            ->set('email', 'otro@example.com')
            ->set('password', 'secret123')
            ->set('password_confirmation', 'secret123')
            ->call('register')
            ->assertHasErrors(['document_number']);
    }
}
