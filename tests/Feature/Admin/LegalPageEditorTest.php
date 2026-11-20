<?php

namespace Tests\Feature\Admin;

use App\Models\AdminUser;
use App\Models\LegalPage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class LegalPageEditorTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_the_legal_page_on_first_save(): void
    {
        $admin = AdminUser::create([
            'wp_user_id' => 1,
            'email' => 'admin@harkerlloreda.com',
            'display_name' => 'Admin',
            'role' => 'admin',
            'is_active' => true,
        ]);
        $this->actingAs($admin, 'admin');

        Livewire::test('admin.legal-page-editor')
            ->set('title', 'Términos y condiciones')
            ->set('body_html', '<p>Contenido</p>')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('saved', true);

        $this->assertDatabaseHas('legal_pages', [
            'slug' => 'terminos-y-condiciones',
            'title' => 'Términos y condiciones',
            'updated_by_admin_user_id' => $admin->id,
        ]);
    }

    public function test_loads_existing_content_on_mount(): void
    {
        LegalPage::create([
            'slug' => 'terminos-y-condiciones',
            'title' => 'T&C existentes',
            'body_html' => '<p>Ya existe</p>',
        ]);

        $this->actingAs(AdminUser::create([
            'wp_user_id' => 1, 'email' => 'a@a.com', 'display_name' => 'A', 'role' => 'admin', 'is_active' => true,
        ]), 'admin');

        Livewire::test('admin.legal-page-editor')
            ->assertSet('title', 'T&C existentes')
            ->assertSet('body_html', '<p>Ya existe</p>');
    }
}
