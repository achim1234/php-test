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
