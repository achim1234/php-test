<?php

declare(strict_types=1);

namespace App\Storage;

use RuntimeException;

final readonly class ImageStorage
{
    private string $uploadDirectory;
    private string $libraryDirectory;
    private string $outputDirectory;

    public function __construct(string $publicDirectory)
    {
        $publicDirectory = rtrim($publicDirectory, '/');
        $this->uploadDirectory = $publicDirectory . '/uploads/';
        $this->libraryDirectory = $publicDirectory . '/lib/';
        $this->outputDirectory = $publicDirectory . '/output/';

        foreach ([$this->uploadDirectory, $this->libraryDirectory, $this->outputDirectory] as $directory) {
            if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
                throw new RuntimeException(sprintf('Unable to create image directory "%s".', $directory));
            }
        }
    }

    /**
     * @param array{tmp_name?: mixed} $file
     */
    public function storeUpload(array $file): ?string
    {
        $temporaryPath = $file['tmp_name'] ?? null;
        if (!is_string($temporaryPath)) {
            return null;
        }

        $info = getimagesize($temporaryPath);
        $extension = match ($info['mime'] ?? '') {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            default => null,
        };
        if ($extension === null) {
            return null;
        }

        $filename = uniqid('upload_', true) . '.' . $extension;

        return move_uploaded_file($temporaryPath, $this->libraryDirectory . $filename)
            ? $filename
            : null;
    }

    public function importLibraryImage(string $image): ?string
    {
        $sourcePath = $this->resolveLibraryImage($image);
        if ($sourcePath === null) {
            return null;
        }

        $extension = pathinfo($sourcePath, PATHINFO_EXTENSION);
        $filename = uniqid('glitch_lib_', true) . '.' . $extension;

        return copy($sourcePath, $this->uploadDirectory . $filename)
            ? $filename
            : null;
    }

    public function resolveLibraryImage(string $image): ?string
    {
        $parts = explode('/', $image);
        $filename = array_pop($parts);
        if (!$this->isValidFilename($filename) || count($parts) > 1) {
            return null;
        }

        $directories = match ($parts[0] ?? '') {
            'lib' => [$this->libraryDirectory],
            'output' => [$this->outputDirectory],
            '' => [$this->libraryDirectory, $this->outputDirectory],
            default => [],
        };

        foreach ($directories as $directory) {
            if (is_file($directory . $filename)) {
                return $directory . $filename;
            }
        }

        return null;
    }

    public function resolveSource(string $filename): ?string
    {
        if (!$this->isValidFilename($filename)) {
            return null;
        }

        foreach ([$this->libraryDirectory, $this->uploadDirectory] as $directory) {
            if (is_file($directory . $filename)) {
                return $directory . $filename;
            }
        }

        return null;
    }

    /**
     * @return array{filename: string, path: string}
     */
    public function createGlitchDestination(string $sourceFilename): array
    {
        $filename = 'glitched_' . $sourceFilename;

        return ['filename' => $filename, 'path' => $this->uploadDirectory . $filename];
    }

    /**
     * @return array{filename: string, path: string}
     */
    public function createMorphDestination(string $sourcePath): array
    {
        $extension = pathinfo($sourcePath, PATHINFO_EXTENSION);
        $filename = 'morph_' . uniqid() . '.' . $extension;

        return ['filename' => $filename, 'path' => $this->uploadDirectory . $filename];
    }

    public function copyToOutput(string $filename): ?string
    {
        if (!$this->isValidFilename($filename)) {
            return null;
        }

        $sourcePath = $this->uploadDirectory . $filename;
        if (!is_file($sourcePath)) {
            return null;
        }

        $pathInfo = pathinfo($filename);
        $extension = $pathInfo['extension'] ?? '';
        $outputFilename = $pathInfo['filename'] . '_' . bin2hex(random_bytes(8)) . '.' . $extension;

        return copy($sourcePath, $this->outputDirectory . $outputFilename)
            ? $outputFilename
            : null;
    }

    /** @return string[] */
    public function outputImages(): array
    {
        return $this->imagesIn($this->outputDirectory);
    }

    /** @return string[] */
    public function libraryImages(): array
    {
        return $this->imagesIn($this->libraryDirectory);
    }

    private function isValidFilename(string $filename): bool
    {
        return $filename !== ''
            && !str_contains($filename, "\0")
            && !str_contains($filename, '..')
            && !str_contains($filename, '/')
            && !str_contains($filename, '\\');
    }

    /** @return string[] */
    private function imagesIn(string $directory): array
    {
        $entries = array_diff(scandir($directory) ?: [], ['.', '..', '.gitkeep']);

        return array_values($entries);
    }
}
