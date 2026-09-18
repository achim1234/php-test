<?php

declare(strict_types=1);

namespace App\Image;

use App\Enum\ColorProcess;
use GdImage;

final readonly class ColorProcessor
{
    public function __construct(private PixelTransformer $pixels) {}

    public function apply(GdImage $image, GlitchOptions $options): void
    {
        if ($options->brightness !== 0 || $options->contrast !== 0) {
            imagefilter($image, IMG_FILTER_BRIGHTNESS, $options->brightness);
            imagefilter($image, IMG_FILTER_CONTRAST, -$options->contrast);
        }

        if ($options->invert) {
            imagefilter($image, IMG_FILTER_NEGATE);
        }

        if ($options->pixelate > 1) {
            imagefilter($image, IMG_FILTER_PIXELATE, $options->pixelate, true);
        }

        match ($options->colorProcess) {
            ColorProcess::Grayscale => imagefilter($image, IMG_FILTER_GRAYSCALE),
            ColorProcess::Sepia => $this->applySepia($image),
            ColorProcess::Dramatic => $this->applyDramatic($image),
            ColorProcess::Vintage => $this->applyVintage($image),
            ColorProcess::Neon => $this->applyNeon($image),
            ColorProcess::Solarize => $this->applySolarize($image),
            ColorProcess::Thermal => $this->applyThermalMap($image),
            ColorProcess::Toxic => $this->applyToxicChrome($image),
            ColorProcess::Posterize => $this->applyPosterize($image),
            ColorProcess::GameBoy => $this->applyGameBoyDither($image),
            ColorProcess::ChromaticHalftone => $this->applyChromaticHalftone($image),
            ColorProcess::AchimsSpecial => $this->applyAchimsSpecial($image),
            ColorProcess::Duotone => $this->applyDuotone($image, $options->duotoneShadow, $options->duotoneHighlight),
            ColorProcess::None => null,
        };

        if ($options->tintColor !== '' && $options->tintIntensity > 0) {
            $rgb = $this->hexToRgb($options->tintColor);
            if ($rgb !== null) {
                $alpha = 127 - (int)($options->tintIntensity * 1.27);
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
        $this->pixels->transform($image, static fn(int $red, int $green, int $blue): array => [
            $red > 127 ? 255 - $red : $red,
            $green > 127 ? 255 - $green : $green,
            $blue > 127 ? 255 - $blue : $blue,
        ]);
    }

    private function applyThermalMap(GdImage $image): void
    {
        $this->pixels->transform($image, static function (int $red, int $green, int $blue): array {
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
        $this->pixels->transform($image, static fn(int $red, int $green, int $blue): array => [
            min(255, intdiv($red, 64) * 85),
            min(255, intdiv($green, 64) * 85),
            min(255, intdiv($blue, 64) * 85),
        ]);
    }

    private function applyGameBoyDither(GdImage $image): void
    {
        $bayerMatrix = [
            [0, 8, 2, 10],
            [12, 4, 14, 6],
            [3, 11, 1, 9],
            [15, 7, 13, 5],
        ];
        $palette = [
            [15, 56, 15],
            [48, 98, 48],
            [139, 172, 15],
            [155, 188, 15],
        ];

        $this->pixels->transformWithPosition(
            $image,
            static function (int $red, int $green, int $blue, int $x, int $y) use ($bayerMatrix, $palette): array {
                $luminance = (($red * 299) + ($green * 587) + ($blue * 114)) / 1000;
                $palettePosition = ($luminance / 255) * (count($palette) - 1);
                $lowerIndex = (int)floor($palettePosition);
                $threshold = ($bayerMatrix[$y % 4][$x % 4] + 0.5) / 16;
                $paletteIndex = min(
                    count($palette) - 1,
                    $lowerIndex + (($palettePosition - $lowerIndex) > $threshold ? 1 : 0),
                );

                return $palette[$paletteIndex];
            },
        );
    }

    private function applyChromaticHalftone(GdImage $image): void
    {
        $cellSize = max(4, min(12, (int)round(min(imagesx($image), imagesy($image)) / 120)));
        $maximumRadiusSquared = ($cellSize * $cellSize) / 2;
        $offsets = [
            [0, 0],
            [(int)round($cellSize / 3), (int)round($cellSize / 6)],
            [(int)round($cellSize / 6), (int)round($cellSize / 2)],
        ];

        $this->pixels->transformWithPosition(
            $image,
            static function (int $red, int $green, int $blue, int $x, int $y) use (
                $cellSize,
                $maximumRadiusSquared,
                $offsets,
            ): array {
                $center = ($cellSize - 1) / 2;
                $inkAmounts = [(255 - $red) / 255, (255 - $green) / 255, (255 - $blue) / 255];
                $result = [255, 255, 255];

                foreach ($inkAmounts as $channel => $inkAmount) {
                    [$offsetX, $offsetY] = $offsets[$channel];
                    $gridX = (($x - $offsetX) % $cellSize + $cellSize) % $cellSize;
                    $gridY = (($y - $offsetY) % $cellSize + $cellSize) % $cellSize;
                    $distanceSquared = (($gridX - $center) ** 2) + (($gridY - $center) ** 2);

                    if ($distanceSquared < $inkAmount * $maximumRadiusSquared) {
                        $result[$channel] = 0;
                    }
                }

                return $result;
            },
        );
    }

    private function applyAchimsSpecial(GdImage $image): void
    {
        $bayerMatrix = [
            [0, 8, 2, 10],
            [12, 4, 14, 6],
            [3, 11, 1, 9],
            [15, 7, 13, 5],
        ];
        $palettes = [
            [[18, 8, 38], [255, 43, 111], [255, 112, 35], [231, 255, 63]],
            [[9, 21, 45], [94, 54, 255], [0, 229, 178], [210, 255, 76]],
            [[25, 5, 48], [171, 45, 255], [20, 211, 255], [255, 73, 185]],
        ];
        $blockSize = max(8, min(30, (int)round(min(imagesx($image), imagesy($image)) / 30)));
        $tearWidth = max(2, intdiv($blockSize, 4));

        $this->pixels->transformWithPosition(
            $image,
            static function (int $red, int $green, int $blue, int $x, int $y) use (
                $bayerMatrix,
                $palettes,
                $blockSize,
                $tearWidth,
            ): array {
                $luminance = (($red * 299) + ($green * 587) + ($blue * 114)) / 1000;
                $dither = ($bayerMatrix[$y % 4][$x % 4] - 7.5) * 4;
                $level = min(3, max(0, intdiv((int)round($luminance + $dither), 64)));

                $dominantChannel = match (max($red, $green, $blue)) {
                    $red => 0,
                    $green => 1,
                    default => 2,
                };
                $collageTile = (intdiv($x, $blockSize) + intdiv($y, $blockSize)) % 3;
                $paletteIndex = ($dominantChannel + $collageTile) % 3;

                $tearPosition = ($x + (2 * $y)) % ($blockSize * 3);
                if ($tearPosition < $tearWidth) {
                    $level = 3 - $level;
                    $paletteIndex = ($paletteIndex + 1) % 3;
                }

                return $palettes[$paletteIndex][$level];
            },
        );
    }

    private function applyDuotone(GdImage $image, string $shadowColor, string $highlightColor): void
    {
        $shadow = $this->hexToRgb($shadowColor) ?? ['r' => 36, 'g' => 16, 'b' => 94];
        $highlight = $this->hexToRgb($highlightColor) ?? ['r' => 255, 'g' => 239, 'b' => 92];

        $this->pixels->transform(
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

    /**
     * @return array{r: int, g: int, b: int}|null
     */
    private function hexToRgb(string $hex): ?array
    {
        $hex = str_replace('#', '', $hex);
        if (preg_match('/^(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $hex) !== 1) {
            return null;
        }

        if (strlen($hex) === 3) {
            return [
                'r' => hexdec($hex[0] . $hex[0]),
                'g' => hexdec($hex[1] . $hex[1]),
                'b' => hexdec($hex[2] . $hex[2]),
            ];
        }

        return [
            'r' => hexdec(substr($hex, 0, 2)),
            'g' => hexdec(substr($hex, 2, 2)),
            'b' => hexdec(substr($hex, 4, 2)),
        ];
    }
}
