<?php

declare(strict_types=1);

use Classes\PhotoGlitcher;
use PHPUnit\Framework\TestCase;

final class PhotoGlitcherTest extends TestCase
{
    /** @var string[] */
    private array $files = [];

    protected function tearDown(): void
    {
        foreach ($this->files as $file) {
            if (is_file($file)) unlink($file);
        }
    }

    private function imagePath(?GdImage $image = null): string
    {
        $path = sys_get_temp_dir() . '/glitch_test_' . bin2hex(random_bytes(8)) . '.png';
        $this->files[] = $path;
        if ($image !== null) imagepng($image, $path);
        return $path;
    }

    private function patternedImage(int $width = 32, int $height = 24): GdImage
    {
        $image = imagecreatetruecolor($width, $height);
        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                imagesetpixel(
                    $image,
                    $x,
                    $y,
                    ((($x * 37 + $y * 11) % 256) << 16)
                    | ((($x * 13 + $y * 43) % 256) << 8)
                    | (($x * 29 + $y * 17) % 256),
                );
            }
        }

        return $image;
    }

    public function testRgbShiftKeepsChannelsAndDimensions(): void
    {
        $image = imagecreatetruecolor(4, 1);
        $pixels = [0x123456, 0x789ABC, 0xDEF012, 0x345678];
        foreach ($pixels as $x => $pixel) imagesetpixel($image, $x, 0, $pixel);
        $output = $this->imagePath();

        self::assertTrue((new PhotoGlitcher())->applyGlitch($this->imagePath($image), $output, 1, 0, 0));

        $result = imagecreatefrompng($output);
        self::assertSame(4, imagesx($result));
        self::assertSame(1, imagesy($result));
        self::assertSame(0x0034BC, imagecolorat($result, 0, 0));
        self::assertSame(0x129A12, imagecolorat($result, 1, 0));
        self::assertSame(0x78F078, imagecolorat($result, 2, 0));
        self::assertSame(0xDE5600, imagecolorat($result, 3, 0));
    }

    public function testZeroEffectsPreservePixelsAndTransparency(): void
    {
        $image = imagecreatetruecolor(3, 1);
        imagealphablending($image, false);
        imagesavealpha($image, true);
        $pixels = [0x00123456, 0x40789ABC, 0x7FDEF012];
        foreach ($pixels as $x => $pixel) imagesetpixel($image, $x, 0, $pixel);
        $output = $this->imagePath();

        self::assertTrue((new PhotoGlitcher())->applyGlitch($this->imagePath($image), $output, 0, 0, 0));
        $result = imagecreatefrompng($output);
        foreach ($pixels as $x => $pixel) self::assertSame($pixel, imagecolorat($result, $x, 0));
    }

    public function testPalettePngUsesColorsInsteadOfPaletteIndexes(): void
    {
        $image = imagecreate(2, 1);
        $red = imagecolorallocate($image, 255, 0, 0);
        $green = imagecolorallocate($image, 0, 255, 0);
        imagesetpixel($image, 0, 0, $red);
        imagesetpixel($image, 1, 0, $green);
        $output = $this->imagePath();

        self::assertTrue((new PhotoGlitcher())->applyGlitch($this->imagePath($image), $output, 1, 0, 0));
        $result = imagecreatefrompng($output);
        self::assertSame(0, imagecolorat($result, 0, 0));
        self::assertSame(0xFFFF00, imagecolorat($result, 1, 0));
    }

    public function testScanlinesHandleImagesSmallerThanFivePixels(): void
    {
        $output = $this->imagePath();
        self::assertTrue((new PhotoGlitcher())->applyGlitch(
            $this->imagePath(imagecreatetruecolor(1, 1)), $output, 0, 1, 50
        ));
        self::assertSame([1, 1], array_slice(getimagesize($output), 0, 2));
    }

    public function testDistortionEnginesTransformPixelsAndPreserveDimensions(): void
    {
        $source = $this->imagePath($this->patternedImage());
        $sourceHash = md5_file($source);

        foreach (['datamosh', 'melt', 'mirror', 'vhs', 'shred'] as $mode) {
            $output = $this->imagePath();
            self::assertTrue((new PhotoGlitcher())->applyGlitch(
                sourcePath: $source,
                destPath: $output,
                rgbShift: 0,
                jitter: 0,
                scanlines: 0,
                glitchMode: $mode,
                chaos: 100,
            ), $mode);
            self::assertSame([32, 24], array_slice(getimagesize($output), 0, 2), $mode);
            self::assertNotSame($sourceHash, md5_file($output), $mode);
        }
    }

    public function testExtremeColorProcessesTransformPixels(): void
    {
        $source = $this->imagePath($this->patternedImage(12, 10));
        $sourceHash = md5_file($source);

        foreach (['neon', 'solarize', 'thermal', 'toxic', 'posterize'] as $filter) {
            $output = $this->imagePath();
            self::assertTrue((new PhotoGlitcher())->applyGlitch(
                sourcePath: $source,
                destPath: $output,
                rgbShift: 0,
                jitter: 0,
                scanlines: 0,
                presetFilter: $filter,
                chaos: 0,
            ), $filter);
            self::assertNotSame($sourceHash, md5_file($output), $filter);
        }
    }

    public function testDuotoneMapsLuminanceToCustomPaletteAndPreservesAlpha(): void
    {
        $image = imagecreatetruecolor(3, 1);
        imagealphablending($image, false);
        imagesavealpha($image, true);
        imagesetpixel($image, 0, 0, 0x00000000);
        imagesetpixel($image, 1, 0, 0x00808080);
        imagesetpixel($image, 2, 0, 0x40FFFFFF);
        $output = $this->imagePath();

        self::assertTrue((new PhotoGlitcher())->applyGlitch(
            sourcePath: $this->imagePath($image),
            destPath: $output,
            rgbShift: 0,
            jitter: 0,
            scanlines: 0,
            presetFilter: 'duotone',
            chaos: 0,
            duotoneShadow: '#120034',
            duotoneHighlight: '#f0e050',
        ));

        $result = imagecreatefrompng($output);
        self::assertSame(0x00120034, imagecolorat($result, 0, 0));
        self::assertSame(0x00817042, imagecolorat($result, 1, 0));
        self::assertSame(0x40F0E050, imagecolorat($result, 2, 0));
    }

    public function testSingleChannelOperationsProduceExpectedColors(): void
    {
        $image = imagecreatetruecolor(1, 1);
        imagesetpixel($image, 0, 0, 0x204060);
        $source = $this->imagePath($image);
        $expectedColors = [
            'red_only' => 0x200000,
            'green_only' => 0x004000,
            'blue_only' => 0x000060,
            'invert_red' => 0xDF4060,
            'invert_green' => 0x20BF60,
            'invert_blue' => 0x20409F,
            'remove_red' => 0x004060,
            'remove_green' => 0x200060,
            'remove_blue' => 0x204000,
            'swap_red_green' => 0x402060,
            'swap_red_blue' => 0x604020,
            'swap_green_blue' => 0x206040,
            'rotate_rgb' => 0x406020,
            'rotate_rbg' => 0x602040,
        ];

        foreach ($expectedColors as $filter => $expectedColor) {
            $output = $this->imagePath();
            self::assertTrue((new PhotoGlitcher())->applyGlitch(
                sourcePath: $source,
                destPath: $output,
                rgbShift: 0,
                jitter: 0,
                scanlines: 0,
                chaos: 0,
                channelFilter: $filter,
            ), $filter);
            self::assertSame($expectedColor, imagecolorat(imagecreatefrompng($output), 0, 0), $filter);
        }
    }

    public function testChannelLevelsClampAtColorBounds(): void
    {
        $image = imagecreatetruecolor(1, 1);
        imagesetpixel($image, 0, 0, 0x204060);
        $output = $this->imagePath();

        self::assertTrue((new PhotoGlitcher())->applyGlitch(
            sourcePath: $this->imagePath($image),
            destPath: $output,
            rgbShift: 0,
            jitter: 0,
            scanlines: 0,
            chaos: 0,
            redChannel: 100,
            greenChannel: -100,
            blueChannel: 10,
        ));
        self::assertSame(0xFF007A, imagecolorat(imagecreatefrompng($output), 0, 0));
    }

    public function testMorphAveragesAndTilesDifferentSizedImages(): void
    {
        $first = imagecreatetruecolor(2, 1);
        imagesetpixel($first, 0, 0, 0xFF0000);
        imagesetpixel($first, 1, 0, 0x00FF00);
        $second = imagecreatetruecolor(1, 2);
        imagesetpixel($second, 0, 0, 0x0000FF);
        imagesetpixel($second, 0, 1, 0xFFFFFF);
        $output = $this->imagePath();

        self::assertTrue((new PhotoGlitcher())->morphImages([$this->imagePath($first), $this->imagePath($second)], $output));
        $result = imagecreatefrompng($output);
        self::assertSame([2, 2], array_slice(getimagesize($output), 0, 2));
        self::assertSame(0x7F007F, imagecolorat($result, 0, 0));
        self::assertSame(0x007F7F, imagecolorat($result, 1, 0));
        self::assertSame(0xFF7F7F, imagecolorat($result, 0, 1));
        self::assertSame(0x7FFF7F, imagecolorat($result, 1, 1));
    }
}
