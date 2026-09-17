<?php

declare(strict_types=1);

namespace App\Image;

use GdImage;

final readonly class GlitchProcessor
{
    public function __construct(
        private GdImageFile $imageFile,
        private ColorProcessor $colors,
        private ChannelProcessor $channels,
        private DistortionProcessor $distortions,
    ) {}

    public function process(string $sourcePath, string $destinationPath, GlitchOptions $options): bool
    {
        $loaded = $this->imageFile->load($sourcePath);
        if ($loaded === null) {
            return false;
        }

        $source = $loaded->image;
        $destination = null;

        try {
            $this->colors->apply($source, $options);
            $this->channels->apply($source, $options);
            $destination = $this->distortions->apply($source, $options);

            return $this->imageFile->save($destination, $destinationPath, $loaded->mimeType);
        } finally {
            imagedestroy($source);
            if ($destination instanceof GdImage) {
                imagedestroy($destination);
            }
        }
    }
}
