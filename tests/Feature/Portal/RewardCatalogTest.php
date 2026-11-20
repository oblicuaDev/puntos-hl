<?php

namespace Tests\Feature\Portal;

use App\Models\Patient;
use App\Models\PointBatch;
use App\Models\Reward;
use App\Models\RewardCategory;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class RewardCatalogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(SettingsService::class)->set('cop_per_point', 100, 'decimal');

        $patient = Patient::create([
            'document_number' => '999',
            'first_name' => 'Ana',
            'last_name' => 'Pérez',
            'password' => '999',
            'must_change_password' => false,
        ]);

        PointBatch::create([
            'patient_id' => $patient->id,
            'points_original' => 100,
            'points_remaining' => 100,
            'earned_at' => now(),
            'expires_at' => now()->addMonths(12),
            'status' => 'active',
        ]);

        $this->actingAs($patient, 'patient');
    }

    public function test_only_shows_active_rewards(): void
    {
        Reward::create(['slug' => 'activo', 'title' => 'Activo', 'price_cop' => 10000, 'is_active' => true]);
        Reward::create(['slug' => 'inactivo', 'title' => 'Inactivo', 'price_cop' => 10000, 'is_active' => false]);

        $component = Livewire::test('portal.reward-catalog');

        $titles = $component->get('rewards')->pluck('title');
        $this->assertTrue($titles->contains('Activo'));
        $this->assertFalse($titles->contains('Inactivo'));
    }

    public function test_filters_by_category(): void
    {
        $facial = RewardCategory::create(['name' => 'Facial', 'slug' => 'facial']);
        $corporal = RewardCategory::create(['name' => 'Corporal', 'slug' => 'corporal']);

        Reward::create(['slug' => 'f1', 'title' => 'Facial 1', 'price_cop' => 10000, 'category_id' => $facial->id]);
        Reward::create(['slug' => 'c1', 'title' => 'Corporal 1', 'price_cop' => 10000, 'category_id' => $corporal->id]);

        $titles = Livewire::test('portal.reward-catalog')
            ->set('categoryId', $facial->id)
            ->get('rewards')
            ->pluck('title');

        $this->assertTrue($titles->contains('Facial 1'));
        $this->assertFalse($titles->contains('Corporal 1'));
    }

    public function test_only_affordable_filter_respects_patient_balance(): void
    {
        // El paciente tiene 100 puntos = 10,000 COP a la tasa configurada.
        Reward::create(['slug' => 'barato', 'title' => 'Barato', 'price_cop' => 5000]);
        Reward::create(['slug' => 'caro', 'title' => 'Caro', 'price_cop' => 50000]);

        $titles = Livewire::test('portal.reward-catalog')
            ->set('onlyAffordable', true)
            ->get('rewards')
            ->pluck('title');

        $this->assertTrue($titles->contains('Barato'));
        $this->assertFalse($titles->contains('Caro'));
    }

    public function test_searches_by_title(): void
    {
        Reward::create(['slug' => 'botox', 'title' => 'Botox Facial', 'price_cop' => 10000]);
        Reward::create(['slug' => 'limpieza', 'title' => 'Limpieza Dental', 'price_cop' => 10000]);

        $titles = Livewire::test('portal.reward-catalog')
            ->set('search', 'Botox')
            ->get('rewards')
            ->pluck('title');

        $this->assertTrue($titles->contains('Botox Facial'));
        $this->assertFalse($titles->contains('Limpieza Dental'));
    }
}
