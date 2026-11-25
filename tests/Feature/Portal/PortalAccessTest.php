<?php

namespace Tests\Feature\Portal;

use App\Models\Patient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PortalAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login_from_dashboard(): void
    {
        $this->get('/mi-cuenta')->assertRedirect(route('portal.login'));
    }

    public function test_authenticated_patient_can_browse_normally(): void
    {
        $patient = Patient::create([
            'document_number' => '222',
            'first_name' => 'Ana',
            'last_name' => 'Pérez',
            'password' => '222',
            'must_change_password' => false,
        ]);
        $this->actingAs($patient, 'patient');

        $this->get('/mi-cuenta')->assertOk();
        $this->get('/premios')->assertOk();
    }

    public function test_authenticated_patient_cannot_see_login_or_register_pages(): void
    {
        $patient = Patient::create([
            'document_number' => '333',
            'first_name' => 'Ana',
            'last_name' => 'Pérez',
            'password' => '333',
            'must_change_password' => false,
        ]);
        $this->actingAs($patient, 'patient');

        $this->get('/login')->assertRedirect();
        $this->get('/registro')->assertRedirect();
    }
}
