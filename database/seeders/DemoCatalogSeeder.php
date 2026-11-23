<?php

namespace Database\Seeders;

use App\Models\Banner;
use App\Models\Procedure;
use App\Models\Reward;
use App\Models\RewardCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

/**
 * Catálogo mínimo (procedimientos, premios y banners del home) necesario
 * para que las ventas y redenciones de prueba tengan algo real con qué
 * relacionarse. No crea duplicados si ya existe catálogo.
 */
class DemoCatalogSeeder extends Seeder
{
    public function run(): void
    {
        // Comprueba por nombre/slug propios del seed, no por conteo: así no
        // se salta el catálogo de demo en un ambiente que ya tenga registros
        // de prueba previos (p. ej. un "Pro 1" / "Premio 1" de smoke test).
        if (Procedure::where('name', 'Rinoplastia')->doesntExist()) {
            foreach ($this->procedures() as $procedure) {
                Procedure::create($procedure);
            }
        }

        if (RewardCategory::where('slug', 'descuentos-y-bonos')->doesntExist()) {
            $this->seedRewards();
        }

        if (Banner::where('image_path', 'banners/demo-hero-bienestar.webp')->doesntExist()) {
            $this->seedBanners();
        }
    }

    private function procedures(): array
    {
        return [
            ['name' => 'Rinoplastia', 'description' => 'Cirugía estética de nariz.', 'points_price' => 35000, 'is_active' => true],
            ['name' => 'Liposucción', 'description' => 'Contorno corporal por liposucción.', 'points_price' => 40000, 'is_active' => true],
            ['name' => 'Aumento de busto', 'description' => 'Mamoplastia de aumento.', 'points_price' => 45000, 'is_active' => true],
            ['name' => 'Lifting facial', 'description' => 'Rejuvenecimiento facial quirúrgico.', 'points_price' => 38000, 'is_active' => true],
            ['name' => 'Blefaroplastia', 'description' => 'Cirugía de párpados.', 'points_price' => 18000, 'is_active' => true],
            ['name' => 'Abdominoplastia', 'description' => 'Cirugía de contorno abdominal.', 'points_price' => 42000, 'is_active' => true],
            ['name' => 'Toxina botulínica', 'description' => 'Tratamiento antiedad facial.', 'points_price' => 3500, 'is_active' => true],
            ['name' => 'Rellenos dérmicos', 'description' => 'Ácido hialurónico facial.', 'points_price' => 4200, 'is_active' => true],
            ['name' => 'Mesoterapia', 'description' => 'Tratamiento facial/corporal con microinyecciones.', 'points_price' => 2200, 'is_active' => true],
            ['name' => 'Carboxiterapia', 'description' => 'Tratamiento corporal con CO2.', 'points_price' => 1800, 'is_active' => true],
        ];
    }

    private function seedRewards(): void
    {
        $categories = [
            'Descuentos y Bonos' => [
                ['title' => 'Bono de regalo $50.000', 'short_description' => 'Bono para usar en cualquier procedimiento.', 'price_cop' => 50000],
                ['title' => 'Bono de regalo $100.000', 'short_description' => 'Bono para usar en cualquier procedimiento.', 'price_cop' => 100000],
                ['title' => 'Bono de regalo $200.000', 'short_description' => 'Bono para usar en cualquier procedimiento.', 'price_cop' => 200000],
            ],
            'Spa & Bienestar' => [
                ['title' => 'Masaje relajante 60 min', 'short_description' => 'Sesión de masaje de relajación.', 'price_cop' => 160000],
                ['title' => 'Sesión de drenaje linfático', 'short_description' => 'Drenaje linfático manual.', 'price_cop' => 220000],
                ['title' => 'Manicure y pedicure spa', 'short_description' => 'Ritual completo de manos y pies.', 'price_cop' => 90000],
            ],
            'Procedimientos Faciales' => [
                ['title' => 'Limpieza facial profunda', 'short_description' => 'Limpieza facial con extracción.', 'price_cop' => 180000],
                ['title' => 'Hidratación con ácido hialurónico', 'short_description' => 'Hidratación facial profunda.', 'price_cop' => 350000],
                ['title' => 'Peeling químico', 'short_description' => 'Renovación celular facial.', 'price_cop' => 280000],
                ['title' => 'Radiofrecuencia facial', 'short_description' => 'Tensado de piel por radiofrecuencia.', 'price_cop' => 300000],
            ],
            'Dermocosmética' => [
                ['title' => 'Kit de skincare premium', 'short_description' => 'Rutina facial completa de marca propia.', 'price_cop' => 150000],
                ['title' => 'Depilación láser (zona pequeña)', 'short_description' => 'Sesión de depilación láser.', 'price_cop' => 190000],
            ],
        ];

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
     * Copia las mismas fotografías de marca usadas en el home/registro hacia
     * el disco público (los banners se sirven vía Storage::url, no asset())
     * y crea los registros que alimentan el carrusel del home del portal.
     */
    private function seedBanners(): void
    {
        $banners = [
            ['source' => 'hero-bienestar.webp', 'title' => 'Tus procedimientos ahora suman puntos', 'order' => 0],
            ['source' => 'hero-corporal.webp', 'title' => 'Conoce el catálogo de premios', 'order' => 1],
        ];

        foreach ($banners as $banner) {
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
