<?php

namespace Tests\Feature\Admin;

use App\Models\AdminUser;
use App\Models\Patient;
use App\Models\Procedure;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SaleManagerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(SettingsService::class)->set('cop_per_point', 100, 'decimal');
    }

    private function admin(): AdminUser
    {
        return AdminUser::create([
            'wp_user_id' => 1,
            'email' => 'admin@harkerlloreda.com',
            'display_name' => 'Admin',
            'role' => 'admin',
            'is_active' => true,
        ]);
    }

    public function test_registers_a_sale_and_awards_points_to_the_patient(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin, 'admin');

        $patient = Patient::create([
            'document_number' => '1234',
            'first_name' => 'Ana',
            'last_name' => 'Pérez',
            'password' => '1234',
        ]);

        Livewire::test('admin.sale-manager')
            ->call('selectPatient', $patient->id)
            ->set('amount_cop', 50000)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('sales', [
            'patient_id' => $patient->id,
            'admin_user_id' => $admin->id,
            'amount_cop' => 50000,
            'points_awarded' => 500, // 50000 / 100
        ]);

        $this->assertSame(500, $patient->pointsBalance());
    }

    public function test_cannot_save_without_selecting_a_patient(): void
    {
        $this->actingAs($this->admin(), 'admin');

        Livewire::test('admin.sale-manager')
            ->set('amount_cop', 10000)
            ->call('save')
            ->assertHasErrors(['patient_id']);

        $this->assertDatabaseCount('sales', 0);
    }

    public function test_patient_search_finds_matches_by_document_or_name(): void
    {
        $this->actingAs($this->admin(), 'admin');

        $match = Patient::create([
            'document_number' => '9999999',
            'first_name' => 'Camila',
            'last_name' => 'Torres',
            'password' => '9999999',
        ]);

        Patient::create([
            'document_number' => '1111111',
            'first_name' => 'Otro',
            'last_name' => 'Paciente',
            'password' => '1111111',
        ]);

        $component = Livewire::test('admin.sale-manager')
            ->set('patientSearch', 'Camila');

        $this->assertTrue($component->get('patientMatches')->contains('id', $match->id));
        $this->assertCount(1, $component->get('patientMatches'));
    }

    public function test_sale_can_reference_a_procedure(): void
    {
        $this->actingAs($this->admin(), 'admin');

        $patient = Patient::create([
            'document_number' => '555',
            'first_name' => 'Luis',
            'last_name' => 'Gómez',
            'password' => '555',
        ]);

        $procedure = Procedure::create(['name' => 'Limpieza', 'points_price' => 100]);

        Livewire::test('admin.sale-manager')
            ->call('selectPatient', $patient->id)
            ->set('procedure_id', $procedure->id)
            ->set('amount_cop', 20000)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('sales', [
            'patient_id' => $patient->id,
            'procedure_id' => $procedure->id,
        ]);
    }
}
