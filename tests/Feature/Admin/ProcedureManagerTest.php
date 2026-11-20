<?php

namespace Tests\Feature\Admin;

use App\Models\AdminUser;
use App\Models\Procedure;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ProcedureManagerTest extends TestCase
{
    use RefreshDatabase;

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

    public function test_creates_a_procedure(): void
    {
        $this->actingAs($this->admin(), 'admin');

        Livewire::test('admin.procedure-manager')
            ->set('name', 'Limpieza dental')
            ->set('points_price', 500)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('procedures', [
            'name' => 'Limpieza dental',
            'points_price' => 500,
            'is_active' => true,
        ]);
    }

    public function test_requires_name_and_points_price(): void
    {
        $this->actingAs($this->admin(), 'admin');

        Livewire::test('admin.procedure-manager')
            ->set('name', '')
            ->set('points_price', null)
            ->call('save')
            ->assertHasErrors(['name', 'points_price']);
    }

    public function test_edits_an_existing_procedure(): void
    {
        $this->actingAs($this->admin(), 'admin');

        $procedure = Procedure::create(['name' => 'Original', 'points_price' => 100]);

        Livewire::test('admin.procedure-manager')
            ->call('edit', $procedure->id)
            ->set('name', 'Actualizado')
            ->set('points_price', 200)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('procedures', ['id' => $procedure->id, 'name' => 'Actualizado', 'points_price' => 200]);
        $this->assertDatabaseCount('procedures', 1);
    }

    public function test_deletes_a_procedure(): void
    {
        $this->actingAs($this->admin(), 'admin');

        $procedure = Procedure::create(['name' => 'Borrar', 'points_price' => 100]);

        Livewire::test('admin.procedure-manager')
            ->call('delete', $procedure->id);

        $this->assertDatabaseMissing('procedures', ['id' => $procedure->id]);
    }
}
