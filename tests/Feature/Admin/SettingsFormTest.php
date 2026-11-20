<?php

namespace Tests\Feature\Admin;

use App\Models\AdminUser;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SettingsFormTest extends TestCase
{
    use RefreshDatabase;

    public function test_updates_general_settings(): void
    {
        $admin = AdminUser::create([
            'wp_user_id' => 1,
            'email' => 'admin@harkerlloreda.com',
            'display_name' => 'Admin',
            'role' => 'admin',
            'is_active' => true,
        ]);
        $this->actingAs($admin, 'admin');

        Livewire::test('admin.settings-form')
            ->set('cop_per_point', 120)
            ->set('min_points_percentage', 15)
            ->set('points_expiration_months', 10)
            ->set('abandoned_checkout_timeout_minutes', 45)
            ->set('management_notification_email', 'otra@harkerlloreda.com')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('saved', true);

        $settings = app(SettingsService::class);
        $this->assertEquals(120, $settings->get('cop_per_point'));
        $this->assertEquals(15, $settings->get('min_points_percentage'));
        $this->assertEquals(10, $settings->get('points_expiration_months'));
        $this->assertEquals(45, $settings->get('abandoned_checkout_timeout_minutes'));
        $this->assertEquals('otra@harkerlloreda.com', $settings->get('management_notification_email'));
    }

    public function test_rejects_an_invalid_email(): void
    {
        $admin = AdminUser::create([
            'wp_user_id' => 1,
            'email' => 'admin@harkerlloreda.com',
            'display_name' => 'Admin',
            'role' => 'admin',
            'is_active' => true,
        ]);
        $this->actingAs($admin, 'admin');

        Livewire::test('admin.settings-form')
            ->set('management_notification_email', 'no-es-un-correo')
            ->call('save')
            ->assertHasErrors(['management_notification_email']);
    }
}
