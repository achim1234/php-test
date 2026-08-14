<?php

declare(strict_types=1);

namespace Classes;

use GdImage;

class PhotoGlitcher
{
    /**
     * Applies a simple RGB shift glitch effect to an image.
     */
    public function applyGlitch(
        string $sourcePath,
        string $destPath,
        int $rgbShift = 10,
        int $jitter = 20,
        int $scanlines = 10,
        int $brightness = 0,
        int $contrast = 0,
        bool $invert = false,
        int $pixelate = 0,
        int $vJitter = 0,
        string $presetFilter = 'none',
        string $colorize = '',
        int $colorIntensity = 0
    ): bool {
        if (!file_exists($sourcePath)) {
            return false;
        }

        $info = @getimagesize($sourcePath);
        if ($info === false) {
            return false;
        }

        $mime = $info['mime'];
        $src = $this->createImageFromPath($sourcePath, $mime);
        if (!$src) {
            return false;
        }

        // Pre-processing filters
        $this->applyFilters($src, $brightness, $contrast, $invert, $pixelate, $presetFilter, $colorize, $colorIntensity);

        // Core glitch effect
        $dst = $this->createGlitchedCanvas($src, $rgbShift, $jitter, $vJitter);

        // Post-processing static/scanlines
        $this->addScanlines($dst, $scanlines, $jitter);

        // Save result
        $result = $this->saveImage($dst, $destPath, $mime);

        imagedestroy($src);
        imagedestroy($dst);

        return $result;
    }

    private function createImageFromPath(string $path, string $mime): ?GdImage
    {
        return match ($mime) {
            'image/jpeg' => imagecreatefromjpeg($path) ?: null,
            'image/png' => imagecreatefrompng($path) ?: null,
            default => null,
        };
    }

    private function applyFilters(
        GdImage $image,
        int $brightness,
        int $contrast,
        bool $invert,
        int $pixelate,
        string $presetFilter = 'none',
        string $colorize = '',
        int $colorIntensity = 0
    ): void {
        if ($brightness !== 0 || $contrast !== 0) {
            imagefilter($image, IMG_FILTER_BRIGHTNESS, $brightness);
            imagefilter($image, IMG_FILTER_CONTRAST, -$contrast); // GD contrast is inverted
        }

        if ($invert) {
            imagefilter($image, IMG_FILTER_NEGATE);
        }

        if ($pixelate > 1) {
            imagefilter($image, IMG_FILTER_PIXELATE, $pixelate, true);
        }

        match ($presetFilter) {
            'grayscale' => imagefilter($image, IMG_FILTER_GRAYSCALE),
            'sepia' => $this->applySepia($image),
            'dramatic' => $this->applyDramatic($image),
            'vintage' => $this->applyVintage($image),
            default => null,
        };

        if ($colorize !== '' && $colorIntensity > 0) {
            $rgb = $this->hexToRgb($colorize);
            if ($rgb) {
                // GD colorize: alpha 0-127. 
                // 0 indicates completely opaque while 127 indicates completely transparent.
                // intensity 0 (none) -> alpha 127
                // intensity 100 (full) -> alpha 0
                $alpha = 127 - (int)($colorIntensity * 1.27);
                imagefilter($image, IMG_FILTER_COLORIZE, $rgb['r'], $rgb['g'], $rgb['b'], $alpha);
            }
        }
    }

    private function applySepia(GdImage $image): void
    {
        imagefilter($image, IMG_FILTER_GRAYSCALE);
        imagefilter($image, IMG_FILTER_COLORIZE, 90, 60, 40);
    }

    private function applyDramatic(GdImage $image): void
    {
        imagefilter($image, IMG_FILTER_CONTRAST, -20);
        imagefilter($image, IMG_FILTER_BRIGHTNESS, -10);
        imagefilter($image, IMG_FILTER_COLORIZE, 0, 0, 20, 30);
    }

    private function applyVintage(GdImage $image): void
    {
        imagefilter($image, IMG_FILTER_COLORIZE, 20, 20, 0, 20);
        imagefilter($image, IMG_FILTER_CONTRAST, -10);
    }

    private function hexToRgb(string $hex): ?array
    {
        $hex = str_replace('#', '', $hex);
        if (strlen($hex) === 3) {
            $r = hexdec(substr($hex, 0, 1) . substr($hex, 0, 1));
            $g = hexdec(substr($hex, 1, 1) . substr($hex, 1, 1));
            $b = hexdec(substr($hex, 2, 1) . substr($hex, 2, 1));
        } elseif (strlen($hex) === 6) {
            $r = hexdec(substr($hex, 0, 2));
            $g = hexdec(substr($hex, 2, 2));
            $b = hexdec(substr($hex, 4, 2));
        } else {
            return null;
        }
        return ['r' => $r, 'g' => $g, 'b' => $b];
    }

