<?php

declare(strict_types=1);

namespace App\Image;

use GdImage;

final readonly class ImageMorpher
{
    public function __construct(private GdImageFile $imageFile) {}

    /**
     * @param string[] $sourcePaths
     */
    public function morph(array $sourcePaths, string $destinationPath): bool
    {
        $maxWidth = 0;
        $maxHeight = 0;
        $images = $this->loadImages($sourcePaths, $maxWidth, $maxHeight);

        if (count($images) < 2) {
            $this->destroyImages($images);

            return false;
        }

        $destination = imagecreatetruecolor($maxWidth, $maxHeight);
        imagefill($destination, 0, 0, imagecolorallocate($destination, 0, 0, 0));

        try {
            $this->blend($destination, $images, $maxWidth, $maxHeight);

            return $this->imageFile->save(
                $destination,
                $destinationPath,
                $this->imageFile->mimeTypeForPath($destinationPath),
            );
        } finally {
            $this->destroyImages($images);
            imagedestroy($destination);
        }
    }

    /**
     * @param string[] $paths
     * @return GdImage[]
     */
    private function loadImages(array $paths, int &$maxWidth, int &$maxHeight): array
    {
        $images = [];

        foreach ($paths as $path) {
            $loaded = $this->imageFile->load($path);
            if ($loaded === null) {
                continue;
            }

            $images[] = $loaded->image;
            $maxWidth = max($maxWidth, imagesx($loaded->image));
            $maxHeight = max($maxHeight, imagesy($loaded->image));
        }

        return $images;
    }

    /**
     * @param GdImage[] $images
     */
    private function blend(GdImage $destination, array $images, int $width, int $height): void
    {
        $count = count($images);
        $widths = array_map(imagesx(...), $images);
        $heights = array_map(imagesy(...), $images);

        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                $redTotal = 0;
                $greenTotal = 0;
                $blueTotal = 0;

                foreach ($images as $index => $image) {
                    $rgb = imagecolorat($image, $x % $widths[$index], $y % $heights[$index]);
                    $redTotal += ($rgb >> 16) & 0xFF;
                    $greenTotal += ($rgb >> 8) & 0xFF;
                    $blueTotal += $rgb & 0xFF;
                }

                $red = (int)($redTotal / $count);
                $green = (int)($greenTotal / $count);
                $blue = (int)($blueTotal / $count);
                imagesetpixel($destination, $x, $y, ($red << 16) | ($green << 8) | $blue);
            }
        }
    }

    /**
     * @param GdImage[] $images
     */
    private function destroyImages(array $images): void
    {
        foreach ($images as $image) {
            imagedestroy($image);
        }
    }
}
