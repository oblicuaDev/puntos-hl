<?php

namespace App\Console\Commands;

use App\Models\Reward;
use App\Models\RewardImage;
use App\Services\Media\ImageUploadService;
use Illuminate\Console\Command;
use Illuminate\Http\UploadedFile;

/**
 * Genera un gráfico genérico de "bono de regalo" (con el isotipo de marca,
 * la paleta oficial y el monto correspondiente) para los premios de la
 * categoría "Descuentos y Bonos", que no tienen foto propia en el
 * WordPress de H&L porque no son contenido real del sitio, solo bonos
 * de valor monetario. Idempotente: no toca un premio que ya tenga imagen.
 */
class GenerateBonoImages extends Command
{
    protected $signature = 'catalog:generate-bono-images';

    protected $description = 'Genera y adjunta un gráfico genérico de bono de regalo a cada premio de "Descuentos y Bonos".';

    private const WIDTH = 1200;

    private const HEIGHT = 900;

    /** title del Reward => monto a mostrar en la tarjeta */
    private function bonosToGenerate(): array
    {
        return [
            'Bono de regalo $50.000' => '$50.000',
            'Bono de regalo $100.000' => '$100.000',
            'Bono de regalo $200.000' => '$200.000',
        ];
    }

    public function handle(): int
    {
        $uploader = app(ImageUploadService::class);
        $fontBold = resource_path('fonts/Poppins-Bold.ttf');
        $fontSemiBold = resource_path('fonts/Poppins-SemiBold.ttf');
        $fontMedium = resource_path('fonts/Poppins-Medium.ttf');
        $isotipoPath = resource_path('brand/isotipo-white.png');

        $attached = 0;
        $skipped = 0;

        foreach ($this->bonosToGenerate() as $title => $amount) {
            $reward = Reward::where('title', $title)->first();

            if (! $reward) {
                $this->warn("No existe un premio con título «{$title}», se omite.");

                continue;
            }

            if ($reward->images()->exists()) {
                $skipped++;

                continue;
            }

            $tmpPath = tempnam(sys_get_temp_dir(), 'bono-card-').'.png';
            $this->renderCard($amount, $fontBold, $fontSemiBold, $fontMedium, $isotipoPath, $tmpPath);

            try {
                $uploadedFile = new UploadedFile($tmpPath, 'bono.png', 'image/png', null, true);

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

        $this->info("Imágenes de bono generadas: {$attached}. Premios ya con imagen (sin tocar): {$skipped}.");

        return self::SUCCESS;
    }

    private function renderCard(string $amount, string $fontBold, string $fontSemiBold, string $fontMedium, string $isotipoPath, string $outputPath): void
    {
        $width = self::WIDTH;
        $height = self::HEIGHT;

        $im = imagecreatetruecolor($width, $height);
        imagesavealpha($im, true);
        imagealphablending($im, true);

        // Gradiente vertical con la paleta de marca: azul -> un tono más oscuro
        // que "marino", para que el texto blanco tenga buen contraste abajo.
        [$r1, $g1, $b1] = [0x33, 0x5C, 0x82]; // --color-azul
        [$r2, $g2, $b2] = [0x1F, 0x2D, 0x38];

        for ($y = 0; $y < $height; $y++) {
            $t = $y / $height;
            $color = imagecolorallocate(
                $im,
                (int) ($r1 + ($r2 - $r1) * $t),
                (int) ($g1 + ($g2 - $g1) * $t),
                (int) ($b1 + ($b2 - $b1) * $t)
            );
            imageline($im, 0, $y, $width, $y, $color);
        }

        // Acentos sutiles de marca (dermo y teal) para dar profundidad sin ruido.
        $dermo = imagecolorallocatealpha($im, 0xF9, 0xD2, 0xA4, 112);
        imagefilledellipse($im, $width - 120, 120, 520, 520, $dermo);
        $teal = imagecolorallocatealpha($im, 0x5E, 0xBB, 0xC0, 118);
        imagefilledellipse($im, -80, $height + 40, 480, 480, $teal);

        $this->drawGiftIcon($im, 90, 260, 180);

        $white = imagecolorallocate($im, 255, 255, 255);
        $muted = imagecolorallocatealpha($im, 255, 255, 255, 40);

        $this->drawTracked($im, 26, $fontSemiBold, 'BONO DE REGALO', 300, $white, $width, 10);
        $this->drawCentered($im, 96, $fontBold, $amount, 450, $white, $width);
        $this->drawCentered($im, 30, $fontMedium, 'Para usar en cualquier procedimiento', 520, $muted, $width);

        $this->drawIsotipo($im, $isotipoPath, $width, $height);

        imagepng($im, $outputPath);
        imagedestroy($im);
    }

    private function drawGiftIcon($im, int $x, int $y, int $size): void
    {
        $icon = imagecreatetruecolor($size, $size);
        imagesavealpha($icon, true);
        $transparent = imagecolorallocatealpha($icon, 0, 0, 0, 127);
        imagefill($icon, 0, 0, $transparent);

        $panel = imagecolorallocate($icon, 255, 255, 255);
        $this->filledRoundRect($icon, 0, 0, $size, $size, 24, $panel);

        $ribbon = imagecolorallocate($icon, 0x33, 0x5C, 0x82);
        imagefilledrectangle($icon, (int) ($size / 2 - 11), 0, (int) ($size / 2 + 11), $size, $ribbon);
        imagefilledrectangle($icon, 0, (int) ($size / 2 - 11), $size, (int) ($size / 2 + 11), $ribbon);

        $cx = $size / 2;
        $cy = $size / 2;
        imagefilledellipse($icon, (int) $cx, (int) $cy, 16, 16, $panel);

        imagecopy($im, $icon, $x, $y, 0, 0, $size, $size);
        imagedestroy($icon);
    }

    private function filledRoundRect($im, $x1, $y1, $x2, $y2, $r, $color): void
    {
        imagefilledrectangle($im, $x1 + $r, $y1, $x2 - $r, $y2, $color);
        imagefilledrectangle($im, $x1, $y1 + $r, $x2, $y2 - $r, $color);
        imagefilledellipse($im, $x1 + $r, $y1 + $r, $r * 2, $r * 2, $color);
        imagefilledellipse($im, $x2 - $r, $y1 + $r, $r * 2, $r * 2, $color);
        imagefilledellipse($im, $x1 + $r, $y2 - $r, $r * 2, $r * 2, $color);
        imagefilledellipse($im, $x2 - $r, $y2 - $r, $r * 2, $r * 2, $color);
    }

    private function drawCentered($im, int $size, string $font, string $text, int $y, int $color, int $width): void
    {
        $box = imagettfbbox($size, 0, $font, $text);
        $textWidth = abs($box[4] - $box[0]);
        $x = (int) (($width - $textWidth) / 2);
        imagettftext($im, $size, 0, $x, $y, $color, $font, $text);
    }

    private function drawTracked($im, int $size, string $font, string $text, int $y, int $color, int $width, int $tracking = 8): void
    {
        $chars = mb_str_split($text);
        $widths = [];
        $totalWidth = 0;

        foreach ($chars as $char) {
            $box = imagettfbbox($size, 0, $font, $char);
            $charWidth = abs($box[4] - $box[0]);
            $widths[] = $charWidth;
            $totalWidth += $charWidth + $tracking;
        }

        $totalWidth -= $tracking;
        $x = ($width - $totalWidth) / 2;

        foreach ($chars as $i => $char) {
            imagettftext($im, $size, 0, (int) $x, $y, $color, $font, $char);
            $x += $widths[$i] + $tracking;
        }
    }

    private function drawIsotipo($im, string $isotipoPath, int $width, int $height): void
    {
        $isoSrc = imagecreatefrompng($isotipoPath);
        $isoSize = 72;

        $iso = imagecreatetruecolor($isoSize, $isoSize);
        imagesavealpha($iso, true);
        imagealphablending($iso, false);
        $transparent = imagecolorallocatealpha($iso, 0, 0, 0, 127);
        imagefill($iso, 0, 0, $transparent);
        imagealphablending($iso, true);
        imagecopyresampled($iso, $isoSrc, 0, 0, 0, 0, $isoSize, $isoSize, imagesx($isoSrc), imagesy($isoSrc));

        imagecopy($im, $iso, (int) (($width - $isoSize) / 2), $height - 130, 0, 0, $isoSize, $isoSize);

        imagedestroy($iso);
        imagedestroy($isoSrc);
    }
}
