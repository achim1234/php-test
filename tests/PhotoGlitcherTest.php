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
