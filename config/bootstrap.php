<?php

declare(strict_types=1);

use App\Http\GlitchController;
use App\Image\ChannelProcessor;
use App\Image\ColorProcessor;
use App\Image\DistortionProcessor;
use App\Image\GdImageFile;
use App\Image\GlitchOptionsFactory;
use App\Image\GlitchProcessor;
use App\Image\ImageMorpher;
use App\Image\PixelTransformer;
use App\Storage\ImageStorage;
use App\Storage\PostStorage;

$imageFile = new GdImageFile();
$pixels = new PixelTransformer();

return new GlitchController(
    new ImageStorage(__DIR__ . '/../public'),
    new GlitchProcessor(
        $imageFile,
        new ColorProcessor($pixels),
        new ChannelProcessor($pixels),
        new DistortionProcessor(),
    ),
    new ImageMorpher($imageFile),
    new GlitchOptionsFactory(),
    new PostStorage(__DIR__ . '/../public/posts'),
);
