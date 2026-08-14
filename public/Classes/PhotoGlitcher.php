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

    /**
     * Morphs (blends) multiple images together.
     *
     * @param array $sourcePaths Paths to source images
     * @param string $destPath Path to save the resulting image
     * @param float $amount Blending amount (not strictly used if multiple images, we'll average them)
     * @return bool
     */
    public function morphImages(array $sourcePaths, string $destPath): bool
    {
        if (empty($sourcePaths)) {
            return false;
        }

        $images = [];
        $maxWidth = 0;
        $maxHeight = 0;

        foreach ($sourcePaths as $path) {
            if (!file_exists($path)) continue;
            $info = @getimagesize($path);
            if ($info === false) continue;

            $mime = $info['mime'];
            switch ($mime) {
                case 'image/jpeg':
                    $img = imagecreatefromjpeg($path);
                    break;
                case 'image/png':
                    $img = imagecreatefrompng($path);
                    break;
                default:
                    continue 2;
            }

            if ($img) {
                $images[] = $img;
                $maxWidth = max($maxWidth, imagesx($img));
                $maxHeight = max($maxHeight, imagesy($img));
            }
        }

        if (count($images) < 2) {
            foreach ($images as $img) imagedestroy($img);
            return false;
        }

        $dst = imagecreatetruecolor($maxWidth, $maxHeight);
        imagefill($dst, 0, 0, imagecolorallocate($dst, 0, 0, 0));

        // Average all images
        $count = count($images);
        for ($y = 0; $y < $maxHeight; $y++) {
            for ($x = 0; $x < $maxWidth; $x++) {
                $rTotal = $gTotal = $bTotal = 0;
                foreach ($images as $img) {
                    $w = imagesx($img);
                    $h = imagesy($img);
                    
                    // Simple scaling: just wrap or clamp if image is smaller
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

        $extension = pathinfo($destPath, PATHINFO_EXTENSION);
        if (strtolower($extension) === 'png') {
            $result = imagepng($dst, $destPath, 0);
        } else {
            $result = imagejpeg($dst, $destPath, 100);
        }

        foreach ($images as $img) imagedestroy($img);
        imagedestroy($dst);

        return $result;
    }
}
