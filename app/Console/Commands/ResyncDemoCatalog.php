<?php

namespace App\Console\Commands;

use App\Models\Banner;
use App\Models\Reward;
use App\Models\RewardCategory;
use Database\Seeders\DemoCatalogSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Reemplaza el catálogo de premios de demo (categorías/premios genéricos de
 * spa) por el catálogo real de H&L definido en DemoCatalogSeeder::rewardCatalog(),
 * y los banners de demo viejos por los definidos en DemoCatalogSeeder::bannerCatalog().
 *
 * A diferencia de DemoCatalogSeeder (que solo corre una vez, en una base de
 * datos vacía), este comando está pensado para un ambiente donde el catálogo
 * viejo YA existe (p. ej. producción) y hay que migrarlo al nuevo contenido.
 * Es idempotente: se puede correr varias veces sin duplicar ni volver a
 * tocar lo que ya quedó igual al catálogo objetivo.
 */
class ResyncDemoCatalog extends Command
{
    protected $signature = 'catalog:resync-demo {--force : No pedir confirmación}';

    protected $description = 'Reemplaza las categorías/premios de demo viejos por el catálogo real de H&L.';

    public function handle(): int
    {
        $catalog = DemoCatalogSeeder::rewardCatalog();
        $targetNames = array_keys($catalog);

        $staleCategories = RewardCategory::whereNotIn('name', $targetNames)->get();
        $rewardIdsToDelete = Reward::whereIn('category_id', $staleCategories->pluck('id'))->pluck('id');

        foreach ($catalog as $categoryName => $rewards) {
            $category = RewardCategory::where('name', $categoryName)->first();

            if (! $category) {
                continue;
            }

            $desiredTitles = array_column($rewards, 'title');

            $staleRewardIds = Reward::where('category_id', $category->id)
                ->whereNotIn('title', $desiredTitles)
                ->pluck('id');

            $rewardIdsToDelete = $rewardIdsToDelete->merge($staleRewardIds);
        }

        $blockedByRedemptions = DB::table('redemptions')
            ->whereIn('reward_id', $rewardIdsToDelete)
            ->distinct()
            ->pluck('reward_id');

        if ($blockedByRedemptions->isNotEmpty()) {
            $this->error('No se puede continuar: hay canjes reales sobre premios que se iban a reemplazar (reward_id: '.$blockedByRedemptions->join(', ').'). Resuélvelo manualmente antes de re-correr este comando.');

            return self::FAILURE;
        }

        $newCategoryNames = collect($targetNames)->diff(RewardCategory::pluck('name'));

        $bannerCatalog = DemoCatalogSeeder::bannerCatalog();
        $desiredBannerPaths = collect($bannerCatalog)->map(fn ($b) => 'banners/demo-'.$b['source']);
        $staleBanners = Banner::where('image_path', 'like', 'banners/demo-%')
            ->whereNotIn('image_path', $desiredBannerPaths)
            ->get();

        $this->line('Categorías a borrar: '.($staleCategories->pluck('name')->implode(', ') ?: '(ninguna)'));
        $this->line('Categorías nuevas a crear: '.($newCategoryNames->implode(', ') ?: '(ninguna)'));
        $this->line('Premios a borrar: '.$rewardIdsToDelete->count());
        $this->line('Banners de demo viejos a reemplazar: '.($staleBanners->pluck('title')->implode(', ') ?: '(ninguno)'));

        if (! $this->option('force') && ! $this->confirm('¿Continuar y reemplazar el catálogo de premios en esta base de datos?')) {
            return self::SUCCESS;
        }

        DB::transaction(function () use ($catalog, $staleCategories, $rewardIdsToDelete, $bannerCatalog, $staleBanners) {
            Reward::whereIn('id', $rewardIdsToDelete)->get()->each(fn (Reward $reward) => $reward->forceDelete());

            RewardCategory::whereIn('id', $staleCategories->pluck('id'))->delete();

            $order = 0;

            foreach ($catalog as $categoryName => $rewards) {
                $category = RewardCategory::updateOrCreate(
                    ['name' => $categoryName],
                    ['slug' => str($categoryName)->slug(), 'order' => $order, 'is_active' => true]
                );

                $order++;

                foreach ($rewards as $index => $reward) {
                    Reward::updateOrCreate(
                        ['category_id' => $category->id, 'title' => $reward['title']],
                        [
                            'slug' => str($reward['title'])->slug().'-'.$category->id,
                            'short_description' => $reward['short_description'],
                            'body_html' => '<p>'.$reward['short_description'].'</p>',
                            'price_cop' => $reward['price_cop'],
                            'stock' => null,
                            'is_active' => true,
                            'is_featured' => $index === 0,
                        ]
                    );
                }
            }

            foreach ($staleBanners as $banner) {
                Storage::disk('public')->delete($banner->image_path);
                $banner->delete();
            }

            foreach ($bannerCatalog as $banner) {
                $path = 'banners/demo-'.$banner['source'];
                $sourcePath = public_path('img/marketing/'.$banner['source']);

                if (! file_exists($sourcePath)) {
                    continue;
                }

                if (! Storage::disk('public')->exists($path)) {
                    Storage::disk('public')->put($path, file_get_contents($sourcePath));
                }

                Banner::updateOrCreate(
                    ['image_path' => $path],
                    ['title' => $banner['title'], 'link_url' => route('portal.rewards.index'), 'order' => $banner['order'], 'is_active' => true]
                );
            }
        });

        $this->info('Catálogo de premios y banners de demo resincronizados con el contenido real de H&L.');

        return self::SUCCESS;
    }
}
