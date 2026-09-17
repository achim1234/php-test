<?php

declare(strict_types=1);

namespace App\Image;

use GdImage;

final class PixelTransformer
{
    /**
     * @param callable(int, int, int): array{int, int, int} $transform
     */
    public function transform(GdImage $image, callable $transform): void
    {
        $width = imagesx($image);
        $height = imagesy($image);
        imagealphablending($image, false);
        imagesavealpha($image, true);

        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                $color = imagecolorat($image, $x, $y);
                [$red, $green, $blue] = $transform(
                    ($color >> 16) & 0xFF,
                    ($color >> 8) & 0xFF,
                    $color & 0xFF,
                );
                $alpha = $color & 0x7F000000;
                imagesetpixel($image, $x, $y, $alpha | ($red << 16) | ($green << 8) | $blue);
            }
        }
    }
}
