<?php

declare(strict_types=1);

namespace FoxCMS\Api\Bootstrap\Runtime;

use PharData;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;
use UnexpectedValueException;

/** TAR.GZ and TGZ runtime archive inspection. */

final class RuntimeTar
{
    public static function inspectTarRuntime(string $absolutePath, string $branchPlatform): array
    {
        if (!class_exists('PharData')) {
            throw new RuntimeException('TAR inspection requires the PHP Phar extension.');
        }
        try {
            $phar = new PharData($absolutePath);
            $entries = array();
            $count = 0;
            $totalSize = 0;
            $prefix = 'phar://' . str_replace('\\', '/', $absolutePath) . '/';
            $iterator = new RecursiveIteratorIterator($phar, RecursiveIteratorIterator::SELF_FIRST);
            foreach ($iterator as $entry) {
                if (!$entry instanceof SplFileInfo) {
                    continue;
                }
                if (++$count > 200000) {
                    throw new RuntimeException('TAR archive contains too many entries.');
                }
                $pathName = str_replace('\\', '/', $entry->getPathname());
                $name = strpos($pathName, $prefix) === 0
                    ? substr($pathName, strlen($prefix))
                    : basename($pathName);
                $name = RuntimeArchive::normalizeRuntimeArchiveEntry($name);
                if ($name === '') {
                    continue;
                }
                if ($entry->isLink()) {
                    throw new RuntimeException('TAR archive contains a symbolic link: ' . $name . '.');
                }
                $entrySize = $entry->isFile() ? (int) $entry->getSize() : 0;
                $totalSize += $entrySize;
                if ($totalSize > 8 * 1024 * 1024 * 1024) {
                    throw new RuntimeException('TAR archive expands beyond the configured safety limit.');
                }
                $entries[] = array(
                    'name' => $name,
                    'directory' => $entry->isDir(),
                    'size' => $entrySize,
                    'locator' => $entry->getPathname(),
                );
            }

            return RuntimeArchive::analyzeRuntimeArchiveEntries(
                $entries,
                static function ($locator): string {
                    $bytes = file_get_contents((string) $locator, false, null, 0, 1024 * 1024);
                    if (!is_string($bytes)) {
                        throw new RuntimeException('TAR release metadata cannot be read.');
                    }
                    return $bytes;
                },
                $branchPlatform,
                'tar-metadata'
            );
        } catch (UnexpectedValueException $exception) {
            throw new RuntimeException('TAR archive cannot be opened: ' . $exception->getMessage(), 0, $exception);
        }
    }
}
