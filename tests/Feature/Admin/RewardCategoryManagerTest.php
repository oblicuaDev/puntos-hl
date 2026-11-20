<?php

namespace Tests\Feature\Admin;

use App\Models\AdminUser;
use App\Models\RewardCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class RewardCategoryManagerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(AdminUser::create([
            'wp_user_id' => 1,
            'email' => 'admin@harkerlloreda.com',
            'display_name' => 'Admin',
            'role' => 'admin',
            'is_active' => true,
        ]), 'admin');
    }

    public function test_creates_a_category_with_an_auto_generated_slug(): void
    {
        Livewire::test('admin.reward-category-manager')
            ->set('name', 'Tratamientos Faciales')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('reward_categories', [
            'name' => 'Tratamientos Faciales',
            'slug' => 'tratamientos-faciales',
        ]);
    }

    public function test_supports_a_parent_category(): void
    {
        $parent = RewardCategory::create(['name' => 'Facial', 'slug' => 'facial']);

        Livewire::test('admin.reward-category-manager')
            ->set('name', 'Limpieza profunda')
            ->set('parent_id', $parent->id)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('reward_categories', ['name' => 'Limpieza profunda', 'parent_id' => $parent->id]);
    }

    public function test_deletes_a_category(): void
    {
        $category = RewardCategory::create(['name' => 'Borrar', 'slug' => 'borrar']);

        Livewire::test('admin.reward-category-manager')->call('delete', $category->id);

        $this->assertDatabaseMissing('reward_categories', ['id' => $category->id]);
    }
}
