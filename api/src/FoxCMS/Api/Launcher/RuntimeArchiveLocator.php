<?php

declare(strict_types=1);

namespace FoxCMS\Api\Launcher;

use FoxCMS\Api\Core\HttpException;

final class RuntimeArchiveLocator
{
    public function storageDirectory(mixed $value): string
    {
        if (!is_string($value) || $value === '') {
            throw new HttpException(500, 'runtime_storage_invalid', 'Bootstrap storage directory is not configured.');
        }
        $real = realpath($value);
        if ($real === false || !is_dir($real) || !is_readable($real)) {
            throw new HttpException(503, 'runtime_storage_unavailable', 'Bootstrap storage directory is unavailable.');
        }
        return rtrim($real, '/\\');
    }

    public function resolve(string $storageDirectory, string $relativePath): string
    {
        $normalized = str_replace('\\', '/', $relativePath);
        if ($normalized === '' || str_contains($normalized, "\0") || str_starts_with($normalized, '/')) {
            throw new HttpException(500, 'runtime_path_unsafe', 'Runtime archive path is unsafe.');
        }
        foreach (explode('/', $normalized) as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                throw new HttpException(500, 'runtime_path_unsafe', 'Runtime archive path is unsafe.');
            }
        }
        if (!str_starts_with($normalized, 'runtime/')) {
            throw new HttpException(500, 'runtime_path_unsafe', 'Runtime archive is outside runtime storage.');
        }

        $candidate = $storageDirectory . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $normalized);
        $real = realpath($candidate);
        $storagePrefix = rtrim(str_replace('\\', '/', $storageDirectory), '/') . '/';
        $normalizedReal = $real === false ? '' : str_replace('\\', '/', $real);
        if ($real === false || !is_file($real) || is_link($candidate) || !str_starts_with($normalizedReal, $storagePrefix)) {
            throw new HttpException(404, 'runtime_archive_missing', 'Selected runtime archive is unavailable.');
        }
        return $real;
    }
}
