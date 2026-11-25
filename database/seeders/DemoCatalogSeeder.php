<?php

namespace Database\Seeders;

use App\Models\Banner;
use App\Models\Reward;
use App\Models\RewardCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

/**
 * Catálogo de premios (bonos + procedimientos reales de H&L) y banners del
 * home, para que las ventas y redenciones de prueba tengan algo real con qué
 * relacionarse. No crea duplicados si ya existe catálogo.
 */
class DemoCatalogSeeder extends Seeder
{
    public function run(): void
    {
        if (RewardCategory::where('slug', 'descuentos-y-bonos')->doesntExist()) {
            $this->seedRewards();
        }

        if (Banner::where('image_path', 'banners/demo-promo-bonos-1.webp')->doesntExist()) {
            $this->seedBanners();
        }

        // Catálogo real de procedimientos (precios, descripciones e imágenes).
        $this->command->call('catalog:import-procedures', ['--force' => true]);
    }

    /**
     * Bonos de regalo (no son procedimientos). Los procedimientos reales los
     * carga `catalog:import-procedures` desde App\Support\Catalog\HlProcedureCatalog.
     */
    public static function rewardCatalog(): array
    {
        return [
            'Descuentos y Bonos' => [
                ['title' => 'Bono de regalo $50.000', 'short_description' => 'Bono para usar en cualquier procedimiento.', 'price_cop' => 50000],
                ['title' => 'Bono de regalo $100.000', 'short_description' => 'Bono para usar en cualquier procedimiento.', 'price_cop' => 100000],
                ['title' => 'Bono de regalo $200.000', 'short_description' => 'Bono para usar en cualquier procedimiento.', 'price_cop' => 200000],
            ],
        ];
    }

    private function seedRewards(): void
    {
        $categories = self::rewardCatalog();

        $order = 0;

        foreach ($categories as $categoryName => $rewards) {
            $category = RewardCategory::create([
                'name' => $categoryName,
                'slug' => str($categoryName)->slug(),
                'order' => $order++,
                'is_active' => true,
            ]);

            foreach ($rewards as $index => $reward) {
                Reward::create([
                    'category_id' => $category->id,
                    'slug' => str($reward['title'])->slug().'-'.$category->id,
                    'title' => $reward['title'],
                    'short_description' => $reward['short_description'],
                    'body_html' => '<p>'.$reward['short_description'].'</p>',
                    'price_cop' => $reward['price_cop'],
                    'stock' => null,
                    'is_active' => true,
                    'is_featured' => $index === 0,
                ]);
            }
        }
    }

    /**
     * Banners de demo del home.
     *
     * Son banners de diseño (no fotografías), pensados para comunicar algo
     * concreto del plan de puntos (bonos de descuento reales del catálogo),
     * no solo una foto de marca sin mensaje.
     */
    public static function bannerCatalog(): array
    {
        return [
            ['source' => 'promo-bonos-1.webp', 'title' => 'Tus puntos valen dinero real', 'order' => 0],
            ['source' => 'promo-bonos-2.webp', 'title' => 'Hasta $200.000 en bonos de descuento', 'order' => 1],
        ];
    }

    /**
     * Copia las imágenes de banner hacia el disco público (los banners se
     * sirven vía Storage::url, no asset()) y crea los registros que
     * alimentan el carrusel del home del portal.
     */
    private function seedBanners(): void
    {
        foreach (self::bannerCatalog() as $banner) {
            $path = 'banners/demo-'.$banner['source'];
            $sourcePath = public_path('img/marketing/'.$banner['source']);

            if (! file_exists($sourcePath)) {
                continue;
            }

            Storage::disk('public')->put($path, file_get_contents($sourcePath));

            Banner::create([
                'title' => $banner['title'],
                'image_path' => $path,
                'link_url' => route('portal.rewards.index'),
                'order' => $banner['order'],
                'is_active' => true,
            ]);
        }
    }
}
