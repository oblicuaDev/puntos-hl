<?php

namespace Tests\Feature\Console;

use App\Models\Patient;
use App\Models\Procedure;
use App\Models\Redemption;
use App\Models\Reward;
use App\Models\RewardImage;
use App\Services\SettingsService;
use App\Support\Catalog\HlProcedureCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ImportProcedureCatalogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        app(SettingsService::class)->set('redemption_points_per_100_cop', 3, 'decimal');

        // Imagen PNG mínima válida para todas las descargas del WordPress.
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
        Http::fake(['webadmin.harkerlloreda.com/*' => Http::response($png, 200, ['Content-Type' => 'image/png'])]);
    }

    public function test_loads_every_procedure_with_price_image_and_description(): void
    {
        $this->artisan('catalog:import-procedures', ['--force' => true])->assertSuccessful();

        $items = HlProcedureCatalog::items();
        $this->assertCount(27, $items);
        $this->assertSame(27, Reward::where('is_active', true)->count());

        $venus = Reward::where('title', 'Paquete 10 sesiones Venus Legacy (60 min)')->sole();
        $this->assertSame(1270000, (int) $venus->price_cop);
        $this->assertSame(38100, $venus->pointsEquivalent());
        $this->assertStringContainsString('<li>', $venus->body_html);

        $this->assertSame(660000, (int) Reward::where('title', 'Nanopore con Mesocit')->value('price_cop'));
        $this->assertSame(0, Reward::doesntHave('images')->count());
        $this->assertSame(27, Procedure::where('is_active', true)->count());
    }

    public function test_is_idempotent_and_retires_demo_items_safely(): void
    {
        $used = Reward::create(['slug' => 'demo-1', 'title' => 'Hollywood Peel', 'price_cop' => 260000]);
        $unused = Reward::create(['slug' => 'demo-2', 'title' => 'Drenaje linfático', 'price_cop' => 180000]);
        Redemption::forceCreate([
            'code' => 'X', 'patient_id' => Patient::create(['document_number' => '1', 'first_name' => 'A', 'last_name' => 'B', 'password' => '1'])->id,
            'reward_id' => $used->id, 'reward_title_snapshot' => 'Hollywood Peel', 'reward_price_cop_snapshot' => 260000,
            'points_used' => 1, 'money_amount_cop' => 0, 'points_percentage_applied' => 100,
            'payment_method' => 'points_only', 'status' => 'approved',
        ]);

        $this->artisan('catalog:import-procedures', ['--force' => true])->assertSuccessful();
        $this->artisan('catalog:import-procedures', ['--force' => true])->assertSuccessful();

        $this->assertSame(27, Reward::where('is_active', true)->count());
        $this->assertFalse($used->fresh()->is_active);
        $this->assertNull(Reward::withTrashed()->find($unused->id));
        $this->assertSame(27, RewardImage::count());
    }
}
