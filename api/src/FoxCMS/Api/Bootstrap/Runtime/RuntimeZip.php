<?php

declare(strict_types=1);

namespace FoxCMS\Api\Bootstrap\Runtime;

use PharData;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;
use Throwable;
use ZipArchive;

/** ZIP runtime archive inspection with ZipArchive and PharData backends. */
final class RuntimeZip
{
    private const MAX_ENTRIES = 200000;
    private const MAX_EXPANDED_BYTES = 8 * 1024 * 1024 * 1024;
    private const MAX_RELEASE_BYTES = 1024 * 1024;

    /** @return array<string, mixed> */
    public static function inspectZipRuntime(string $absolutePath, string $branchPlatform): array
    {
        if (class_exists(ZipArchive::class)) {
            return self::inspectWithZipArchive($absolutePath, $branchPlatform);
        }
        if (class_exists(PharData::class)) {
            return self::inspectWithPharData($absolutePath, $branchPlatform);
        }
        throw new RuntimeException('ZIP inspection requires either ZipArchive or PharData support.');
    }

    /** @return array<string, mixed> */
    private static function inspectWithZipArchive(string $absolutePath, string $branchPlatform): array
    {
        $zip = new ZipArchive();
        $result = $zip->open($absolutePath, ZipArchive::RDONLY);
        if ($result !== true) {
            throw new RuntimeException('ZIP archive cannot be opened; ZipArchive code=' . (string)$result . '.');
        }

        try {
            if ($zip->numFiles > self::MAX_ENTRIES) {
                throw new RuntimeException('ZIP archive contains too many entries.');
            }
            $entries = [];
            $totalSize = 0;
            for ($index = 0; $index < $zip->numFiles; ++$index) {
                $stat = $zip->statIndex($index);
                if (!is_array($stat) || !isset($stat['name'])) {
                    throw new RuntimeException('ZIP entry metadata cannot be read.');
                }
                $name = RuntimeArchive::normalizeRuntimeArchiveEntry((string)$stat['name']);
                if ($name === '') {
                    continue;
                }
                if (self::runtimeZipEntryIsSymlink($zip, $index)) {
                    throw new RuntimeException('ZIP archive contains a symbolic link: ' . $name . '.');
                }
                $entrySize = isset($stat['size']) ? (int)$stat['size'] : 0;
                $totalSize = self::checkedExpandedSize($totalSize, $entrySize, $name);
                $entries[] = [
                    'name' => $name,
                    'directory' => str_ends_with($name, '/'),
                    'size' => $entrySize,
                    'locator' => $index,
                ];
            }

            return RuntimeArchive::analyzeRuntimeArchiveEntries(
                $entries,
                static function (mixed $locator) use ($zip): string {
                    $bytes = $zip->getFromIndex((int)$locator, self::MAX_RELEASE_BYTES);
                    if (!is_string($bytes)) {
                        throw new RuntimeException('ZIP release metadata cannot be read.');
                    }
                    return $bytes;
                },
                $branchPlatform,
                'zip-metadata',
            );
        } finally {
            $zip->close();
        }
    }

    /** @return array<string, mixed> */
    private static function inspectWithPharData(string $absolutePath, string $branchPlatform): array
    {
        try {
            $archive = new PharData($absolutePath);
        } catch (Throwable $error) {
            throw new RuntimeException('ZIP archive cannot be opened by PharData.', 0, $error);
        }

        $entries = [];
        $totalSize = 0;
        $entryCount = 0;
        $archiveMarker = str_replace('\\', '/', $absolutePath) . '/';
        $iterator = new RecursiveIteratorIterator($archive, RecursiveIteratorIterator::SELF_FIRST);
        foreach ($iterator as $entry) {
            if (!$entry instanceof SplFileInfo) {
                continue;
            }
            if (++$entryCount > self::MAX_ENTRIES) {
                throw new RuntimeException('ZIP archive contains too many entries.');
            }

            $entryPath = str_replace('\\', '/', $entry->getPathname());
            $position = stripos($entryPath, $archiveMarker);
            if ($position === false) {
                throw new RuntimeException('ZIP entry path cannot be resolved safely.');
            }
            $name = RuntimeArchive::normalizeRuntimeArchiveEntry(
                substr($entryPath, $position + strlen($archiveMarker)),
            );
            if ($name === '') {
                continue;
            }
            if ($entry->isLink()) {
                throw new RuntimeException('ZIP archive contains a symbolic link: ' . $name . '.');
            }

            $entrySize = $entry->isDir() ? 0 : (int)$entry->getSize();
            $totalSize = self::checkedExpandedSize($totalSize, $entrySize, $name);
            $entries[] = [
                'name' => $name,
                'directory' => $entry->isDir(),
                'size' => $entrySize,
                'locator' => $entry->getPathname(),
            ];
        }

        return RuntimeArchive::analyzeRuntimeArchiveEntries(
            $entries,
            static function (mixed $locator): string {
                $stream = fopen((string)$locator, 'rb');
                if ($stream === false) {
                    throw new RuntimeException('ZIP release metadata cannot be opened.');
                }
                try {
                    $bytes = stream_get_contents($stream, self::MAX_RELEASE_BYTES);
                } finally {
                    fclose($stream);
                }
                if (!is_string($bytes)) {
                    throw new RuntimeException('ZIP release metadata cannot be read.');
                }
                return $bytes;
            },
            $branchPlatform,
            'zip-phardata-metadata',
        );
    }

    public static function runtimeZipEntryIsSymlink(ZipArchive $zip, int $index): bool
    {
        if (!method_exists($zip, 'getExternalAttributesIndex')) {
            return false;
        }
        $operations = 0;
        $attributes = 0;
        if (!$zip->getExternalAttributesIndex($index, $operations, $attributes)) {
            return false;
        }
        $mode = ($attributes >> 16) & 0170000;
        return $mode === 0120000;
    }

    private static function checkedExpandedSize(int $totalSize, int $entrySize, string $name): int
    {
        if ($entrySize < 0) {
            throw new RuntimeException('ZIP entry has an invalid size: ' . $name . '.');
        }
        $totalSize += $entrySize;
        if ($totalSize > self::MAX_EXPANDED_BYTES) {
            throw new RuntimeException('ZIP archive expands beyond the configured safety limit.');
        }
        return $totalSize;
    }
}
