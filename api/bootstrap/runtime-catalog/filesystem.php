<?php

declare(strict_types=1);

/** Safe runtime catalog traversal and filesystem path handling. */

function runtimeArchiveFiles(string $scanRoot): array
{
    $files = array();
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($scanRoot, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($iterator as $fileInfo) {
        if (!$fileInfo instanceof SplFileInfo || !$fileInfo->isFile() || $fileInfo->isLink()) {
            continue;
        }
        if (isRuntimeArchiveName($fileInfo->getFilename())) {
            $files[] = $fileInfo->getPathname();
        }
    }
    natcasesort($files);
    return array_values($files);
}
function isRuntimeArchiveName(string $fileName): bool
{
    $lower = strtolower($fileName);
    if ($fileName === '' || substr($lower, 0, 1) === '.') {
        return false;
    }
    if (preg_match('/\.(?:part|tmp|wrong|bak|sha256|sig)$/D', $lower) === 1) {
        return false;
    }
    return substr($lower, -4) === '.zip'
        || substr($lower, -7) === '.tar.gz'
        || substr($lower, -4) === '.tgz';
}
function runtimeCatalogRelativePath(string $storageDirectory, string $absolutePath): string
{
    $storage = realpath($storageDirectory);
    $file = realpath($absolutePath);
    if ($storage === false || $file === false) {
        throw new RuntimeException('Runtime catalog path cannot be resolved.');
    }
    $prefix = rtrim(str_replace('\\', '/', $storage), '/') . '/';
    $normalized = str_replace('\\', '/', $file);
    if (strpos($normalized, $prefix) !== 0) {
        throw new RuntimeException('Runtime archive resolves outside bootstrap storage.');
    }
    $relative = substr($normalized, strlen($prefix));
    if ($relative === '' || preg_match('#^[A-Za-z0-9._+@/-]+$#D', $relative) !== 1) {
        throw new RuntimeException('Runtime archive has an unsafe catalog path.');
    }
    return $relative;
}
