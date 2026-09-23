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
use PHPUnit\Framework\TestCase;

final class PostGlitchTest extends TestCase
{
    private string $directory;
    private PostStorage $posts;
    private GlitchController $controller;
    private array $originalPost;
    private array $originalServer;

    protected function setUp(): void
    {
        $this->originalPost = $_POST;
        $this->originalServer = $_SERVER;
        $_SERVER['REQUEST_METHOD'] = 'POST';
        unset($_SERVER['HTTP_X_REQUESTED_WITH']);
        $_POST = ['action' => 'glitch_post', 'post_id' => 'post_example', 'invert' => '1'];
        $this->directory = sys_get_temp_dir() . '/post_glitch_test_' . bin2hex(random_bytes(8));
        mkdir($this->directory . '/posts/post_example', 0775, true);
        $this->posts = new PostStorage($this->directory . '/posts');
        $imageFile = new GdImageFile();
        $pixels = new PixelTransformer();
        $this->controller = new GlitchController(
            new ImageStorage($this->directory),
            new GlitchProcessor($imageFile, new ColorProcessor($pixels), new ChannelProcessor($pixels), new DistortionProcessor()),
            new ImageMorpher($imageFile),
            new GlitchOptionsFactory(),
            $this->posts,
        );
        for ($i = 0; $i <= 5; $i++) {
            $image = imagecreatetruecolor(4, 4);
            imagefill($image, 0, 0, 0x123456);
            if ($i === 5) {
                imagejpeg($image, $this->directory . '/posts/post_example/post_5.jpg', 100);
            } else {
                imagepng($image, $this->directory . '/posts/post_example/post_' . $i . '.png');
            }
            imagedestroy($image);
        }
        file_put_contents($this->directory . '/posts/post_example/post_text.txt', "Title: Test collection\n\nTeaser: Preserve this caption.\n");
    }

    protected function tearDown(): void
    {
        $_POST = $this->originalPost;
        $_SERVER = $this->originalServer;
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->directory, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($files as $file) {
            if ($file->isDir() && !$file->isLink()) rmdir($file->getPathname());
            else unlink($file->getPathname());
        }
        rmdir($this->directory);
    }

    public function testAllImagesAndOverviewAreProcessedWithoutChangingOriginals(): void
    {
        $originals = [];
        foreach (glob($this->directory . '/posts/post_example/*') as $path) {
            $originals[$path] = hash_file('sha256', $path);
        }
        $result = $this->controller->handleRequest();
        self::assertNull($result['error']);
        $saved = $result['postResult'];
        self::assertCount(6, $saved['images']);
        self::assertNotSame('post_example', $saved['id']);
        self::assertSame(file_get_contents($this->directory . '/posts/post_example/post_text.txt'), file_get_contents($this->directory . '/' . $saved['caption']));
        foreach ($saved['images'] as $url) {
            $path = $this->directory . '/' . $url;
            $loaded = (new GdImageFile())->load($path);
            self::assertNotNull($loaded);
            self::assertSame(4, imagesx($loaded->image));
            if (str_ends_with($url, '.png')) self::assertSame(0xEDCBA9, imagecolorat($loaded->image, 0, 0));
            else self::assertSame('image/jpeg', $loaded->mimeType);
        }
        foreach ($originals as $path => $hash) self::assertSame($hash, hash_file('sha256', $path));
        $second = $this->controller->handleRequest();
        self::assertNotSame($saved['id'], $second['postResult']['id']);
        self::assertCount(3, $this->posts->posts());
        $catalog = $this->posts->posts();
        self::assertSame('post_example', $catalog[0]['id']);
        self::assertTrue(str_contains($catalog[2]['id'], '_glitched_'));
    }

    public function testPreviewProcessesTheCollageWithoutCreatingAPost(): void
    {
        $_POST['action'] = 'preview_post';
        $result = $this->controller->handleRequest();

        self::assertNull($result['error']);
        self::assertSame('post_example', $result['postResult']['id']);
        self::assertCount(6, $result['postResult']['images']);
        self::assertCount(1, $this->posts->posts());
        foreach ($result['postResult']['images'] as $url) {
            self::assertStringStartsWith('uploads/post_preview_', $url);
            self::assertFileExists($this->directory . '/' . explode('?', $url)[0]);
        }

        $second = $this->controller->handleRequest();
        self::assertCount(6, glob($this->directory . '/uploads/post_preview_*'));
        self::assertNotSame($result['postResult']['images'][0], $second['postResult']['images'][0]);
    }

    public function testInvalidPostIdsAreRejected(): void
    {
        foreach (['../post_example', 'post_example/../post_example', 'post_missing', ['post_example']] as $id) {
            $_POST['post_id'] = $id;
            $result = $this->controller->handleRequest();
            self::assertNotNull($result['error']);
            self::assertNull($result['postResult']);
        }
        self::assertCount(1, $this->posts->posts());
    }

    public function testFailedImageLeavesNoPartialPostOrDraft(): void
    {
        $image = imagecreatetruecolor(2, 2);
        imagegif($image, $this->directory . '/posts/post_example/post_4.png');
        $result = $this->controller->handleRequest();
        self::assertStringContainsString('post_4.png', $result['error']);
        self::assertNull($result['postResult']);
        self::assertSame(['.', '..', 'post_example'], scandir($this->directory . '/posts'));
    }

    public function testCatalogIgnoresSymlinksAndUnrelatedFiles(): void
    {
        file_put_contents($this->directory . '/posts/post_example/notes.txt', 'not an image');
        symlink($this->directory . '/posts/post_example', $this->directory . '/posts/post_link');
        symlink($this->directory . '/posts/post_example/post_1.png', $this->directory . '/posts/post_example/post_9.png');
        self::assertNull($this->posts->find('post_link'));
        self::assertCount(1, $this->posts->posts());
        self::assertCount(6, $this->posts->find('post_example')['images']);
        self::assertNull($this->posts->imagePath('post_example', '../post_example/post_1.png'));
        self::assertSame('Test collection', $this->posts->find('post_example')['title']);
    }
}
