<?php

namespace Tests\Feature\Admin;

use App\Models\AdminUser;
use App\Models\Patient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class PatientManagerTest extends TestCase
{
    use RefreshDatabase;

    private function adminUser(string $role = 'admin'): AdminUser
    {
        return AdminUser::create([
            'wp_user_id' => $role === 'admin' ? 1 : 2,
            'email' => $role.'@harkerlloreda.com',
            'display_name' => ucfirst($role),
            'role' => $role,
            'is_active' => true,
        ]);
    }

    public function test_registers_a_patient_with_the_document_number_as_initial_password(): void
    {
        $admin = $this->adminUser();
        $this->actingAs($admin, 'admin');

        Livewire::test('admin.patient-manager')
            ->set('document_number', '1122334455')
            ->set('first_name', 'Ana')
            ->set('last_name', 'Pérez')
            ->set('phone', '3001234567')
            ->call('save')
            ->assertHasNoErrors();

        $patient = Patient::where('document_number', '1122334455')->first();

        $this->assertNotNull($patient);
        $this->assertTrue(Hash::check('1122334455', $patient->password));
        $this->assertTrue($patient->must_change_password);
        $this->assertSame('manual_admin', $patient->registration_source);
        $this->assertSame($admin->id, $patient->created_by_admin_user_id);
        $this->assertSame('active', $patient->status);
    }

    public function test_asesor_can_also_register_patients(): void
    {
        $this->actingAs($this->adminUser('asesor'), 'admin');

        Livewire::test('admin.patient-manager')
            ->set('document_number', '9988776655')
            ->set('first_name', 'Luis')
            ->set('last_name', 'Gómez')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('patients', ['document_number' => '9988776655']);
    }

    public function test_document_number_must_be_unique(): void
    {
        $this->actingAs($this->adminUser(), 'admin');

        Patient::create([
            'document_number' => '555',
            'first_name' => 'Existe',
            'last_name' => 'Ya',
            'password' => '555',
        ]);

        Livewire::test('admin.patient-manager')
            ->set('document_number', '555')
            ->set('first_name', 'Otro')
            ->set('last_name', 'Paciente')
            ->call('save')
            ->assertHasErrors(['document_number']);
    }

    public function test_editing_a_patient_does_not_change_their_password(): void
    {
        $this->actingAs($this->adminUser(), 'admin');

        $patient = Patient::create([
            'document_number' => '777',
            'first_name' => 'Carla',
            'last_name' => 'Ruiz',
            'password' => '777',
        ]);
        $originalHash = $patient->password;

        Livewire::test('admin.patient-manager')
            ->call('edit', $patient->id)
            ->set('phone', '3009999999')
            ->call('save')
            ->assertHasNoErrors();

        $patient->refresh();
        $this->assertSame('3009999999', $patient->phone);
        $this->assertSame($originalHash, $patient->password);
    }

    public function test_toggle_status_blocks_and_reactivates_a_patient(): void
    {
        $this->actingAs($this->adminUser(), 'admin');

        $patient = Patient::create([
            'document_number' => '333',
            'first_name' => 'Jorge',
            'last_name' => 'Lopez',
            'password' => '333',
        ]);

        $component = Livewire::test('admin.patient-manager');

        $component->call('toggleStatus', $patient->id);
        $this->assertSame('blocked', $patient->refresh()->status);

        $component->call('toggleStatus', $patient->id);
        $this->assertSame('active', $patient->refresh()->status);
    }
}
