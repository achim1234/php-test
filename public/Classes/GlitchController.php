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
                if ($this->isAjax()) {
                    $this->sendJson(['success' => !$error, 'glitchedImage' => $glitchedImage, 'error' => $error]);
                }
                return compact('glitchedImage', 'error', 'sourceFile');
            }

            if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
                $this->handleUpload($sourceFile, $error);
            } elseif (!empty($_POST['library_image'])) {
                $this->handleLibrarySelection($sourceFile, $error);
            } elseif (!empty($_POST['source_file'])) {
                $this->handleReGlitch($sourceFile, $error);
            }

            if ($sourceFile && !$error) {
                $this->applyGlitch($sourceFile, $glitchedImage, $error);
            }

            if ($this->isAjax()) {
                $this->sendJson(['success' => !$error, 'glitchedImage' => $glitchedImage, 'error' => $error]);
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
                $timestamp = date('Ymd_His');
                $newFilename = $pathInfo['filename'] . '_' . $timestamp . '.' . ($pathInfo['extension'] ?? '');
                $destPath = $this->outputDir . $newFilename;
                if (copy($sourcePath, $destPath)) {
                    $this->sendJson(['success' => true]);
                }
            }
        }
        $this->sendJson(['success' => false, 'error' => 'Failed to save to output library.']);
    }

    private function handleMorph(?string &$glitchedImage, ?string &$error): void
    {
        $images = $_POST['images'] ?? [];
        if (count($images) < 2) {
            $error = "Please select at least two images.";
            return;
        }

        $sourcePaths = [];
        foreach ($images as $img) {
            if (!$this->isValidFilename($img)) continue;

            $path = $this->libDir . $img;
            if (!file_exists($path)) {
                $path = $this->outputDir . $img;
            }

            if (file_exists($path)) {
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
            $glitchedImage = 'uploads/' . $destFilename . '?t=' . time();
        } else {
            $error = "Failed to morph images.";
        }
    }

    private function handleUpload(?string &$sourceFile, ?string &$error): void
    {
        $file = $_FILES['photo'];
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
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
        $libImage = $_POST['library_image'];
        if (!$this->isValidFilename($libImage)) {
            $error = "Invalid library image.";
            return;
        }

        $libPath = $this->libDir . $libImage;
        if (!file_exists($libPath)) {
            $libPath = $this->outputDir . $libImage;
        }

        if (file_exists($libPath)) {
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
        $rgbShift = (int)($_POST['rgb_shift'] ?? 10);
        $jitter = (int)($_POST['jitter'] ?? 20);
        $scanlines = (int)($_POST['scanlines'] ?? 10);
        $brightness = (int)($_POST['brightness'] ?? 0);
        $contrast = (int)($_POST['contrast'] ?? 0);
        $invert = ($_POST['invert'] ?? '') === '1';
        $pixelate = (int)($_POST['pixelate'] ?? 0);
        $vJitter = (int)($_POST['v_jitter'] ?? 0);

        $isFromLib = file_exists($this->libDir . $sourceFile);
        $sourcePath = $isFromLib ? $this->libDir . $sourceFile : $this->uploadDir . $sourceFile;
        $destFilename = 'glitched_' . $sourceFile;
        $destPath = $this->uploadDir . $destFilename;

        if ($this->glitcher->applyGlitch($sourcePath, $destPath, $rgbShift, $jitter, $scanlines, $brightness, $contrast, $invert, $pixelate, $vJitter)) {
            $glitchedImage = 'uploads/' . $destFilename . '?t=' . time();
        } else {
            $error = "Failed to apply glitch effect.";
        }
    }

    private function isValidFilename(string $filename): bool
    {
        return !str_contains($filename, '..') && !str_contains($filename, '/') && !str_contains($filename, '\\');
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
