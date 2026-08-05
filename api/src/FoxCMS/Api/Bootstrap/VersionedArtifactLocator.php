<?php

declare(strict_types=1);

namespace FoxCMS\Api\Bootstrap;

use FoxCMS\Api\Core\HttpException;

final class VersionedArtifactLocator
{
    private const VERSION_PATTERN = '/^[0-9A-Za-z][0-9A-Za-z._+-]*$/D';

    /** @return array<string, string> */
    public function versionDirectories(string $root): array
    {
        if (!is_dir($root) || !is_readable($root)) {
            throw new HttpException(
                503,
                'bootstrap_catalog_unavailable',
                'Bootstrap catalog directory does not exist or is not readable.',
                ['directory' => basename($root)],
            );
        }

        $entries = scandir($root);
        if ($entries === false) {
            throw new HttpException(503, 'bootstrap_catalog_unreadable', 'Published artifact catalog cannot be scanned.');
        }

        $versions = [];
        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..' || str_starts_with($entry, '.')) {
                continue;
            }
            if (preg_match(self::VERSION_PATTERN, $entry) !== 1) {
                continue;
            }
            $candidate = $root . DIRECTORY_SEPARATOR . $entry;
            if (is_dir($candidate) && !is_link($candidate) && is_readable($candidate)) {
                $versions[$entry] = $candidate;
            }
        }
        uksort($versions, static function (string $left, string $right): int {
            $result = version_compare($right, $left);
            return $result !== 0 ? $result : strnatcasecmp($right, $left);
        });
        return $versions;
    }

    public function bootstrapperFile(string $directory, string $platform): ?string
    {
        $entries = scandir($directory);
        if ($entries === false) {
            return null;
        }

        $candidates = [];
        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..' || str_starts_with($entry, '.')) {
                continue;
            }
            if (preg_match('/(?:\.sha256|\.sig|\.part|\.tmp|\.bak)$/iD', $entry) === 1) {
                continue;
            }
            $path = $directory . DIRECTORY_SEPARATOR . $entry;
            if (!is_file($path) || is_link($path) || !is_readable($path)) {
                continue;
            }
            $size = filesize($path);
            if ($size === false || (int)$size <= 0) {
                continue;
            }
            if (str_starts_with($platform, 'windows-') && preg_match('/\.exe$/iD', $entry) !== 1) {
                continue;
            }
            $candidates[$entry] = $path;
        }
        if ($candidates === []) {
            return null;
        }

        $preferred = str_starts_with($platform, 'windows-') ? 'FoxesCraft.exe' : 'FoxesCraft';
        if (isset($candidates[$preferred])) {
            return $candidates[$preferred];
        }
        if (count($candidates) === 1) {
            return reset($candidates) ?: null;
        }
        ksort($candidates, SORT_NATURAL | SORT_FLAG_CASE);
        return reset($candidates) ?: null;
    }
}
