<?php

namespace Tests\Feature\Admin;

use App\Models\AdminUser;
use App\Models\Reward;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class RewardManagerTest extends TestCase
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

    public function test_creates_a_reward_with_an_auto_generated_slug(): void
    {
        Livewire::test('admin.reward-manager')
            ->set('title', 'Botox Facial')
            ->set('price_cop', 250000)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('rewards', [
            'title' => 'Botox Facial',
            'slug' => 'botox-facial',
            'price_cop' => 250000,
        ]);
    }

    public function test_requires_title_and_price(): void
    {
        Livewire::test('admin.reward-manager')
            ->set('title', '')
            ->set('price_cop', null)
            ->call('save')
            ->assertHasErrors(['title', 'price_cop']);
    }

    public function test_uploads_and_reencodes_an_image(): void
    {
        Livewire::test('admin.reward-manager')
            ->set('title', 'Premio con foto')
            ->set('price_cop', 100000)
            ->set('newImages', [UploadedFile::fake()->image('foto.jpg', 800, 600)])
            ->call('save')
            ->assertHasNoErrors();

        $reward = Reward::where('title', 'Premio con foto')->firstOrFail();

        $this->assertCount(1, $reward->images);
        $this->assertStringEndsWith('.webp', $reward->images->first()->path);
        Storage::disk('public')->assertExists($reward->images->first()->path);
    }

    public function test_rejects_a_non_image_upload(): void
    {
        Livewire::test('admin.reward-manager')
            ->set('title', 'Premio inválido')
            ->set('price_cop', 100000)
            ->set('newImages', [UploadedFile::fake()->create('archivo.pdf', 100)])
            ->call('save')
            ->assertHasErrors(['newImages.0']);
    }

    public function test_edits_an_existing_reward(): void
    {
        $reward = Reward::create(['slug' => 'original', 'title' => 'Original', 'price_cop' => 100000]);

        Livewire::test('admin.reward-manager')
            ->call('edit', $reward->id)
            ->set('title', 'Actualizado')
            ->set('price_cop', 150000)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('rewards', ['id' => $reward->id, 'title' => 'Actualizado', 'price_cop' => 150000]);
    }

    public function test_deletes_a_reward(): void
    {
        $reward = Reward::create(['slug' => 'borrar', 'title' => 'Borrar', 'price_cop' => 100000]);

        Livewire::test('admin.reward-manager')->call('delete', $reward->id);

        $this->assertSoftDeleted('rewards', ['id' => $reward->id]);
    }
}
