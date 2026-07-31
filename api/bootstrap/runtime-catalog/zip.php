<?php

declare(strict_types=1);

/** ZIP runtime archive inspection. */

function inspectZipRuntime(string $absolutePath, string $branchPlatform): array
{
    if (!class_exists('ZipArchive')) {
        throw new RuntimeException('ZIP inspection requires the PHP ZipArchive extension.');
    }
    $zip = new ZipArchive();
    $result = $zip->open($absolutePath, ZipArchive::RDONLY);
    if ($result !== true) {
        throw new RuntimeException('ZIP archive cannot be opened; ZipArchive code=' . (string) $result . '.');
    }

    try {
        $entries = array();
        $totalSize = 0;
        if ($zip->numFiles > 200000) {
            throw new RuntimeException('ZIP archive contains too many entries.');
        }
        for ($index = 0; $index < $zip->numFiles; ++$index) {
            $stat = $zip->statIndex($index);
            if (!is_array($stat) || !isset($stat['name'])) {
                throw new RuntimeException('ZIP entry metadata cannot be read.');
            }
            $name = normalizeRuntimeArchiveEntry((string) $stat['name']);
            if ($name === '') {
                continue;
            }
            if (runtimeZipEntryIsSymlink($zip, $index)) {
                throw new RuntimeException('ZIP archive contains a symbolic link: ' . $name . '.');
            }
            $entrySize = isset($stat['size']) ? (int) $stat['size'] : 0;
            if ($entrySize < 0) {
                throw new RuntimeException('ZIP entry has an invalid size: ' . $name . '.');
            }
            $totalSize += $entrySize;
            if ($totalSize > 8 * 1024 * 1024 * 1024) {
                throw new RuntimeException('ZIP archive expands beyond the configured safety limit.');
            }
            $entries[] = array(
                'name' => $name,
                'directory' => substr($name, -1) === '/',
                'size' => $entrySize,
                'locator' => $index,
            );
        }

        return analyzeRuntimeArchiveEntries(
            $entries,
            static function ($locator) use ($zip): string {
                $bytes = $zip->getFromIndex((int) $locator, 1024 * 1024);
                if (!is_string($bytes)) {
                    throw new RuntimeException('ZIP release metadata cannot be read.');
                }
                return $bytes;
            },
            $branchPlatform,
            'zip-metadata'
        );
    } finally {
        $zip->close();
    }
}
function runtimeZipEntryIsSymlink(ZipArchive $zip, int $index): bool
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