    private function createGlitchedCanvas(GdImage $src, int $rgbShift, int $jitter, int $vJitter): GdImage
    {
        $width = imagesx($src);
        $height = imagesy($src);
        $dst = imagecreatetruecolor($width, $height);

        // Fill with black
        imagefill($dst, 0, 0, imagecolorallocate($dst, 0, 0, 0));

        $shift = $rgbShift;

        for ($y = 0; $y < $height; $y++) {
            // Horizontal Jitter
            if ($jitter > 0 && $y % rand(20, 50) === 0) {
                $shift = rand(-$jitter, $jitter);
            }
            
            // Vertical Jitter
            $yOff = 0;
            if ($vJitter > 0 && rand(0, 100) < 5) {
                $yOff = rand(-$vJitter, $vJitter);
            }

            for ($x = 0; $x < $width; $x++) {
                $srcY = $y + $yOff;
                if ($srcY < 0 || $srcY >= $height) $srcY = $y;

                $rgb = imagecolorat($src, $x, $srcY);
                $r = ($rgb >> 16) & 0xFF;
                $g = ($rgb >> 8) & 0xFF;
                $b = $rgb & 0xFF;

                // Red channel shift
                $xRed = $x + $shift;
                if ($xRed >= 0 && $xRed < $width) {
                    $this->setPixelChannel($dst, $xRed, $y, $r, 'r');
                }

                // Blue channel shift
                $xBlue = $x - $shift;
                if ($xBlue >= 0 && $xBlue < $width) {
                    $this->setPixelChannel($dst, $xBlue, $y, $b, 'b');
                }
                
                // Green channel (center/slight shift)
                if ($x >= 0 && $x < $width) {
                    $this->setPixelChannel($dst, $x, $y, $g, 'g');
                }
            }
        }

        return $dst;
    }

    private function setPixelChannel(GdImage $image, int $x, int $y, int $value, string $channel): void
    {
        $rgb = imagecolorat($image, $x, $y);
        $r = ($rgb >> 16) & 0xFF;
        $g = ($rgb >> 8) & 0xFF;
        $b = $rgb & 0xFF;

        match ($channel) {
            'r' => $r = $value,
            'g' => $g = $value,
            'b' => $b = $value,
        };

        imagesetpixel($image, $x, $y, imagecolorallocate($image, $r, $g, $b));
    }

    private function addScanlines(GdImage $image, int $scanlines, int $jitter): void
    {
        $width = imagesx($image);
        $height = imagesy($image);

        for ($i = 0; $i < $scanlines; $i++) {
            $h = rand(1, 5);
            $y = rand(0, $height - $h);
            $s = rand(-$jitter, $jitter);
            imagecopy($image, $image, $s, $y, 0, $y, $width, $h);
        }
    }

    private function saveImage(GdImage $image, string $path, string $mime): bool
    {
        return match ($mime) {
            'image/png' => imagepng($image, $path, 0),
            default => imagejpeg($image, $path, 100),
        };
    }

    /**
     * Morphs (blends) multiple images together.
     */
    public function morphImages(array $sourcePaths, string $destPath): bool
    {
        if (empty($sourcePaths)) {
            return false;
        }

        $maxWidth = 0;
        $maxHeight = 0;
        $images = $this->loadImagesForMorphing($sourcePaths, $maxWidth, $maxHeight);

        if (count($images) < 2) {
            $this->destroyImages($images);
            return false;
        }

        $dst = imagecreatetruecolor($maxWidth, $maxHeight);
        imagefill($dst, 0, 0, imagecolorallocate($dst, 0, 0, 0));

        $this->blendImages($dst, $images, $maxWidth, $maxHeight);

        $result = $this->saveImage($dst, $destPath, $this->guessMimeType($destPath));

        $this->destroyImages($images);
        imagedestroy($dst);

        return $result;
    }

    private function loadImagesForMorphing(array $paths, int &$maxWidth, int &$maxHeight): array
    {
        $images = [];
        $maxWidth = 0;
        $maxHeight = 0;

        foreach ($paths as $path) {
            if (!file_exists($path)) continue;
            $info = @getimagesize($path);
            if ($info === false) continue;

            $img = $this->createImageFromPath($path, $info['mime']);
            if ($img) {
                $images[] = $img;
                $maxWidth = max($maxWidth, imagesx($img));
                $maxHeight = max($maxHeight, imagesy($img));
            }
        }

        return $images;
    }

    private function blendImages(GdImage $dst, array $images, int $maxWidth, int $maxHeight): void
    {
        $count = count($images);
        for ($y = 0; $y < $maxHeight; $y++) {
            for ($x = 0; $x < $maxWidth; $x++) {
                $rTotal = $gTotal = $bTotal = 0;
                foreach ($images as $img) {
                    $w = imagesx($img);
                    $h = imagesy($img);
                    
                    $srcX = $x % $w;
                    $srcY = $y % $h;
                    
                    $rgb = imagecolorat($img, $srcX, $srcY);
                    $rTotal += ($rgb >> 16) & 0xFF;
                    $gTotal += ($rgb >> 8) & 0xFF;
                    $bTotal += $rgb & 0xFF;
                }
                
                $r = (int)($rTotal / $count);
                $g = (int)($gTotal / $count);
                $b = (int)($bTotal / $count);
                
                imagesetpixel($dst, $x, $y, imagecolorallocate($dst, $r, $g, $b));
            }
        }
    }

    private function destroyImages(array $images): void
    {
        foreach ($images as $img) {
            imagedestroy($img);
        }
    }

    private function guessMimeType(string $path): string
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        return $extension === 'png' ? 'image/png' : 'image/jpeg';
    }
}
