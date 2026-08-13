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
        int $scanlines = 10
    ): bool {
        $info = getimagesize($sourcePath);
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

        // Fill with black or transparent if needed
        imagefill($dst, 0, 0, imagecolorallocate($dst, 0, 0, 0));

        // Simple RGB Shift + some scanlines
        $shift = $rgbShift;

        for ($y = 0; $y < $height; $y++) {
            // Occasionally change shift for "jitter" effect
            if ($jitter > 0 && $y % rand(20, 50) === 0) {
                $shift = rand(-$jitter, $jitter);
            }

            for ($x = 0; $x < $width; $x++) {
                $rgb = imagecolorat($src, $x, $y);
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
                    $bDest = ($rgbDest >> 8) & 0xFF; // mixing a bit
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

        $result = imagejpeg($dst, $destPath, 80);
        imagedestroy($src);
        imagedestroy($dst);

        return $result;
    }
}
