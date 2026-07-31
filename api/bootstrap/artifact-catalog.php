<?php

declare(strict_types=1);

/** Filesystem-backed artifact catalog. Published files are authoritative. */

final class BootstrapCatalogException extends RuntimeException
{
    private $statusCode;
    private $errorCode;
    private $details;

    public function __construct(int $statusCode, string $errorCode, string $message, array $details = array())
    {
        parent::__construct($message);
        $this->statusCode = $statusCode;
        $this->errorCode = $errorCode;
        $this->details = $details;
    }

    public function getStatusCode(): int { return $this->statusCode; }
    public function getErrorCode(): string { return $this->errorCode; }
    public function getDetails(): array { return $this->details; }
}

function catalogRequestPlatform(): string
{
    $platform = isset($_GET['platform']) ? trim((string) $_GET['platform']) : 'windows-x86_64';
    if (preg_match('/^(?:(?:windows|linux)-(?:x86|x86_64|aarch64)|macos-(?:x86_64|aarch64))$/D', $platform) !== 1) {
        catalogFail(422, 'unsupported_platform', 'Unsupported bootstrapper platform.', array('platform' => $platform));
    }
    return $platform;
}

function discoverBootstrapperArtifact(string $storageDirectory, string $platform): array
{
    $root = catalogDirectory($storageDirectory . DIRECTORY_SEPARATOR . 'bootstrapper' . DIRECTORY_SEPARATOR . $platform);
    foreach (catalogVersionDirectories($root) as $version => $directory) {
        $file = selectBootstrapperFile($directory, $platform);
        if ($file !== null) {
            return array(
                'version' => $version,
                'platform' => $platform,
                'artifact' => describeCatalogFile($storageDirectory, $file),
            );
        }
    }
    catalogFail(404, 'bootstrapper_platform_unavailable', 'No usable bootstrapper is published for the requested platform.', array('platform' => $platform));
}

function discoverLauncherArtifact(string $storageDirectory, string $fileName = 'launcher.jar'): array
{
    if (preg_match('/^[A-Za-z0-9._-]+$/D', $fileName) !== 1 || $fileName === '.' || $fileName === '..') {
        catalogFail(500, 'bootstrap_configuration_invalid', 'Launcher artifact filename is invalid.');
    }

    $root = catalogDirectory($storageDirectory . DIRECTORY_SEPARATOR . 'launcher');
    foreach (catalogVersionDirectories($root) as $version => $directory) {
        $candidate = $directory . DIRECTORY_SEPARATOR . $fileName;
        if (is_file($candidate) && !is_link($candidate) && is_readable($candidate)) {
            $size = filesize($candidate);
            if ($size !== false && (int) $size > 0) {
                return array(
                    'version' => $version,
                    'file_name' => $fileName,
                    'artifact' => describeCatalogFile($storageDirectory, $candidate),
                );
            }
        }
    }
    catalogFail(503, 'launcher_artifact_unavailable', 'No usable launcher artifact is published.', array('file_name' => $fileName));
}

function describeCatalogFile(string $storageDirectory, string $absolutePath): array
{
    $realStorage = realpath(catalogDirectory($storageDirectory));
    $realFile = realpath($absolutePath);
    if ($realStorage === false || $realFile === false || !is_file($realFile) || is_link($absolutePath)) {
        catalogFail(503, 'bootstrap_artifact_unavailable', 'Published artifact cannot be resolved safely.');
    }

    $prefix = rtrim(str_replace('\\', '/', $realStorage), '/') . '/';
    $normalizedFile = str_replace('\\', '/', $realFile);
    if (strpos($normalizedFile, $prefix) !== 0) {
        catalogFail(503, 'bootstrap_artifact_unsafe', 'Published artifact resolves outside bootstrap storage.');
    }

    $relativePath = substr($normalizedFile, strlen($prefix));
    if ($relativePath === '' || preg_match('#^[A-Za-z0-9._+@/-]+$#D', $relativePath) !== 1) {
        catalogFail(503, 'bootstrap_artifact_unsafe', 'Published artifact has an unsafe path.');
    }

    $stream = fopen($realFile, 'rb');
    if ($stream === false) {
        catalogFail(503, 'bootstrap_artifact_unreadable', 'Published artifact cannot be opened.');
    }

    try {
        $stat = fstat($stream);
        if (!is_array($stat) || !isset($stat['size']) || (int) $stat['size'] <= 0) {
            catalogFail(503, 'bootstrap_artifact_unreadable', 'Published artifact size cannot be read.');
        }
        $size = (int) $stat['size'];
        $hash = hash_init('sha256');
        if (hash_update_stream($hash, $stream) !== $size) {
            catalogFail(503, 'bootstrap_artifact_unreadable', 'Published artifact could not be hashed completely.');
        }
        $sha256 = hash_final($hash);
    } finally {
        fclose($stream);
    }

    if (preg_match('/^[a-f0-9]{64}$/D', $sha256) !== 1) {
        catalogFail(503, 'bootstrap_artifact_unreadable', 'Published artifact SHA-256 is invalid.');
    }

    return array(
        'path' => $relativePath,
        'url' => '/uploads/bootstrap/' . implode('/', array_map('rawurlencode', explode('/', $relativePath))),
        'sha256' => $sha256,
        'size' => (int) $size,
    );
}

function catalogVersionDirectories(string $root): array
{
    $entries = scandir($root);
    if ($entries === false) {
        catalogFail(503, 'bootstrap_catalog_unreadable', 'Published artifact catalog cannot be scanned.');
    }

    $versions = array();
    foreach ($entries as $entry) {
        if ($entry === '.' || $entry === '..' || substr($entry, 0, 1) === '.') {
            continue;
        }
        if (preg_match('/^[0-9A-Za-z][0-9A-Za-z._+-]*$/D', $entry) !== 1) {
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

function selectBootstrapperFile(string $directory, string $platform): ?string
{
    $entries = scandir($directory);
    if ($entries === false) {
        return null;
    }

    $candidates = array();
    foreach ($entries as $entry) {
        if ($entry === '.' || $entry === '..' || substr($entry, 0, 1) === '.') {
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
        if ($size === false || (int) $size <= 0) {
            continue;
        }
        if (strpos($platform, 'windows-') === 0 && preg_match('/\.exe$/iD', $entry) !== 1) {
            continue;
        }
        $candidates[$entry] = $path;
    }

    if (count($candidates) === 0) {
        return null;
    }

    $preferred = strpos($platform, 'windows-') === 0 ? 'FoxesCraft.exe' : 'FoxesCraft';
    if (isset($candidates[$preferred])) {
        return $candidates[$preferred];
    }
    if (count($candidates) === 1) {
        return reset($candidates);
    }
    ksort($candidates, SORT_NATURAL | SORT_FLAG_CASE);
    return reset($candidates);
}

function catalogDirectory(string $path): string
{
    if (!is_dir($path) || !is_readable($path)) {
        catalogFail(503, 'bootstrap_catalog_unavailable', 'Bootstrap catalog directory does not exist or is not readable.', array('directory' => basename($path)));
    }
    return rtrim($path, '/\\');
}

function catalogFail(int $statusCode, string $errorCode, string $message, array $details = array()): void
{
    throw new BootstrapCatalogException($statusCode, $errorCode, $message, $details);
}
