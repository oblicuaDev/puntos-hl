<?php

namespace Tests\Feature\Admin;

use App\Models\AdminUser;
use App\Models\Banner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class BannerManagerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->actingAs(AdminUser::create([
            'wp_user_id' => 1,
            'email' => 'admin@harkerlloreda.com',
            'display_name' => 'Admin',
            'role' => 'admin',
            'is_active' => true,
        ]), 'admin');
    }

    public function test_creates_a_banner_with_a_desktop_image(): void
    {
        Livewire::test('admin.banner-manager')
            ->set('title', 'Promo Navidad')
            ->set('imageDesktop', UploadedFile::fake()->image('banner.jpg', 1600, 500))
            ->call('save')
            ->assertHasNoErrors();

        $banner = Banner::where('title', 'Promo Navidad')->firstOrFail();
        Storage::disk('public')->assertExists($banner->image_path);
    }

    public function test_requires_a_desktop_image_on_create(): void
    {
        Livewire::test('admin.banner-manager')
            ->set('title', 'Sin imagen')
            ->call('save')
            ->assertHasErrors(['imageDesktop']);
    }

    public function test_editing_does_not_require_a_new_image(): void
    {
        $banner = Banner::create(['image_path' => 'banners/existing.webp', 'order' => 0]);

        Livewire::test('admin.banner-manager')
            ->call('edit', $banner->id)
            ->set('title', 'Actualizado')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('banners', ['id' => $banner->id, 'title' => 'Actualizado', 'image_path' => 'banners/existing.webp']);
    }
}
