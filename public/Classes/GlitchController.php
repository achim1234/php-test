<?php

declare(strict_types=1);

namespace Classes;

class GlitchController
{
    private readonly string $uploadDir;
    private readonly string $libDir;
    private readonly string $outputDir;
    private readonly PhotoGlitcher $glitcher;

    public function __construct()
    {
        $this->uploadDir = __DIR__ . '/../uploads/';
        $this->libDir = __DIR__ . '/../lib/';
        $this->outputDir = __DIR__ . '/../output/';
        $this->glitcher = new PhotoGlitcher();

        $this->ensureDirectories();
    }

    private function ensureDirectories(): void
    {
        foreach ([$this->uploadDir, $this->libDir, $this->outputDir] as $dir) {
            if (!is_dir($dir)) {
                mkdir($dir, 0777, true);
            }
        }
    }

    /**
     * Handles the incoming request and returns the data for the view.
     *
     * @return array{glitchedImage: ?string, error: ?string, sourceFile: ?string}
     */
    public function handleRequest(): array
    {
        $glitchedImage = null;
        $error = null;
        $sourceFile = null;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $action = $_POST['action'] ?? '';

            if ($action === 'save_to_output') {
                $this->handleSaveToOutput();
            }

            if ($action === 'morph') {
                $this->handleMorph($glitchedImage, $error);
                $sourceFile = $glitchedImage ? basename($glitchedImage) : null;
                if ($this->isAjax()) {
                    $this->sendJson(['success' => !$error, 'glitchedImage' => $glitchedImage, 'sourceFile' => $sourceFile, 'error' => $error]);
                }
                return compact('glitchedImage', 'error', 'sourceFile');
            }

            $libraryImage = null;
            if (isset($_FILES['photo']) && $_FILES['photo']['error'] !== UPLOAD_ERR_NO_FILE) {
                if ($_FILES['photo']['error'] === UPLOAD_ERR_OK) {
                    $this->handleUpload($sourceFile, $error);
                    $libraryImage = $sourceFile ? 'lib/' . $sourceFile : null;
                } else {
                    $error = 'Upload failed. Please check the file size and try again.';
                }
            } elseif (!empty($_POST['library_image'])) {
                $this->handleLibrarySelection($sourceFile, $error);
            } elseif (!empty($_POST['source_file'])) {
                $this->handleReGlitch($sourceFile, $error);
            } else {
                $error = 'Please upload a JPEG or PNG, or choose an image from the library.';
            }

            if ($sourceFile && !$error) {
                $this->applyGlitch($sourceFile, $glitchedImage, $error);
            }

            if ($this->isAjax()) {
                $this->sendJson(['success' => !$error, 'glitchedImage' => $glitchedImage, 'sourceFile' => $sourceFile, 'libraryImage' => $libraryImage, 'error' => $error]);
            }
        }

