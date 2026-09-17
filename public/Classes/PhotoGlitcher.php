<?php

declare(strict_types=1);

namespace Classes;

use GdImage;

class PhotoGlitcher
{
    /**
     * Applies color processing and structural glitch effects to an image.
     */
    public function applyGlitch(
        string $sourcePath,
        string $destPath,
        int $rgbShift = 0,
        int $jitter = 0,
        int $scanlines = 0,
        int $brightness = 0,
        int $contrast = 0,
        bool $invert = false,
        int $pixelate = 0,
        int $vJitter = 0,
        string $presetFilter = 'none',
        string $colorize = '',
        int $colorIntensity = 0,
        string $glitchMode = 'signal',
        int $chaos = 0,
        int $redChannel = 0,
        int $greenChannel = 0,
        int $blueChannel = 0,
        string $channelFilter = 'none',
        string $duotoneShadow = '#24105e',
        string $duotoneHighlight = '#ffef5c',
    ): bool {
        if (!file_exists($sourcePath)) {
            return false;
        }

        $info = getimagesize($sourcePath);
        if ($info === false) {
            return false;
        }

        $mime = $info['mime'];
        $src = $this->createImageFromPath($sourcePath, $mime);
        if (!$src) {
            return false;
        }

        $this->applyFilters(
            $src,
            $brightness,
            $contrast,
            $invert,
            $pixelate,
            $presetFilter,
            $colorize,
            $colorIntensity,
            $duotoneShadow,
            $duotoneHighlight,
        );
        $this->applyChannelManipulation($src, $redChannel, $greenChannel, $blueChannel, $channelFilter);

        $dst = $this->createGlitchedCanvas($src, $rgbShift, $jitter, $vJitter);
        $this->applyDistortionEngine($dst, $glitchMode, max(0, min(100, $chaos)));

        $this->addScanlines($dst, $scanlines, $jitter);

        $result = $this->saveImage($dst, $destPath, $mime);

        imagedestroy($src);
        imagedestroy($dst);

        return $result;
    }

    private function createImageFromPath(string $path, string $mime): ?GdImage
    {
        $image = match ($mime) {
            'image/jpeg' => imagecreatefromjpeg($path) ?: null,
            'image/png' => imagecreatefrompng($path) ?: null,
            default => null,
        };

        if ($image !== null && !imageistruecolor($image)) {
            imagepalettetotruecolor($image);
        }

        return $image;
    }

