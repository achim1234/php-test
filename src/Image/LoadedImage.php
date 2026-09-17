<?php

declare(strict_types=1);

namespace App\Image;

use GdImage;

final readonly class LoadedImage
{
    public function __construct(
        public GdImage $image,
        public string $mimeType,
    ) {}
}
