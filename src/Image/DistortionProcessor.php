<?php

declare(strict_types=1);

namespace App\Image;

use App\Enum\DistortionMode;
use GdImage;

final class DistortionProcessor
{
    public function apply(GdImage $source, GlitchOptions $options): GdImage
    {
        $image = $this->createCanvas(
            $source,
            $options->rgbShift,
            $options->jitter,
            $options->verticalJitter,
        );

        if ($options->chaos > 0) {
            match ($options->distortionMode) {
                DistortionMode::DataMosh => $this->applyDataMosh($image, $options->chaos),
                DistortionMode::Melt => $this->applyPixelMelt($image, $options->chaos),
                DistortionMode::Mirror => $this->applyMirrorFold($image, $options->chaos),
                DistortionMode::Vhs => $this->applyVhsRupture($image, $options->chaos),
                DistortionMode::Shred => $this->applySliceShred($image, $options->chaos),
                DistortionMode::Signal => null,
            };
        }

        $this->addScanlines($image, $options->scanlines, $options->jitter);

        return $image;
    }

    private function createCanvas(GdImage $source, int $rgbShift, int $jitter, int $verticalJitter): GdImage
    {
        $width = imagesx($source);
        $height = imagesy($source);
        $destination = imagecreatetruecolor($width, $height);

        imagealphablending($destination, false);
        imagesavealpha($destination, true);

        if ($rgbShift === 0 && $jitter === 0 && $verticalJitter === 0) {
            imagecopy($destination, $source, 0, 0, 0, 0, $width, $height);

            return $destination;
        }

        $shift = $rgbShift;
        for ($y = 0; $y < $height; $y++) {
            if ($jitter > 0 && $y % rand(20, 50) === 0) {
                $shift = rand(-$jitter, $jitter);
            }

            $sourceY = $y;
            if ($verticalJitter > 0 && rand(0, 100) < 5) {
                $sourceY += rand(-$verticalJitter, $verticalJitter);
            }
            if ($sourceY < 0 || $sourceY >= $height) {
                $sourceY = $y;
            }

            if ($shift === 0) {
                imagecopy($destination, $source, 0, $y, 0, $sourceY, $width, 1);
                continue;
            }

            for ($x = 0; $x < $width; $x++) {
                $rgb = imagecolorat($source, $x, $sourceY) & 0x7F00FF00;
                $redX = $x - $shift;
                $blueX = $x + $shift;
                if ($redX >= 0 && $redX < $width) {
                    $rgb |= imagecolorat($source, $redX, $sourceY) & 0xFF0000;
                }
                if ($blueX >= 0 && $blueX < $width) {
                    $rgb |= imagecolorat($source, $blueX, $sourceY) & 0xFF;
                }
                imagesetpixel($destination, $x, $y, $rgb);
            }
        }

        return $destination;
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
            $lineHeight = rand(1, min(5, $height));
            $y = rand(0, $height - $lineHeight);
            $shift = rand(-$jitter, $jitter);
            imagecopy($image, $image, $shift, $y, 0, $y, $width, $lineHeight);
        }
    }
}
