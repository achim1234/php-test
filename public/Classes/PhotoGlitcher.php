<?php

declare(strict_types=1);

namespace Classes;

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
        int $vJitter = 0
    ): bool {
        if (!file_exists($sourcePath)) {
            return false;
        }

        $info = @getimagesize($sourcePath);
        if ($info === false) {
            return false;
        }

        $mime = $info['mime'];
        switch ($mime) {
            case 'image/jpeg':
                $src = imagecreatefromjpeg($sourcePath);
                break;
            case 'image/png':
                $src = imagecreatefrompng($sourcePath);
                break;
            default:
                return false;
        }

        if (!$src) {
            return false;
        }

        $width = imagesx($src);
        $height = imagesy($src);
        $dst = imagecreatetruecolor($width, $height);

        // Fill with black
        imagefill($dst, 0, 0, imagecolorallocate($dst, 0, 0, 0));

        // Brightness / Contrast
        if ($brightness !== 0 || $contrast !== 0) {
            imagefilter($src, IMG_FILTER_BRIGHTNESS, $brightness);
            imagefilter($src, IMG_FILTER_CONTRAST, -$contrast); // GD contrast is inverted
        }

        // Invert
        if ($invert) {
            imagefilter($src, IMG_FILTER_NEGATE);
        }

        // Pixelate
        if ($pixelate > 1) {
            imagefilter($src, IMG_FILTER_PIXELATE, $pixelate, true);
        }

        // Simple RGB Shift + some scanlines
        $shift = $rgbShift;
        $vShift = 0;

        for ($y = 0; $y < $height; $y++) {
            // Horizontal Jitter
            if ($jitter > 0 && $y % rand(20, 50) === 0) {
                $shift = rand(-$jitter, $jitter);
            }
            
            // Vertical Jitter (randomly shift a row vertically)
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

                // Shift red channel
                $xRed = $x + $shift;
                if ($xRed >= 0 && $xRed < $width) {
                    $rgbDest = imagecolorat($dst, $xRed, $y);
                    $rDest = $r;
                    $gDest = ($rgbDest >> 8) & 0xFF;
                    $bDest = $rgbDest & 0xFF;
                    imagesetpixel($dst, $xRed, $y, imagecolorallocate($dst, $rDest, $gDest, $bDest));
                }

                // Shift blue channel opposite way
                $xBlue = $x - $shift;
                if ($xBlue >= 0 && $xBlue < $width) {
                    $rgbDest = imagecolorat($dst, $xBlue, $y);
                    $rDest = ($rgbDest >> 16) & 0xFF;
                    $gDest = ($rgbDest >> 8) & 0xFF;
                    $bDest = $b;
                    imagesetpixel($dst, $xBlue, $y, imagecolorallocate($dst, $rDest, $gDest, $bDest));
                }
                
                // Green stays or slightly shifted
                if ($x >= 0 && $x < $width) {
                    $rgbDest = imagecolorat($dst, $x, $y);
                    $rDest = ($rgbDest >> 16) & 0xFF;
                    $gDest = $g;
                    $bDest = ($rgbDest >> 0) & 0xFF; // mixing a bit
                    imagesetpixel($dst, $x, $y, imagecolorallocate($dst, $rDest, $gDest, $bDest));
                }
            }
        }

        // Add some "scanlines" or static
        for ($i = 0; $i < $scanlines; $i++) {
            $h = rand(1, 5);
            $y = rand(0, $height - $h);
            $s = rand(-$jitter, $jitter);
            imagecopy($dst, $dst, $s, $y, 0, $y, $width, $h);
        }

        // Save based on MIME type to preserve quality/format
        if ($mime === 'image/png') {
            // For PNG, quality is 0-9 (0 = no compression, 9 = max compression)
            $result = imagepng($dst, $destPath, 0);
        } else {
            // For JPEG, quality is 0-100 (default is ~75)
            $result = imagejpeg($dst, $destPath, 100);
        }

        imagedestroy($src);
        imagedestroy($dst);

        return $result;
    }
}