    private function applyFilters(
        GdImage $image,
        int $brightness,
        int $contrast,
        bool $invert,
        int $pixelate,
        string $presetFilter = 'none',
        string $colorize = '',
        int $colorIntensity = 0,
        string $duotoneShadow = '#24105e',
        string $duotoneHighlight = '#ffef5c',
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
            'neon' => $this->applyNeon($image),
            'solarize' => $this->applySolarize($image),
            'thermal' => $this->applyThermalMap($image),
            'toxic' => $this->applyToxicChrome($image),
            'posterize' => $this->applyPosterize($image),
            'duotone' => $this->applyDuotone($image, $duotoneShadow, $duotoneHighlight),
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

    private function applyNeon(GdImage $image): void
    {
        imagefilter($image, IMG_FILTER_EDGEDETECT);
        imagefilter($image, IMG_FILTER_GRAYSCALE);
        imagefilter($image, IMG_FILTER_CONTRAST, -55);
        imagefilter($image, IMG_FILTER_NEGATE);
        imagefilter($image, IMG_FILTER_COLORIZE, 50, -35, 110);
    }

    private function applySolarize(GdImage $image): void
    {
        $this->transformPixels($image, static function (int $red, int $green, int $blue): array {
            return [
                $red > 127 ? 255 - $red : $red,
                $green > 127 ? 255 - $green : $green,
                $blue > 127 ? 255 - $blue : $blue,
            ];
        });
    }

    private function applyThermalMap(GdImage $image): void
    {
        $this->transformPixels($image, static function (int $red, int $green, int $blue): array {
            $level = (int)(($red * 0.299) + ($green * 0.587) + ($blue * 0.114));

            return match (true) {
                $level < 64 => [0, $level * 2, 128 + ($level * 2)],
                $level < 128 => [($level - 64) * 4, 255, 255 - (($level - 64) * 4)],
                $level < 192 => [255, 255 - (($level - 128) * 4), 0],
                default => [255, ($level - 192) * 4, ($level - 192) * 4],
            };
        });
    }

    private function applyToxicChrome(GdImage $image): void
    {
        imagefilter($image, IMG_FILTER_CONTRAST, -45);
        imagefilter($image, IMG_FILTER_COLORIZE, -70, 70, 20);
        imagefilter($image, IMG_FILTER_SMOOTH, -7);
    }

    private function applyPosterize(GdImage $image): void
    {
        $this->transformPixels($image, static fn(int $red, int $green, int $blue): array => [
            min(255, intdiv($red, 64) * 85),
            min(255, intdiv($green, 64) * 85),
            min(255, intdiv($blue, 64) * 85),
        ]);
    }

    private function applyDuotone(GdImage $image, string $shadowColor, string $highlightColor): void
    {
        $shadow = $this->hexToRgb($shadowColor) ?? ['r' => 36, 'g' => 16, 'b' => 94];
        $highlight = $this->hexToRgb($highlightColor) ?? ['r' => 255, 'g' => 239, 'b' => 92];

        $this->transformPixels(
            $image,
            static function (int $red, int $green, int $blue) use ($shadow, $highlight): array {
                $level = intdiv(($red * 299) + ($green * 587) + ($blue * 114) + 500, 1000);
                $mix = $level / 255;

                return [
                    (int)round($shadow['r'] + (($highlight['r'] - $shadow['r']) * $mix)),
                    (int)round($shadow['g'] + (($highlight['g'] - $shadow['g']) * $mix)),
                    (int)round($shadow['b'] + (($highlight['b'] - $shadow['b']) * $mix)),
                ];
            },
        );
    }

    private function applyChannelManipulation(
        GdImage $image,
        int $redLevel,
        int $greenLevel,
        int $blueLevel,
        string $filter,
    ): void {
        if ($redLevel === 0 && $greenLevel === 0 && $blueLevel === 0 && $filter === 'none') {
            return;
        }

        $redOffset = (int)round($redLevel * 2.55);
        $greenOffset = (int)round($greenLevel * 2.55);
        $blueOffset = (int)round($blueLevel * 2.55);

        $this->transformPixels(
            $image,
            static function (int $red, int $green, int $blue) use ($redOffset, $greenOffset, $blueOffset, $filter): array {
                $red = max(0, min(255, $red + $redOffset));
                $green = max(0, min(255, $green + $greenOffset));
                $blue = max(0, min(255, $blue + $blueOffset));

                return match ($filter) {
                    'red_only' => [$red, 0, 0],
                    'green_only' => [0, $green, 0],
                    'blue_only' => [0, 0, $blue],
                    'invert_red' => [255 - $red, $green, $blue],
                    'invert_green' => [$red, 255 - $green, $blue],
                    'invert_blue' => [$red, $green, 255 - $blue],
                    'remove_red' => [0, $green, $blue],
                    'remove_green' => [$red, 0, $blue],
                    'remove_blue' => [$red, $green, 0],
                    'swap_red_green' => [$green, $red, $blue],
                    'swap_red_blue' => [$blue, $green, $red],
                    'swap_green_blue' => [$red, $blue, $green],
                    'rotate_rgb' => [$green, $blue, $red],
                    'rotate_rbg' => [$blue, $red, $green],
                    default => [$red, $green, $blue],
                };
            },
        );
    }

    /**
     * @param callable(int, int, int): array{int, int, int} $transform
     */
    private function transformPixels(GdImage $image, callable $transform): void
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

    private function hexToRgb(string $hex): ?array
    {
        $hex = str_replace('#', '', $hex);
        if (preg_match('/^(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $hex) !== 1) {
            return null;
        }

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

        imagealphablending($dst, false);
        imagesavealpha($dst, true);

        if ($rgbShift === 0 && $jitter === 0 && $vJitter === 0) {
            imagecopy($dst, $src, 0, 0, 0, 0, $width, $height);
            return $dst;
        }

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

            $srcY = $y + $yOff;
            if ($srcY < 0 || $srcY >= $height) $srcY = $y;

            if ($shift === 0) {
                imagecopy($dst, $src, 0, $y, 0, $srcY, $width, 1);
                continue;
            }

            // Gather the channels once per destination pixel, avoiding three
            // read/modify/write cycles and color allocations for every pixel.
            for ($x = 0; $x < $width; $x++) {
                $rgb = imagecolorat($src, $x, $srcY) & 0x7F00FF00;
                $xRed = $x - $shift;
                $xBlue = $x + $shift;
                if ($xRed >= 0 && $xRed < $width) {
                    $rgb |= imagecolorat($src, $xRed, $srcY) & 0xFF0000;
                }
                if ($xBlue >= 0 && $xBlue < $width) {
                    $rgb |= imagecolorat($src, $xBlue, $srcY) & 0xFF;
                }
                imagesetpixel($dst, $x, $y, $rgb);
            }
        }

        return $dst;
    }

    private function applyDistortionEngine(GdImage $image, string $mode, int $chaos): void
    {
        if ($chaos === 0) {
            return;
        }

        match ($mode) {
            'datamosh' => $this->applyDataMosh($image, $chaos),
            'melt' => $this->applyPixelMelt($image, $chaos),
            'mirror' => $this->applyMirrorFold($image, $chaos),
            'vhs' => $this->applyVhsRupture($image, $chaos),
            'shred' => $this->applySliceShred($image, $chaos),
            default => null,
        };
    }

    private function applyDataMosh(GdImage $image, int $chaos): void
    {
        $width = imagesx($image);
        $height = imagesy($image);
        $copies = max(2, (int)ceil($chaos / 4));
        $maxShift = max(1, (int)round($width * (0.08 + ($chaos / 180))));
        $maxBlockWidth = max(1, (int)round($width * (0.08 + ($chaos / 250))));
        $maxBlockHeight = max(1, (int)round($height * (0.03 + ($chaos / 500))));

        for ($i = 0; $i < $copies; $i++) {
            $blockWidth = random_int(1, min($width, $maxBlockWidth));
            $blockHeight = random_int(1, min($height, $maxBlockHeight));
            $sourceX = random_int(0, $width - $blockWidth);
            $sourceY = random_int(0, $height - $blockHeight);
            $targetX = max(0, min($width - $blockWidth, $sourceX + random_int(-$maxShift, $maxShift)));
            $targetY = max(0, min($height - $blockHeight, $sourceY + random_int(-$maxBlockHeight, $maxBlockHeight)));

            imagecopy($image, $image, $targetX, $targetY, $sourceX, $sourceY, $blockWidth, $blockHeight);
        }
    }

    private function applyPixelMelt(GdImage $image, int $chaos): void
    {
        $width = imagesx($image);
        $height = imagesy($image);
        $drips = max(2, (int)ceil($chaos / 6));
        $maxStripWidth = max(1, (int)round($width * 0.12));

        for ($i = 0; $i < $drips; $i++) {
            $stripWidth = random_int(1, min($width, $maxStripWidth));
            $sourceHeight = random_int(1, max(1, (int)round($height * 0.08)));
            $sourceX = random_int(0, $width - $stripWidth);
            $sourceY = random_int(0, max(0, $height - $sourceHeight));
            $meltHeight = min(
                $height - $sourceY,
                $sourceHeight + max(1, (int)round($height * $chaos / 120)),
            );
            $strip = imagecreatetruecolor($stripWidth, $sourceHeight);
            imagecopy($strip, $image, 0, 0, $sourceX, $sourceY, $stripWidth, $sourceHeight);
            imagecopyresampled(
                $image,
                $strip,
                $sourceX,
                $sourceY,
                0,
                0,
                $stripWidth,
                $meltHeight,
                $stripWidth,
                $sourceHeight,
            );
            imagedestroy($strip);
        }
    }

    private function applyMirrorFold(GdImage $image, int $chaos): void
    {
        $width = imagesx($image);
        $height = imagesy($image);
        $leftWidth = (int)ceil($width / 2);
        $rightWidth = $width - $leftWidth;

        if ($rightWidth > 0) {
            $left = imagecrop($image, ['x' => 0, 'y' => 0, 'width' => $leftWidth, 'height' => $height]);
            if ($left !== false) {
                imageflip($left, IMG_FLIP_HORIZONTAL);
                imagecopy($image, $left, $leftWidth, 0, max(0, $leftWidth - $rightWidth), 0, $rightWidth, $height);
                imagedestroy($left);
            }
        }

        if ($chaos < 45) {
            return;
        }

        $topHeight = (int)ceil($height / 2);
        $bottomHeight = $height - $topHeight;
        if ($bottomHeight > 0) {
            $top = imagecrop($image, ['x' => 0, 'y' => 0, 'width' => $width, 'height' => $topHeight]);
            if ($top !== false) {
                imageflip($top, IMG_FLIP_VERTICAL);
                imagecopy($image, $top, 0, $topHeight, 0, max(0, $topHeight - $bottomHeight), $width, $bottomHeight);
                imagedestroy($top);
            }
        }

        if ($chaos >= 80) {
            $this->applyDataMosh($image, (int)round($chaos / 2));
        }
    }

    private function applyVhsRupture(GdImage $image, int $chaos): void
    {
        $width = imagesx($image);
        $height = imagesy($image);
        $tears = max(2, (int)ceil($chaos / 7));
        $maxShift = max(1, (int)round($width * $chaos / 180));
        $maxBandHeight = max(1, (int)round($height * 0.04));

        for ($i = 0; $i < $tears; $i++) {
            $bandHeight = random_int(1, min($height, $maxBandHeight));
            $y = random_int(0, $height - $bandHeight);
            imagecopy($image, $image, random_int(-$maxShift, $maxShift), $y, 0, $y, $width, $bandHeight);
        }

        $lineColor = imagecolorallocatealpha($image, 220, 230, 255, max(55, 110 - $chaos));
        $lineSpacing = max(2, 12 - (int)round($chaos / 10));
        for ($y = 0; $y < $height; $y += $lineSpacing) {
            imageline($image, 0, $y, $width - 1, $y, $lineColor);
        }

        imagefilter($image, IMG_FILTER_COLORIZE, 8, -5, 24, 65);
    }

    private function applySliceShred(GdImage $image, int $chaos): void
    {
        $width = imagesx($image);
        $height = imagesy($image);
        $slices = max(2, (int)ceil($chaos / 7));
        $maxSliceWidth = max(1, (int)round($width * 0.08));
        $maxOffset = max(1, (int)round($height * $chaos / 130));

        for ($i = 0; $i < $slices; $i++) {
            $sliceWidth = random_int(1, min($width, $maxSliceWidth));
            $x = random_int(0, $width - $sliceWidth);
            $slice = imagecreatetruecolor($sliceWidth, $height);
            imagecopy($slice, $image, 0, 0, $x, 0, $sliceWidth, $height);
            $offset = random_int(-$maxOffset, $maxOffset);
            imagecopy($image, $slice, $x, $offset, 0, 0, $sliceWidth, $height);
            if ($offset > 0) {
                imagecopy($image, $slice, $x, $offset - $height, 0, 0, $sliceWidth, $height);
            } elseif ($offset < 0) {
                imagecopy($image, $slice, $x, $height + $offset, 0, 0, $sliceWidth, $height);
            }
            imagedestroy($slice);
        }

        $this->applyDataMosh($image, (int)round($chaos * 0.65));
    }

    private function addScanlines(GdImage $image, int $scanlines, int $jitter): void
    {
        $width = imagesx($image);
        $height = imagesy($image);

        for ($i = 0; $i < $scanlines; $i++) {
            $h = rand(1, min(5, $height));
            $y = rand(0, $height - $h);
            $s = rand(-$jitter, $jitter);
            imagecopy($image, $image, $s, $y, 0, $y, $width, $h);
        }
    }

    private function saveImage(GdImage $image, string $path, string $mime): bool
    {
        return match ($mime) {
            'image/png' => imagepng($image, $path, 6),
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
            $info = getimagesize($path);
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
        $widths = array_map(imagesx(...), $images);
        $heights = array_map(imagesy(...), $images);
        for ($y = 0; $y < $maxHeight; $y++) {
            for ($x = 0; $x < $maxWidth; $x++) {
                $rTotal = $gTotal = $bTotal = 0;
                foreach ($images as $index => $img) {
                    $srcX = $x % $widths[$index];
                    $srcY = $y % $heights[$index];
                    
                    $rgb = imagecolorat($img, $srcX, $srcY);
                    $rTotal += ($rgb >> 16) & 0xFF;
                    $gTotal += ($rgb >> 8) & 0xFF;
                    $bTotal += $rgb & 0xFF;
                }
                
                $r = (int)($rTotal / $count);
                $g = (int)($gTotal / $count);
                $b = (int)($bTotal / $count);
                
                imagesetpixel($dst, $x, $y, ($r << 16) | ($g << 8) | $b);
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
