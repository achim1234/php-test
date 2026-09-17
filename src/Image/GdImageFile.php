<?php

declare(strict_types=1);

namespace App\Image;

use GdImage;

final class GdImageFile
{
    public function load(string $path): ?LoadedImage
    {
        if (!is_file($path)) {
            return null;
        }

        $info = getimagesize($path);
        if ($info === false) {
            return null;
        }

        $mimeType = $info['mime'];
        $image = match ($mimeType) {
            'image/jpeg' => imagecreatefromjpeg($path) ?: null,
            'image/png' => imagecreatefrompng($path) ?: null,
            default => null,
        };

        if ($image === null) {
            return null;
        }

        if (!imageistruecolor($image)) {
            imagepalettetotruecolor($image);
        }

        return new LoadedImage($image, $mimeType);
    }

    public function save(GdImage $image, string $path, string $mimeType): bool
    {
        return match ($mimeType) {
            'image/png' => imagepng($image, $path, 6),
            default => imagejpeg($image, $path, 100),
        };
    }

    public function mimeTypeForPath(string $path): string
    {
        return strtolower(pathinfo($path, PATHINFO_EXTENSION)) === 'png'
            ? 'image/png'
            : 'image/jpeg';
    }
}