        return compact('glitchedImage', 'error', 'sourceFile');
    }

    private function handleSaveToOutput(): void
    {
        $filename = $_POST['filename'] ?? '';
        if ($filename && $this->isValidFilename($filename)) {
            $sourcePath = $this->uploadDir . $filename;
            if (file_exists($sourcePath)) {
                $pathInfo = pathinfo($filename);
                $newFilename = $pathInfo['filename'] . '_' . bin2hex(random_bytes(8)) . '.' . ($pathInfo['extension'] ?? '');
                $destPath = $this->outputDir . $newFilename;
                if (copy($sourcePath, $destPath)) {
                    $this->sendJson(['success' => true, 'outputImage' => 'output/' . $newFilename]);
                }
            }
        }
        $this->sendJson(['success' => false, 'error' => 'Failed to save to output library.']);
    }

    private function handleMorph(?string &$glitchedImage, ?string &$error): void
    {
        $images = $_POST['images'] ?? [];
        if (!is_array($images) || count($images) < 2) {
            $error = "Please select at least two images.";
            return;
        }

        $sourcePaths = [];
        foreach ($images as $img) {
            if (!is_string($img)) continue;
            $path = $this->resolveLibraryImage($img);

            if ($path !== null) {
                $sourcePaths[] = $path;
            }
        }

        if (count($sourcePaths) < 2) {
            $error = "At least two valid images are required for morphing.";
            return;
        }

        $extension = pathinfo($sourcePaths[0], PATHINFO_EXTENSION);
        $destFilename = 'morph_' . uniqid() . '.' . $extension;
        $destPath = $this->uploadDir . $destFilename;

        if ($this->glitcher->morphImages($sourcePaths, $destPath)) {
            $glitchedImage = 'uploads/' . $destFilename;
        } else {
            $error = "Failed to morph images.";
        }
    }

    private function handleUpload(?string &$sourceFile, ?string &$error): void
    {
        $file = $_FILES['photo'];
        $info = getimagesize($file['tmp_name']);
        $extension = match ($info['mime'] ?? '') {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            default => null,
        };
        if ($extension === null) {
            $error = 'Please upload a valid JPEG or PNG image.';
            return;
        }
        $filename = uniqid('upload_', true) . '.' . $extension;
        $destPath = $this->libDir . $filename;

        if (move_uploaded_file($file['tmp_name'], $destPath)) {
            $sourceFile = $filename;
        } else {
            $error = "Failed to move uploaded file.";
        }
    }

    private function handleLibrarySelection(?string &$sourceFile, ?string &$error): void
    {
        $libPath = $this->resolveLibraryImage($_POST['library_image']);
        if ($libPath !== null) {
            $extension = pathinfo($libPath, PATHINFO_EXTENSION);
            $filename = uniqid('glitch_lib_', true) . '.' . $extension;
            $destPath = $this->uploadDir . $filename;
            if (copy($libPath, $destPath)) {
                $sourceFile = $filename;
            } else {
                $error = "Failed to copy library image.";
            }
        } else {
            $error = "Library image not found.";
        }
    }

    private function handleReGlitch(?string &$sourceFile, ?string &$error): void
    {
        $file = $_POST['source_file'];
        if (!$this->isValidFilename($file)) {
            $error = "Invalid source file.";
            return;
        }

        if (!file_exists($this->libDir . $file) && !file_exists($this->uploadDir . $file)) {
            $error = "Source file not found.";
            return;
        }

        $sourceFile = $file;
    }

    private function applyGlitch(string $sourceFile, ?string &$glitchedImage, ?string &$error): void
    {
        $rgbShift = max(0, min(50, (int)($_POST['rgb_shift'] ?? 0)));
        $jitter = max(0, min(100, (int)($_POST['jitter'] ?? 0)));
        $scanlines = max(0, min(50, (int)($_POST['scanlines'] ?? 0)));
        $brightness = max(-100, min(100, (int)($_POST['brightness'] ?? 0)));
        $contrast = max(-100, min(100, (int)($_POST['contrast'] ?? 0)));
        $invert = ($_POST['invert'] ?? '') === '1';
        $pixelate = max(0, min(20, (int)($_POST['pixelate'] ?? 0)));
        $vJitter = max(0, min(100, (int)($_POST['v_jitter'] ?? 0)));
        $presetFilter = is_string($_POST['preset_filter'] ?? null) ? $_POST['preset_filter'] : 'none';
        if (!in_array($presetFilter, ['none', 'grayscale', 'sepia', 'vintage', 'dramatic', 'neon', 'solarize', 'thermal', 'toxic', 'posterize', 'duotone'], true)) {
            $presetFilter = 'none';
        }
        $colorize = is_string($_POST['colorize'] ?? null) ? $_POST['colorize'] : '';
        $colorIntensity = max(0, min(100, (int)($_POST['color_intensity'] ?? 0)));
        $duotoneShadow = is_string($_POST['duotone_shadow'] ?? null) ? $_POST['duotone_shadow'] : '#24105e';
        $duotoneHighlight = is_string($_POST['duotone_highlight'] ?? null) ? $_POST['duotone_highlight'] : '#ffef5c';
        $glitchMode = is_string($_POST['glitch_mode'] ?? null) ? $_POST['glitch_mode'] : 'signal';
        if (!in_array($glitchMode, ['signal', 'datamosh', 'melt', 'mirror', 'vhs', 'shred'], true)) {
            $glitchMode = 'signal';
        }
        $chaos = max(0, min(100, (int)($_POST['chaos'] ?? 0)));
        $redChannel = max(-100, min(100, (int)($_POST['red_channel'] ?? 0)));
        $greenChannel = max(-100, min(100, (int)($_POST['green_channel'] ?? 0)));
        $blueChannel = max(-100, min(100, (int)($_POST['blue_channel'] ?? 0)));
        $channelFilter = is_string($_POST['channel_filter'] ?? null) ? $_POST['channel_filter'] : 'none';
        if (!in_array($channelFilter, [
            'none',
            'red_only',
            'green_only',
            'blue_only',
            'invert_red',
            'invert_green',
            'invert_blue',
            'remove_red',
            'remove_green',
            'remove_blue',
            'swap_red_green',
            'swap_red_blue',
            'swap_green_blue',
            'rotate_rgb',
            'rotate_rbg',
        ], true)) {
            $channelFilter = 'none';
        }

        $isFromLib = file_exists($this->libDir . $sourceFile);
        $sourcePath = $isFromLib ? $this->libDir . $sourceFile : $this->uploadDir . $sourceFile;
        $destFilename = 'glitched_' . $sourceFile;
        $destPath = $this->uploadDir . $destFilename;

        if ($this->glitcher->applyGlitch(
            $sourcePath,
            $destPath,
            $rgbShift,
            $jitter,
            $scanlines,
            $brightness,
            $contrast,
            $invert,
            $pixelate,
            $vJitter,
            $presetFilter,
            $colorize,
            $colorIntensity,
            $glitchMode,
            $chaos,
            $redChannel,
            $greenChannel,
            $blueChannel,
            $channelFilter,
            $duotoneShadow,
            $duotoneHighlight,
        )) {
            $glitchedImage = 'uploads/' . $destFilename . '?v=' . bin2hex(random_bytes(8));
        } else {
            $error = "Failed to apply glitch effect.";
        }
    }

    private function resolveLibraryImage(string $image): ?string
    {
        $parts = explode('/', $image);
        $filename = array_pop($parts);
        if (!$this->isValidFilename($filename) || count($parts) > 1) {
            return null;
        }

        $directories = match ($parts[0] ?? '') {
            'lib' => [$this->libDir],
            'output' => [$this->outputDir],
            '' => [$this->libDir, $this->outputDir],
            default => [],
        };
        foreach ($directories as $directory) {
            if (is_file($directory . $filename)) {
                return $directory . $filename;
            }
        }
        return null;
    }

    private function isValidFilename(string $filename): bool
    {
        return $filename !== '' && !str_contains($filename, "\0") && !str_contains($filename, '..') && !str_contains($filename, '/') && !str_contains($filename, '\\');
    }

    private function isAjax(): bool
    {
        return isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest';
    }

    private function sendJson(array $data): void
    {
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }

    /**
     * @return string[]
     */
    public function getOutputImages(): array
    {
        return is_dir($this->outputDir) ? array_diff(scandir($this->outputDir), ['.', '..', '.gitkeep']) : [];
    }

    /**
     * @return string[]
     */
    public function getLibraryImages(): array
    {
        return is_dir($this->libDir) ? array_diff(scandir($this->libDir), ['.', '..', '.gitkeep']) : [];
    }
}
