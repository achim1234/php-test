<?php

declare(strict_types=1);

namespace App\Storage;

use RuntimeException;

final readonly class PostStorage
{
    public function __construct(private string $directory) {}

    /** @return list<array{id: string, title: string, images: list<string>, caption: ?string}> */
    public function posts(): array
    {
        $posts = [];
        foreach (is_dir($this->directory) ? scandir($this->directory, SCANDIR_SORT_DESCENDING) : [] as $id) {
            $post = $this->find($id);
            if ($post !== null) {
                $posts[] = $post;
            }
        }

        usort($posts, static function (array $first, array $second): int {
            $firstIsGlitched = str_contains($first['id'], '_glitched_');
            $secondIsGlitched = str_contains($second['id'], '_glitched_');
            if ($firstIsGlitched !== $secondIsGlitched) {
                return $firstIsGlitched <=> $secondIsGlitched;
            }

            return $second['id'] <=> $first['id'];
        });

        return $posts;
    }

    /** @return array{id: string, title: string, images: list<string>, caption: ?string}|null */
    public function find(string $id): ?array
    {
        if (preg_match('/\Apost_[a-zA-Z0-9_-]+\z/', $id) !== 1) {
            return null;
        }
        $path = $this->directory . '/' . $id;
        if (!is_dir($path) || is_link($path)) {
            return null;
        }
        $images = [];
        foreach (scandir($path) as $filename) {
            if (preg_match('/\Apost_\d+\.(?:png|jpe?g)\z/i', $filename) === 1
                && is_file($path . '/' . $filename) && !is_link($path . '/' . $filename)) {
                $images[] = 'posts/' . $id . '/' . $filename;
            }
        }
        if ($images === []) {
            return null;
        }
        natsort($images);
        $captionPath = $path . '/post_text.txt';
        $caption = is_file($captionPath) && !is_link($captionPath) ? 'posts/' . $id . '/post_text.txt' : null;
        $title = $id;
        if ($caption !== null) {
            $text = file_get_contents($captionPath, false, null, 0, 4096);
            if ($text !== false && preg_match('/^Title:\s*(.+)$/m', $text, $match) === 1) {
                $title = trim($match[1]);
            }
        }
        if (str_contains($id, '_glitched_')) {
            $title .= ' (glitched)';
        }

        return ['id' => $id, 'title' => $title, 'images' => array_values($images), 'caption' => $caption];
    }

    public function imagePath(string $postId, string $filename): ?string
    {
        $post = $this->find($postId);
        if ($post === null || !in_array('posts/' . $postId . '/' . $filename, $post['images'], true)) {
            return null;
        }

        return $this->directory . '/' . $postId . '/' . $filename;
    }

    /** @return array{id: string, path: string} */
    public function createDraft(): array
    {
        $id = 'post_' . date('Ymd_His') . '_glitched_' . bin2hex(random_bytes(6));
        $path = $this->directory . '/.' . $id;
        if (!mkdir($path, 0775)) {
            throw new RuntimeException('Unable to create the glitched post.');
        }

        return ['id' => $id, 'path' => $path];
    }

    public function copyCaption(string $sourceId, string $draftPath): void
    {
        $post = $this->find($sourceId);
        if ($post === null) {
            throw new RuntimeException('The source post is no longer available.');
        }
        if ($post['caption'] !== null && !copy($this->directory . '/' . $sourceId . '/post_text.txt', $draftPath . '/post_text.txt')) {
            throw new RuntimeException('Unable to copy the post text.');
        }
    }

    /** @param array{id: string, path: string} $draft */
    public function publish(array $draft): void
    {
        if (!rename($draft['path'], $this->directory . '/' . $draft['id'])) {
            throw new RuntimeException('Unable to save the glitched post.');
        }
    }

    public function discardDraft(string $path): void
    {
        foreach (array_diff(scandir($path), ['.', '..']) as $filename) {
            unlink($path . '/' . $filename);
        }
        rmdir($path);
    }
}
