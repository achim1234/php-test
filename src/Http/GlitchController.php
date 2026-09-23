<?php

declare(strict_types=1);

namespace App\Http;

use App\Image\GlitchOptionsFactory;
use App\Image\GlitchProcessor;
use App\Image\ImageMorpher;
use App\Storage\ImageStorage;
use App\Storage\PostStorage;
use RuntimeException;

final readonly class GlitchController
{
    public function __construct(
        private ImageStorage $storage,
        private GlitchProcessor $glitchProcessor,
        private ImageMorpher $imageMorpher,
        private GlitchOptionsFactory $optionsFactory,
        private PostStorage $posts,
    ) {}

    /**
     * @return array{glitchedImage: ?string, error: ?string, sourceFile: ?string, postResult?: ?array}
     */
    public function handleRequest(): array
    {
        $glitchedImage = null;
        $error = null;
        $sourceFile = null;

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return compact('glitchedImage', 'error', 'sourceFile');
        }

        $action = $_POST['action'] ?? '';
        if ($action === 'preview_post') {
            $postResult = null;
            try {
                $postResult = $this->previewPost(is_string($_POST['post_id'] ?? null) ? $_POST['post_id'] : '');
            } catch (RuntimeException $exception) {
                $error = $exception->getMessage();
            }
            if ($this->isAjax()) {
                $this->sendJson(['success' => $error === null, 'post' => $postResult, 'error' => $error]);
            }

            return compact('glitchedImage', 'error', 'sourceFile', 'postResult');
        }
        if ($action === 'glitch_post') {
            $postResult = null;
            try {
                $postResult = $this->glitchPost(is_string($_POST['post_id'] ?? null) ? $_POST['post_id'] : '');
            } catch (RuntimeException $exception) {
                $error = $exception->getMessage();
            }
            if ($this->isAjax()) {
                $this->sendJson(['success' => $error === null, 'post' => $postResult, 'error' => $error]);
            }

            return compact('glitchedImage', 'error', 'sourceFile', 'postResult');
        }
        if ($action === 'save_to_output') {
            $this->handleSaveToOutput();
        }

        if ($action === 'morph') {
            $this->handleMorph($glitchedImage, $error);
            $sourceFile = $glitchedImage !== null ? basename($glitchedImage) : null;
            if ($this->isAjax()) {
                $this->sendJson([
                    'success' => $error === null,
                    'glitchedImage' => $glitchedImage,
                    'sourceFile' => $sourceFile,
                    'error' => $error,
                ]);
            }

            return compact('glitchedImage', 'error', 'sourceFile');
        }

        $libraryImage = null;
        if (isset($_FILES['photo']) && $_FILES['photo']['error'] !== UPLOAD_ERR_NO_FILE) {
            if ($_FILES['photo']['error'] === UPLOAD_ERR_OK) {
                $sourceFile = $this->storage->storeUpload($_FILES['photo']);
                if ($sourceFile === null) {
                    $error = 'Please upload a valid JPEG or PNG image.';
                } else {
                    $libraryImage = 'lib/' . $sourceFile;
                }
            } else {
                $error = 'Upload failed. Please check the file size and try again.';
            }
        } elseif (is_string($_POST['library_image'] ?? null) && $_POST['library_image'] !== '') {
            $sourceFile = $this->storage->importLibraryImage($_POST['library_image']);
            if ($sourceFile === null) {
                $error = 'Library image not found.';
            }
        } elseif (is_string($_POST['source_file'] ?? null) && $_POST['source_file'] !== '') {
            $sourceFile = $_POST['source_file'];
            if ($this->storage->resolveSource($sourceFile) === null) {
                $sourceFile = null;
                $error = 'Source file not found.';
            }
        } else {
            $error = 'Please upload a JPEG or PNG, or choose an image from the library.';
        }

        if ($sourceFile !== null && $error === null) {
            $this->applyGlitch($sourceFile, $glitchedImage, $error);
        }

        if ($this->isAjax()) {
            $this->sendJson([
                'success' => $error === null,
                'glitchedImage' => $glitchedImage,
                'sourceFile' => $sourceFile,
                'libraryImage' => $libraryImage,
                'error' => $error,
            ]);
        }

        return compact('glitchedImage', 'error', 'sourceFile');
    }

    private function handleSaveToOutput(): void
    {
        $filename = is_string($_POST['filename'] ?? null) ? $_POST['filename'] : '';
        $outputFilename = $this->storage->copyToOutput($filename);

        if ($outputFilename !== null) {
            $this->sendJson(['success' => true, 'outputImage' => 'output/' . $outputFilename]);
        }

        $this->sendJson(['success' => false, 'error' => 'Failed to save to output library.']);
    }

    /** @return array{id: string, title: string, images: list<string>, caption: ?string} */
    private function glitchPost(string $id): array
    {
        $post = $this->posts->find($id);
        if ($post === null) {
            throw new RuntimeException('Post not found or it contains no supported images.');
        }
        $options = $this->optionsFactory->fromArray($_POST);
        $draft = $this->posts->createDraft();
        try {
            foreach ($post['images'] as $image) {
                // Refresh the time budget for each full-resolution image in the batch.
                set_time_limit(60);
                $filename = basename($image);
                $source = $this->posts->imagePath($id, $filename);
                if ($source === null || !$this->glitchProcessor->process($source, $draft['path'] . '/' . $filename, $options)) {
                    throw new RuntimeException('Unable to process ' . $filename . '. No glitched post was saved.');
                }
            }
            $this->posts->copyCaption($id, $draft['path']);
            $this->posts->publish($draft);
        } finally {
            if (is_dir($draft['path'])) {
                $this->posts->discardDraft($draft['path']);
            }
        }

        return $this->posts->find($draft['id']);
    }

    /** @return array{id: string, title: string, images: list<string>, caption: ?string} */
    private function previewPost(string $id): array
    {
        $post = $this->posts->find($id);
        if ($post === null) {
            throw new RuntimeException('Post not found or it contains no supported images.');
        }
        $options = $this->optionsFactory->fromArray($_POST);
        $this->storage->clearPostPreviews();
        $preview = $post;
        $preview['images'] = [];
        foreach ($post['images'] as $image) {
            set_time_limit(60);
            $filename = basename($image);
            $source = $this->posts->imagePath($id, $filename);
            $destination = $this->storage->createPostPreviewDestination($filename);
            if ($source === null || !$this->glitchProcessor->process($source, $destination['path'], $options)) {
                throw new RuntimeException('Unable to preview ' . $filename . '.');
            }
            $preview['images'][] = 'uploads/' . $destination['filename'] . '?v=' . bin2hex(random_bytes(6));
        }

        return $preview;
    }

    /** @return list<array{id: string, title: string, images: list<string>, caption: ?string}> */
    public function getPosts(): array
    {
        return $this->posts->posts();
    }

    private function handleMorph(?string &$glitchedImage, ?string &$error): void
    {
        $images = $_POST['images'] ?? [];
        if (!is_array($images) || count($images) < 2) {
            $error = 'Please select at least two images.';
            return;
        }

        $sourcePaths = [];
        foreach ($images as $image) {
            if (!is_string($image)) {
                continue;
            }
            $path = $this->storage->resolveLibraryImage($image);
            if ($path !== null) {
                $sourcePaths[] = $path;
            }
        }

        if (count($sourcePaths) < 2) {
            $error = 'At least two valid images are required for morphing.';
            return;
        }

        $destination = $this->storage->createMorphDestination($sourcePaths[0]);
        if ($this->imageMorpher->morph($sourcePaths, $destination['path'])) {
            $glitchedImage = 'uploads/' . $destination['filename'];
        } else {
            $error = 'Failed to morph images.';
        }
    }

    private function applyGlitch(string $sourceFile, ?string &$glitchedImage, ?string &$error): void
    {
        $sourcePath = $this->storage->resolveSource($sourceFile);
        if ($sourcePath === null) {
            $error = 'Source file not found.';
            return;
        }

        $destination = $this->storage->createGlitchDestination($sourceFile);
        $options = $this->optionsFactory->fromArray($_POST);

        if ($this->glitchProcessor->process($sourcePath, $destination['path'], $options)) {
            $glitchedImage = 'uploads/' . $destination['filename'] . '?v=' . bin2hex(random_bytes(8));
        } else {
            $error = 'Failed to apply glitch effect.';
        }
    }

    private function isAjax(): bool
    {
        return ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? null) === 'XMLHttpRequest';
    }

    /** @param array<string, mixed> $data */
    private function sendJson(array $data): never
    {
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }

    /** @return string[] */
    public function getOutputImages(): array
    {
        return $this->storage->outputImages();
    }

    /** @return string[] */
    public function getLibraryImages(): array
    {
        return $this->storage->libraryImages();
    }
}
