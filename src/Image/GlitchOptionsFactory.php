<?php

declare(strict_types=1);

namespace App\Image;

use App\Enum\ChannelOperation;
use App\Enum\ColorProcess;
use App\Enum\DistortionMode;

final class GlitchOptionsFactory
{
    /**
     * @param array<string, mixed> $input
     */
    public function fromArray(array $input): GlitchOptions
    {
        return new GlitchOptions(
            rgbShift: $this->integer($input, 'rgb_shift', 0, 50),
            jitter: $this->integer($input, 'jitter', 0, 100),
            scanlines: $this->integer($input, 'scanlines', 0, 50),
            brightness: $this->integer($input, 'brightness', -100, 100),
            contrast: $this->integer($input, 'contrast', -100, 100),
            invert: ($input['invert'] ?? '') === '1',
            pixelate: $this->integer($input, 'pixelate', 0, 20),
            verticalJitter: $this->integer($input, 'v_jitter', 0, 100),
            colorProcess: $this->enumValue($input, 'preset_filter', ColorProcess::None),
            tintColor: $this->string($input, 'colorize'),
            tintIntensity: $this->integer($input, 'color_intensity', 0, 100),
            distortionMode: $this->enumValue($input, 'glitch_mode', DistortionMode::Signal),
            chaos: $this->integer($input, 'chaos', 0, 100),
            redChannel: $this->integer($input, 'red_channel', -100, 100),
            greenChannel: $this->integer($input, 'green_channel', -100, 100),
            blueChannel: $this->integer($input, 'blue_channel', -100, 100),
            channelOperation: $this->enumValue($input, 'channel_filter', ChannelOperation::None),
            duotoneShadow: $this->hexColor($input, 'duotone_shadow', '#24105e'),
            duotoneHighlight: $this->hexColor($input, 'duotone_highlight', '#ffef5c'),
        );
    }

    /**
     * @param array<string, mixed> $input
     */
    private function integer(array $input, string $key, int $minimum, int $maximum): int
    {
        return max($minimum, min($maximum, (int)($input[$key] ?? 0)));
    }

    /**
     * @template T of \BackedEnum
     * @param array<string, mixed> $input
     * @param T $default
     * @return T
     */
    private function enumValue(array $input, string $key, \BackedEnum $default): \BackedEnum
    {
        $value = $input[$key] ?? null;
        if (!is_string($value)) {
            return $default;
        }

        /** @var T|null $enum */
        $enum = $default::tryFrom($value);

        return $enum ?? $default;
    }

    /**
     * @param array<string, mixed> $input
     */
    private function string(array $input, string $key): string
    {
        return is_string($input[$key] ?? null) ? $input[$key] : '';
    }

    /**
     * @param array<string, mixed> $input
     */
    private function hexColor(array $input, string $key, string $default): string
    {
        $color = $this->string($input, $key);

        return preg_match('/^#[0-9a-fA-F]{6}$/', $color) === 1 ? $color : $default;
    }
}
