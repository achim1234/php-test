<?php

declare(strict_types=1);

namespace App\Image;

use App\Enum\ChannelOperation;
use GdImage;

final readonly class ChannelProcessor
{
    public function __construct(private PixelTransformer $pixels) {}

    public function apply(GdImage $image, GlitchOptions $options): void
    {
        if (
            $options->redChannel === 0
            && $options->greenChannel === 0
            && $options->blueChannel === 0
            && $options->channelOperation === ChannelOperation::None
        ) {
            return;
        }

        $redOffset = (int)round($options->redChannel * 2.55);
        $greenOffset = (int)round($options->greenChannel * 2.55);
        $blueOffset = (int)round($options->blueChannel * 2.55);
        $operation = $options->channelOperation;

        $this->pixels->transform(
            $image,
            static function (int $red, int $green, int $blue) use ($redOffset, $greenOffset, $blueOffset, $operation): array {
                $red = max(0, min(255, $red + $redOffset));
                $green = max(0, min(255, $green + $greenOffset));
                $blue = max(0, min(255, $blue + $blueOffset));

                return match ($operation) {
                    ChannelOperation::RedOnly => [$red, 0, 0],
                    ChannelOperation::GreenOnly => [0, $green, 0],
                    ChannelOperation::BlueOnly => [0, 0, $blue],
                    ChannelOperation::InvertRed => [255 - $red, $green, $blue],
                    ChannelOperation::InvertGreen => [$red, 255 - $green, $blue],
                    ChannelOperation::InvertBlue => [$red, $green, 255 - $blue],
                    ChannelOperation::RemoveRed => [0, $green, $blue],
                    ChannelOperation::RemoveGreen => [$red, 0, $blue],
                    ChannelOperation::RemoveBlue => [$red, $green, 0],
                    ChannelOperation::SwapRedGreen => [$green, $red, $blue],
                    ChannelOperation::SwapRedBlue => [$blue, $green, $red],
                    ChannelOperation::SwapGreenBlue => [$red, $blue, $green],
                    ChannelOperation::RotateRgb => [$green, $blue, $red],
                    ChannelOperation::RotateRbg => [$blue, $red, $green],
                    ChannelOperation::None => [$red, $green, $blue],
                };
            },
        );
    }
}
