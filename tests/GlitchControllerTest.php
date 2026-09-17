<?php

declare(strict_types=1);

use App\Http\GlitchController;
use PHPUnit\Framework\TestCase;

final class GlitchControllerTest extends TestCase
{
    private array $post;
    private array $server;
    private array $uploads;
    /** @var string[] */
    private array $files = [];

    protected function setUp(): void
    {
        $this->post = $_POST;
        $this->server = $_SERVER;
        $this->uploads = $_FILES;
        $_POST = ['rgb_shift' => 0, 'jitter' => 0, 'scanlines' => 0];
        $_FILES = [];
        $_SERVER['REQUEST_METHOD'] = 'POST';
        unset($_SERVER['HTTP_X_REQUESTED_WITH']);
    }

    protected function tearDown(): void
    {
        $_POST = $this->post;
        $_SERVER = $this->server;
        $_FILES = $this->uploads;
        foreach ($this->files as $file) {
            if (is_file($file)) unlink($file);
        }
    }

    private function fixture(string $directory, int $color, ?string $name = null): string
    {
        $name ??= 'glitch_test_' . bin2hex(random_bytes(8)) . '.png';
        $path = __DIR__ . '/../public/' . $directory . '/' . $name;
        $this->files[] = $path;
        $image = imagecreatetruecolor(3, 3);
        imagefill($image, 0, 0, $color);
        imagepng($image, $path);
        return $name;
    }

    private function request(): array
    {
        /** @var GlitchController $controller */
        $controller = require __DIR__ . '/../config/bootstrap.php';
        $data = $controller->handleRequest();
        foreach (['sourceFile', 'glitchedImage'] as $key) {
            if ($data[$key] !== null) {
                $this->files[] = __DIR__ . '/../public/uploads/' . basename(explode('?', $data[$key])[0]);
            }
        }
        return $data;
    }

    private function resultPixel(array $data): int
    {
        $path = __DIR__ . '/../public/' . explode('?', $data['glitchedImage'])[0];
        return imagecolorat(imagecreatefrompng($path), 0, 0);
    }

    public function testRepeatedEditsUseOriginalAndRefreshTheImageUrl(): void
    {
        $filename = $this->fixture('lib', 0x102030);
        $_POST['source_file'] = $filename;
        $_POST['brightness'] = 30;
        $first = $this->request();
        self::assertNull($first['error']);
        self::assertNotSame(0x102030, $this->resultPixel($first));

        $_POST['brightness'] = 0;
        $second = $this->request();
        self::assertSame($filename, $second['sourceFile']);
        self::assertSame(0x102030, $this->resultPixel($second));
        self::assertNotSame($first['glitchedImage'], $second['glitchedImage']);
    }

    public function testMissingEffectValuesDefaultToAnUnfilteredImage(): void
    {
        $filename = $this->fixture('lib', 0x123456);
        $_POST = ['source_file' => $filename];

        $data = $this->request();

        self::assertNull($data['error']);
        self::assertSame(0x123456, $this->resultPixel($data));
    }

    public function testDuotonePaletteIsAppliedThroughTheController(): void
    {
        $filename = $this->fixture('lib', 0x000000);
        $_POST['source_file'] = $filename;
        $_POST['preset_filter'] = 'duotone';
        $_POST['duotone_shadow'] = '#123456';
        $_POST['duotone_highlight'] = '#fedcba';

        $data = $this->request();

        self::assertNull($data['error']);
        self::assertSame(0x123456, $this->resultPixel($data));
    }

    public function testLibrarySelectionDistinguishesSameNamedFiles(): void
    {
        $filename = $this->fixture('lib', 0xFF0000);
        $this->fixture('output', 0x0000FF, $filename);
        $_POST['library_image'] = 'output/' . $filename;
        $data = $this->request();
        self::assertNull($data['error']);
        self::assertSame(0x0000FF, $this->resultPixel($data));
    }

    public function testCompositeCanBeEditedAsANewOriginal(): void
    {
        $first = $this->fixture('lib', 0xFF0000);
        $second = $this->fixture('output', 0x0000FF);
        $_POST['action'] = 'morph';
        $_POST['images'] = ['lib/' . $first, 'output/' . $second];
        $morph = $this->request();
        self::assertNull($morph['error']);
        self::assertNotNull($morph['sourceFile']);
        self::assertSame(0x7F007F, $this->resultPixel($morph));

        unset($_POST['action'], $_POST['images']);
        $_POST['source_file'] = $morph['sourceFile'];
        $edit = $this->request();
        self::assertNull($edit['error']);
        self::assertSame(0x7F007F, $this->resultPixel($edit));
    }

    public function testFailedUploadDoesNotSilentlyEditThePreviousPhoto(): void
    {
        $_POST['source_file'] = $this->fixture('lib', 0xFFFFFF);
        $_FILES['photo'] = ['error' => UPLOAD_ERR_INI_SIZE];
        $data = $this->request();
        self::assertNotNull($data['error']);
        self::assertNull($data['glitchedImage']);
    }

    public function testMissingSourceReportsAnError(): void
    {
        self::assertNotNull($this->request()['error']);
    }
}
