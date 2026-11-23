<?php

namespace App\Console\Commands;

use App\Models\Reward;
use App\Models\RewardImage;
use App\Services\Media\ImageUploadService;
use Illuminate\Console\Command;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;

/**
 * Descarga las fotos reales de cada procedimiento desde el WordPress de H&L
 * (campos ACF de hl_list_proced, no "featured media") y las adjunta como
 * RewardImage, re-codificadas por ImageUploadService igual que una subida
 * manual desde el admin. Idempotente: no toca un premio que ya tenga imagen.
 */
class AttachCatalogImages extends Command
{
    protected $signature = 'catalog:attach-wp-images';

    protected $description = 'Adjunta a cada premio su foto real tomada del WordPress de H&L.';

    /** title del Reward => URL de la imagen en webadmin.harkerlloreda.com */
    private function imagesByRewardTitle(): array
    {
        return [
            'Rejuvenecimiento facial con láser Spectra' => 'https://webadmin.harkerlloreda.com/wp-content/uploads/2024/04/REJUVENECIMIENTO-FACIAL-CON-LASER-destacada.webp',
            'Restauración de volumen facial' => 'https://webadmin.harkerlloreda.com/wp-content/uploads/2024/04/RESTAURACION-VOLUMEN-FACIAL-destacada.webp',
            'Fotorejuvenecimiento con luz pulsada' => 'https://webadmin.harkerlloreda.com/wp-content/uploads/2024/04/FOTOREJUVENECIMIENTO-destacada.webp',
            'Lifting facial no quirúrgico' => 'https://webadmin.harkerlloreda.com/wp-content/uploads/2024/04/LIFTING-FACIAL-NO-QUIRURGICO-02.webp',
            'Drenaje linfático' => 'https://webadmin.harkerlloreda.com/wp-content/uploads/2024/04/DRENAJE-LINFATICO-destacada.webp',
            'Masaje post operatorio' => 'https://webadmin.harkerlloreda.com/wp-content/uploads/2024/04/MASAJES-POST-OPERATORIOS-destacada.webp',
            'Remodelación corporal con radiofrecuencia' => 'https://webadmin.harkerlloreda.com/wp-content/uploads/2024/04/REMODELACION-CORPORAL-destacada.webp',
            // Musculptech no tiene foto propia en H&L; el propio sitio reutiliza la de Remodelación corporal.
            'Musculptech' => 'https://webadmin.harkerlloreda.com/wp-content/uploads/2024/04/REMODELACION-CORPORAL-destacada-prevencion-e1744303667953.webp',
            'Peeling facial' => 'https://webadmin.harkerlloreda.com/wp-content/uploads/2024/04/PEELING-FACIAL-destacada.webp',
            'Hollywood Peel' => 'https://webadmin.harkerlloreda.com/wp-content/uploads/2024/04/HOLLYWOOD-PEEL-destacada.webp',
            'Microdermoabrasión' => 'https://webadmin.harkerlloreda.com/wp-content/uploads/2024/04/MICRODERMOABRASION-destacada-e1744301119605.webp',
            'Mesoterapia facial polirevitalizante' => 'https://webadmin.harkerlloreda.com/wp-content/uploads/2024/04/MESOTERAPIA-FACIAL-POLIREVITTALIZANTE-destacada-e1744302380604.webp',
            'Regeneración capilar con PRP' => 'https://webadmin.harkerlloreda.com/wp-content/uploads/2024/04/PLASMA-CAPILAR-DESGTACADA.webp',
        ];
    }

    public function handle(): int
    {
        $uploader = app(ImageUploadService::class);
        $attached = 0;
        $skipped = 0;

        foreach ($this->imagesByRewardTitle() as $title => $url) {
            $reward = Reward::where('title', $title)->first();

            if (! $reward) {
                $this->warn("No existe un premio con título «{$title}», se omite.");

                continue;
            }

            if ($reward->images()->exists()) {
                $skipped++;

                continue;
            }

            $response = Http::timeout(30)->withHeaders(['User-Agent' => 'Mozilla/5.0'])->get($url);

            if (! $response->successful()) {
                $this->error("No se pudo descargar la imagen de «{$title}» ({$url}): HTTP {$response->status()}.");

                continue;
            }

            $tmpPath = tempnam(sys_get_temp_dir(), 'wp-img-').'.webp';
            file_put_contents($tmpPath, $response->body());

            try {
                $uploadedFile = new UploadedFile($tmpPath, basename($url), 'image/webp', null, true);

                RewardImage::create([
                    'reward_id' => $reward->id,
                    'path' => $uploader->store($uploadedFile, 'rewards'),
                    'order' => 0,
                    'alt_text' => $reward->title,
                ]);

                $attached++;
            } finally {
                @unlink($tmpPath);
            }
        }

        $this->info("Imágenes adjuntadas: {$attached}. Premios ya con imagen (sin tocar): {$skipped}.");

        return self::SUCCESS;
    }
}
