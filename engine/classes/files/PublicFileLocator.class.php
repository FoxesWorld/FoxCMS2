<?php

declare(strict_types=1);

final class PublicFileLocator
{
    public function __construct(private string $publicRoot)
    {
    }

    public function resolve(string $relativePath, string $allowedRoot, int $maximumBytes): string
    {
        $relativePath = str_replace('\\', '/', trim($relativePath));
        if ($relativePath === '' || str_contains($relativePath, "\0")) {
            throw new HttpException('File not found.', 404);
        }
        if ($maximumBytes < 1) {
            throw new InvalidArgumentException('Maximum file size must be positive.');
        }

        $publicRoot = realpath($this->publicRoot);
        if (!is_string($publicRoot) || !is_dir($publicRoot)) {
            throw new RuntimeException('Public root is unavailable.');
        }
        $root = realpath($allowedRoot);
        if (!is_string($root) || !is_dir($root)) {
            throw new HttpException('File not found.', 404);
        }
        if (!$this->inside($root, $publicRoot)) {
            throw new RuntimeException('Allowed file root is outside the public root.');
        }

        $normalizedRelative = ltrim($relativePath, '/');
        $this->rejectSymlinkPath($normalizedRelative, $publicRoot);
        $candidate = $publicRoot . DIRECTORY_SEPARATOR
            . str_replace('/', DIRECTORY_SEPARATOR, $normalizedRelative);
        $file = realpath($candidate);
        if (!is_string($file) || !is_file($file) || is_link($candidate) || !$this->inside($file, $root)) {
            throw new HttpException('File not found.', 404);
        }

        $size = filesize($file);
        if (!is_int($size) || $size < 0) {
            throw new RuntimeException('Unable to determine file size.');
        }
        if ($size > $maximumBytes) {
            throw new HttpException('File exceeds the allowed size.', 413);
        }
        return $file;
    }

    private function rejectSymlinkPath(string $relative, string $root): void
    {
        $cursor = rtrim($root, '/\\');
        foreach (explode('/', $relative) as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                throw new HttpException('File not found.', 404);
            }
            $cursor .= DIRECTORY_SEPARATOR . $segment;
            if (is_link($cursor)) {
                throw new HttpException('File not found.', 404);
            }
        }
    }

    private function inside(string $path, string $root): bool
    {
        $root = rtrim($root, '/\\');
        return $path === $root || str_starts_with($path, $root . DIRECTORY_SEPARATOR);
    }
}
