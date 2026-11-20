<?php

namespace Tests\Feature\Admin;

use App\Models\AdminUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleAccessTest extends TestCase
{
    use RefreshDatabase;

    private function adminUser(string $role): AdminUser
    {
        return AdminUser::create([
            'wp_user_id' => $role === 'admin' ? 1 : 2,
            'email' => $role.'@harkerlloreda.com',
            'display_name' => ucfirst($role),
            'role' => $role,
            'is_active' => true,
        ]);
    }

    public function test_guest_is_redirected_to_sso_start(): void
    {
        $this->get('/admin')->assertRedirect(route('admin.sso.start'));
    }

    public function test_asesor_can_access_patients_and_sales(): void
    {
        $this->actingAs($this->adminUser('asesor'), 'admin');

        $this->get('/admin/patients')->assertOk();
        $this->get('/admin/sales')->assertOk();
    }

    public function test_asesor_cannot_access_admin_only_sections(): void
    {
        $this->actingAs($this->adminUser('asesor'), 'admin');

        $this->get('/admin/procedures')->assertForbidden();
        $this->get('/admin/settings')->assertForbidden();
    }

    public function test_admin_can_access_everything(): void
    {
        $this->actingAs($this->adminUser('admin'), 'admin');

        $this->get('/admin/patients')->assertOk();
        $this->get('/admin/sales')->assertOk();
        $this->get('/admin/procedures')->assertOk();
        $this->get('/admin/settings')->assertOk();
    }

    public function test_inactive_admin_user_is_redirected(): void
    {
        $admin = $this->adminUser('admin');
        $admin->update(['is_active' => false]);

        $this->actingAs($admin, 'admin');

        $this->get('/admin')->assertRedirect(route('admin.sso.start'));
    }
}
