<?php

namespace App\Services\Media;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

/**
 * Re-codifica cualquier imagen subida a WebP con un ancho máximo, con
 * nombre de archivo aleatorio (nunca el nombre original del usuario).
 * Esto evita servir el archivo tal cual lo subió el usuario (hardening:
 * descarta metadata/EXIF y cualquier payload no-imagen incrustado) y
 * reduce el peso de las imágenes (Core Web Vitals).
 */
class ImageUploadService
{
    public function store(UploadedFile $file, string $directory, int $maxWidth = 1600): string
    {
        $manager = new ImageManager(new Driver);

        $image = $manager->decodePath($file->getRealPath());

        if ($image->width() > $maxWidth) {
            $image->scaleDown(width: $maxWidth);
        }

        $filename = $directory.'/'.Str::uuid()->toString().'.webp';

        Storage::disk('public')->makeDirectory($directory);
        // El formato de codificación se infiere de la extensión .webp del path.
        $image->save(Storage::disk('public')->path($filename), quality: 82);

        return $filename;
    }
}
