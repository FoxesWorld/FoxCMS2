<?php

declare(strict_types=1);

namespace FoxCMS\Api\Bootstrap;

use FoxCMS\Api\Core\HttpException;

final class PublishedArtifactInspector
{
    private const SAFE_RELATIVE_PATH_PATTERN = '#^[A-Za-z0-9._+@/-]+$#D';

    private string $realStorageDirectory;

    public function __construct(private readonly string $storageDirectory)
    {
        $realStorage = realpath($this->requireDirectory($storageDirectory));
        if ($realStorage === false) {
            throw new HttpException(503, 'bootstrap_catalog_unavailable', 'Bootstrap storage cannot be resolved.');
        }
        $this->realStorageDirectory = $realStorage;
    }

    /** @return array{path: string, url: string, sha256: string, size: int} */
    public function describe(string $absolutePath): array
    {
        $realFile = realpath($absolutePath);
        if ($realFile === false || !is_file($realFile) || is_link($absolutePath)) {
            throw new HttpException(503, 'bootstrap_artifact_unavailable', 'Published artifact cannot be resolved safely.');
        }

        $prefix = rtrim(str_replace('\\', '/', $this->realStorageDirectory), '/') . '/';
        $normalizedFile = str_replace('\\', '/', $realFile);
        if (!str_starts_with($normalizedFile, $prefix)) {
            throw new HttpException(503, 'bootstrap_artifact_unsafe', 'Published artifact resolves outside bootstrap storage.');
        }

        $relativePath = substr($normalizedFile, strlen($prefix));
        if ($relativePath === '' || preg_match(self::SAFE_RELATIVE_PATH_PATTERN, $relativePath) !== 1) {
            throw new HttpException(503, 'bootstrap_artifact_unsafe', 'Published artifact has an unsafe path.');
        }

        [$size, $sha256] = $this->hashFile($realFile);
        return [
            'path' => $relativePath,
            'url' => '/uploads/bootstrap/' . implode('/', array_map('rawurlencode', explode('/', $relativePath))),
            'sha256' => $sha256,
            'size' => $size,
        ];
    }

    /** @return array{0: int, 1: string} */
    private function hashFile(string $realFile): array
    {
        $stream = fopen($realFile, 'rb');
        if ($stream === false) {
            throw new HttpException(503, 'bootstrap_artifact_unreadable', 'Published artifact cannot be opened.');
        }
        try {
            $stat = fstat($stream);
            if (!is_array($stat) || !isset($stat['size']) || (int)$stat['size'] <= 0) {
                throw new HttpException(503, 'bootstrap_artifact_unreadable', 'Published artifact size cannot be read.');
            }
            $size = (int)$stat['size'];
            $hash = hash_init('sha256');
            if (hash_update_stream($hash, $stream) !== $size) {
                throw new HttpException(503, 'bootstrap_artifact_unreadable', 'Published artifact could not be hashed completely.');
            }
            $sha256 = hash_final($hash);
        } finally {
            fclose($stream);
        }
        if (preg_match('/^[a-f0-9]{64}$/D', $sha256) !== 1) {
            throw new HttpException(503, 'bootstrap_artifact_unreadable', 'Published artifact SHA-256 is invalid.');
        }
        return [$size, $sha256];
    }

    private function requireDirectory(string $path): string
    {
        if (!is_dir($path) || !is_readable($path)) {
            throw new HttpException(
                503,
                'bootstrap_catalog_unavailable',
                'Bootstrap catalog directory does not exist or is not readable.',
                ['directory' => basename($path)],
            );
        }
        return rtrim($path, '/\\');
    }
}
