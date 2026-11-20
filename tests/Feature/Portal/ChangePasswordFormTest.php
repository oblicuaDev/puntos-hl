<?php

namespace Tests\Feature\Portal;

use App\Models\Patient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class ChangePasswordFormTest extends TestCase
{
    use RefreshDatabase;

    public function test_changes_the_password_and_clears_the_must_change_flag(): void
    {
        $patient = Patient::create([
            'document_number' => '555',
            'first_name' => 'Ana',
            'last_name' => 'Pérez',
            'password' => '555',
            'must_change_password' => true,
        ]);
        $this->actingAs($patient, 'patient');

        Livewire::test('portal.change-password-form')
            ->set('current_password', '555')
            ->set('password', 'nuevaClave123')
            ->set('password_confirmation', 'nuevaClave123')
            ->call('updatePassword')
            ->assertRedirect(route('portal.dashboard'));

        $patient->refresh();
        $this->assertFalse($patient->must_change_password);
        $this->assertTrue(Hash::check('nuevaClave123', $patient->password));
    }

    public function test_rejects_an_incorrect_current_password(): void
    {
        $patient = Patient::create([
            'document_number' => '666',
            'first_name' => 'Ana',
            'last_name' => 'Pérez',
            'password' => '666',
        ]);
        $this->actingAs($patient, 'patient');

        Livewire::test('portal.change-password-form')
            ->set('current_password', 'incorrecta')
            ->set('password', 'nuevaClave123')
            ->set('password_confirmation', 'nuevaClave123')
            ->call('updatePassword')
            ->assertHasErrors(['current_password']);
    }
}
