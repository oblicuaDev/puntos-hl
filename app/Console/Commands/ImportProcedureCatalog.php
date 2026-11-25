<?php

namespace App\Console\Commands;

use App\Models\Procedure;
use App\Models\Reward;
use App\Models\RewardCategory;
use App\Models\RewardImage;
use App\Services\Media\ImageUploadService;
use App\Services\Points\PointRates;
use App\Support\Catalog\HlProcedureCatalog;
use Illuminate\Console\Command;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

/**
 * Carga/actualiza el catálogo real de procedimientos redimibles
 * (HlProcedureCatalog) como premios del portal y como procedimientos del
 * admin (para asociarlos a ventas). Idempotente: se puede correr varias
 * veces; actualiza precio, textos y categoría, y solo adjunta imagen a los
 * premios que no tengan una (o a todos con --refresh-images).
 *
 * Retira los premios/procedimientos de demo que este catálogo reemplaza:
 * se borran si nunca se usaron, o se desactivan si tienen canjes/ventas.
 */
class ImportProcedureCatalog extends Command
{
    protected $signature = 'catalog:import-procedures
        {--refresh-images : Reemplaza la imagen de los premios que ya tienen una}
        {--keep-demo : No retira los premios/procedimientos de demo}
        {--force : No pedir confirmación}';

    protected $description = 'Carga el catálogo real de procedimientos de H&L (precios, descripciones e imágenes).';

    public function handle(ImageUploadService $uploader): int
    {
        $items = HlProcedureCatalog::items();

        $this->table(
            ['Procedimiento', 'Categoría', 'Valor COP', 'Puntos (tasa actual)'],
            collect($items)->map(fn ($i) => [
                $i['title'],
                HlProcedureCatalog::CATEGORIES[$i['category']],
                '$'.number_format($i['price_cop'], 0, ',', '.'),
                number_format(PointRates::pointsNeededFor($i['price_cop']), 0, ',', '.'),
            ])
        );
        $this->line('Tasa de redención: '.PointRates::redemptionPointsPer100Cop().' puntos por cada $100 COP.');

        if (! $this->option('force') && ! $this->confirm('¿Cargar/actualizar estos '.count($items).' procedimientos?')) {
            return self::SUCCESS;
        }

        $rewards = DB::transaction(function () use ($items) {
            $categories = $this->upsertCategories();
            $rewards = [];

            foreach ($items as $item) {
                $slug = str($item['title'])->slug()->toString();

                $reward = Reward::withTrashed()->firstOrNew(['slug' => $slug]);
                $reward->fill([
                    'category_id' => $categories[$item['category']]->id,
                    'title' => $item['title'],
                    'short_description' => $item['short_description'],
                    'body_html' => HlProcedureCatalog::bodyHtml($item),
                    'price_cop' => $item['price_cop'],
                    'stock' => null,
                    'is_active' => true,
                    'is_featured' => (bool) ($item['featured'] ?? false),
                ]);
                $reward->deleted_at = null;
                $reward->save();

                // Procedimiento del admin (ventas). points_price es referencial: se
                // recalcula en cada corrida con la tasa de redención vigente.
                Procedure::updateOrCreate(
                    ['name' => $item['title']],
                    [
                        'description' => $item['short_description'],
                        'points_price' => PointRates::pointsNeededFor($item['price_cop']),
                        'is_active' => true,
                    ]
                );

                $rewards[] = [$reward, $item];
            }

            if (! $this->option('keep-demo')) {
                $this->retireDemoCatalog();
            }

            return $rewards;
        });

        $this->attachImages($rewards, $uploader);

        $this->info('Catálogo de procedimientos cargado: '.count($rewards).' premios.');

        return self::SUCCESS;
    }

    /** @return array<string, RewardCategory> */
    private function upsertCategories(): array
    {
        $categories = [];
        $order = 1; // "Descuentos y Bonos" queda de primera (order 0) si existe.

        foreach (HlProcedureCatalog::CATEGORIES as $slug => $name) {
            $category = RewardCategory::where('slug', $slug)->orWhere('name', $name)->first() ?? new RewardCategory;
            $category->fill(['name' => $name, 'slug' => $slug, 'order' => $order++, 'is_active' => true])->save();
            $categories[$slug] = $category;
        }

        return $categories;
    }

    private function retireDemoCatalog(): void
    {
        $deleted = $deactivated = 0;

        foreach (Reward::whereIn('title', HlProcedureCatalog::RETIRED_DEMO_REWARD_TITLES)->get() as $reward) {
            if ($reward->redemptions()->exists()) {
                $reward->update(['is_active' => false]);
                $deactivated++;

                continue;
            }

            foreach ($reward->images as $image) {
                Storage::disk('public')->delete($image->path);
            }
            $reward->forceDelete();
            $deleted++;
        }

        foreach (Procedure::whereIn('name', HlProcedureCatalog::RETIRED_DEMO_PROCEDURE_NAMES)->get() as $procedure) {
            if ($procedure->sales()->exists()) {
                $procedure->update(['is_active' => false]);
                $deactivated++;
            } else {
                $procedure->delete();
                $deleted++;
            }
        }

        $this->line("Catálogo de demo retirado: {$deleted} borrados, {$deactivated} desactivados (tenían canjes/ventas).");
    }

    private function attachImages(array $rewards, ImageUploadService $uploader): void
    {
        $attached = 0;

        foreach ($rewards as [$reward, $item]) {
            $existing = $reward->images()->get();

            if ($existing->isNotEmpty() && ! $this->option('refresh-images')) {
                continue;
            }

            $tmpPath = $this->fetchImage($item['image']);

            if (! $tmpPath) {
                $this->error("Sin imagen para «{$reward->title}» ({$item['image']}).");

                continue;
            }

            try {
                $file = new UploadedFile($tmpPath, basename($tmpPath), null, null, true);
                $path = $uploader->store($file, 'rewards');

                foreach ($existing as $image) {
                    Storage::disk('public')->delete($image->path);
                    $image->delete();
                }

                RewardImage::create([
                    'reward_id' => $reward->id,
                    'path' => $path,
                    'order' => 0,
                    'alt_text' => $reward->title,
                ]);
                $attached++;
            } finally {
                if (str_starts_with($item['image'], 'wp:')) {
                    @unlink($tmpPath);
                }
            }
        }

        $this->line("Imágenes adjuntadas: {$attached}.");
    }

    /** Devuelve una ruta local legible con la imagen fuente, o null si no se pudo obtener. */
    private function fetchImage(string $source): ?string
    {
        [$kind, $ref] = explode(':', $source, 2);

        if ($kind === 'file') {
            $path = resource_path('catalog/procedimientos/'.$ref);

            return is_file($path) ? $path : null;
        }

        $response = Http::timeout(30)->retry(2, 500, throw: false)
            ->withHeaders(['User-Agent' => 'Mozilla/5.0'])->get($ref);

        if (! $response->successful()) {
            return null;
        }

        $base = tempnam(sys_get_temp_dir(), 'hl-img-');
        $tmp = $base.'.'.pathinfo(parse_url($ref, PHP_URL_PATH), PATHINFO_EXTENSION);
        @unlink($base);
        file_put_contents($tmp, $response->body());

        return $tmp;
    }
}
