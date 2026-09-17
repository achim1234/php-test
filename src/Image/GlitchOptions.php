<?php

declare(strict_types=1);

namespace App\Image;

use App\Enum\ChannelOperation;
use App\Enum\ColorProcess;
use App\Enum\DistortionMode;

final readonly class GlitchOptions
{
    public function __construct(
        public int $rgbShift = 0,
        public int $jitter = 0,
        public int $scanlines = 0,
        public int $brightness = 0,
        public int $contrast = 0,
        public bool $invert = false,
        public int $pixelate = 0,
        public int $verticalJitter = 0,
        public ColorProcess $colorProcess = ColorProcess::None,
        public string $tintColor = '',
        public int $tintIntensity = 0,
        public DistortionMode $distortionMode = DistortionMode::Signal,
        public int $chaos = 0,
        public int $redChannel = 0,
        public int $greenChannel = 0,
        public int $blueChannel = 0,
        public ChannelOperation $channelOperation = ChannelOperation::None,
        public string $duotoneShadow = '#24105e',
        public string $duotoneHighlight = '#ffef5c',
    ) {}
}
