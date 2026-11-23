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

    /**
     * Fuente única de verdad del catálogo de premios de demo: la usan tanto
     * este seeder como el comando `catalog:resync-demo` que lo aplica sobre
     * un ambiente (p. ej. producción) que ya tenía el catálogo viejo creado.
     */
    public static function rewardCatalog(): array
    {
        return [
            'Descuentos y Bonos' => [
                ['title' => 'Bono de regalo $50.000', 'short_description' => 'Bono para usar en cualquier procedimiento.', 'price_cop' => 50000],
                ['title' => 'Bono de regalo $100.000', 'short_description' => 'Bono para usar en cualquier procedimiento.', 'price_cop' => 100000],
                ['title' => 'Bono de regalo $200.000', 'short_description' => 'Bono para usar en cualquier procedimiento.', 'price_cop' => 200000],
            ],
            'Procedimientos Faciales' => [
                ['title' => 'Rejuvenecimiento facial con láser Spectra', 'short_description' => 'Tratamiento láser de picosegundos para rejuvenecer la piel del rostro.', 'price_cop' => 280000],
                ['title' => 'Restauración de volumen facial', 'short_description' => 'Relleno con ácido hialurónico para recuperar volumen en el rostro.', 'price_cop' => 350000],
                ['title' => 'Fotorejuvenecimiento con luz pulsada', 'short_description' => 'Tratamiento de luz pulsada intensa para mejorar la textura de la piel.', 'price_cop' => 220000],
                ['title' => 'Lifting facial no quirúrgico', 'short_description' => 'Tensado de piel del rostro con ultrasonido focalizado (Ultraformer).', 'price_cop' => 450000],
            ],
            'Procedimientos Corporales' => [
                ['title' => 'Drenaje linfático', 'short_description' => 'Terapia manual de drenaje linfático post procedimiento.', 'price_cop' => 180000],
                ['title' => 'Masaje post operatorio', 'short_description' => 'Sesión de presoterapia, radiofrecuencia y drenaje manual.', 'price_cop' => 160000],
                ['title' => 'Remodelación corporal con radiofrecuencia', 'short_description' => 'Tratamiento con Venus Legacy para reducir flacidez y grasa localizada.', 'price_cop' => 320000],
                ['title' => 'Musculptech', 'short_description' => 'Estimulación de contracciones musculares con tecnología de 7 teslas.', 'price_cop' => 300000],
            ],
            'Cuidado de la Piel' => [
                ['title' => 'Peeling facial', 'short_description' => 'Renovación celular de la piel del rostro.', 'price_cop' => 200000],
                ['title' => 'Hollywood Peel', 'short_description' => 'Mascarilla de carbón activado con láser Q-switch Nd:YAG.', 'price_cop' => 260000],
                ['title' => 'Microdermoabrasión', 'short_description' => 'Exfoliación profunda de la piel con puntas de diamante.', 'price_cop' => 150000],
                ['title' => 'Mesoterapia facial polirevitalizante', 'short_description' => 'Microinyecciones revitalizantes para la piel del rostro.', 'price_cop' => 230000],
            ],
            'Cuidado Capilar' => [
                ['title' => 'Regeneración capilar con PRP', 'short_description' => 'Plasma rico en plaquetas para estimular el crecimiento del cabello.', 'price_cop' => 280000],
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
