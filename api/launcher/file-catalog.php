<?php

declare(strict_types=1);

/**
 * Private server-to-server game file catalog for KaylasLauncherBackend.
 *
 * This endpoint intentionally bypasses the normal Application/maintenance pipeline. Access is
 * restricted by a shared secret and the scanner itself confines all paths to uploads/files/clients.
 */

$rootDirectory = dirname(__DIR__, 2);
require_once $rootDirectory . '/engine/data/environment.php';
foxLoadEnv($rootDirectory . '/.env');

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: private, no-store');
header('X-Content-Type-Options: nosniff');

function launcherCatalogRespond(array $payload, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    exit;
}

function launcherCatalogError(string $code, string $message, int $status): never
{
    launcherCatalogRespond([
        'type' => 'error',
        'code' => $code,
        'message' => $message,
    ], $status);
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    launcherCatalogError('method_not_allowed', 'POST is required.', 405);
}

$expectedToken = trim((string)(foxEnv('FOXESCRAFT_LAUNCHER_BRIDGE_TOKEN', '') ?? ''));
if (strlen($expectedToken) < 32) {
    launcherCatalogError('bridge_not_configured', 'Launcher bridge token is not configured.', 503);
}

$providedToken = trim((string)($_SERVER['HTTP_X_KAYLAS_BRIDGE_TOKEN'] ?? ''));
if ($providedToken === '' || !hash_equals($expectedToken, $providedToken)) {
    launcherCatalogError('bridge_forbidden', 'Invalid launcher bridge credentials.', 403);
}

$client = trim((string)($_POST['client'] ?? ''));
$version = trim((string)($_POST['version'] ?? ''));
$platformRaw = $_POST['platform'] ?? null;
if (preg_match('/^[A-Za-z0-9._-]{1,64}$/D', $client) !== 1) {
    launcherCatalogError('invalid_client', 'Invalid client identifier.', 422);
}
if (preg_match('/^[A-Za-z0-9._-]{1,64}$/D', $version) !== 1) {
    launcherCatalogError('invalid_version', 'Invalid version identifier.', 422);
}
if (filter_var($platformRaw, FILTER_VALIDATE_INT) === false) {
    launcherCatalogError('invalid_platform', 'Invalid launcher platform.', 422);
}
$platform = (int)$platformRaw;
if ($platform < 0 || $platform > 4) {
    launcherCatalogError('invalid_platform', 'Unsupported launcher platform.', 422);
}

if (!defined('FOXXEY')) {
    define('FOXXEY', true);
}
if (!defined('ROOT_DIR')) {
    define('ROOT_DIR', $rootDirectory);
}
if (!defined('UPLOADS_DIR')) {
    define('UPLOADS_DIR', '/uploads/');
}

require_once ROOT_DIR . '/engine/classes/modules/GameScanner/GameScanner.class.php';

$cacheTtl = max(1, min(3600, foxEnvInt('FOXESCRAFT_LAUNCHER_CATALOG_TTL', 60)));
$cacheDirectory = ROOT_DIR . '/engine/cache/launcher-file-catalog';
$cacheKey = hash('sha256', $client . "\0" . $version . "\0" . (string)$platform);
$cacheFile = $cacheDirectory . DIRECTORY_SEPARATOR . $cacheKey . '.json';
$lockFile = $cacheDirectory . DIRECTORY_SEPARATOR . $cacheKey . '.lock';

try {
    if (!is_dir($cacheDirectory) && !mkdir($cacheDirectory, 0750, true) && !is_dir($cacheDirectory)) {
        throw new RuntimeException('Unable to create launcher catalog cache directory.');
    }

    $cachedMtime = is_file($cacheFile) ? filemtime($cacheFile) : false;
    if ($cachedMtime !== false && $cachedMtime + $cacheTtl >= time()) {
        $cached = file_get_contents($cacheFile);
        if (is_string($cached) && $cached !== '') {
            header('X-Launcher-Catalog-Cache: HIT');
            http_response_code(200);
            echo $cached;
            exit;
        }
    }

    $lock = fopen($lockFile, 'c+b');
    if ($lock === false || !flock($lock, LOCK_EX)) {
        throw new RuntimeException('Unable to lock launcher catalog cache.');
    }

    try {
        clearstatcache(true, $cacheFile);
        $cachedMtime = is_file($cacheFile) ? filemtime($cacheFile) : false;
        if ($cachedMtime !== false && $cachedMtime + $cacheTtl >= time()) {
            $cached = file_get_contents($cacheFile);
            if (is_string($cached) && $cached !== '') {
                header('X-Launcher-Catalog-Cache: HIT-AFTER-LOCK');
                http_response_code(200);
                echo $cached;
                exit;
            }
        }

        $config = [
            'launcherSettings' => [
                'gameFiles' => trim((string)(foxEnv('FOXESCRAFT_GAME_FILES_DIR', 'files/clients/') ?? 'files/clients/')),
            ],
        ];
        $scanner = new GameScanner($client, $version, $platform, $config);
        $scanner->scan();
        $json = $scanner->toJson();

        $temporary = $cacheFile . '.tmp.' . bin2hex(random_bytes(6));
        if (file_put_contents($temporary, $json, LOCK_EX) === false) {
            throw new RuntimeException('Unable to write launcher catalog cache.');
        }
        @chmod($temporary, 0640);
        if (!rename($temporary, $cacheFile)) {
            @unlink($temporary);
            throw new RuntimeException('Unable to publish launcher catalog cache atomically.');
        }
        header('X-Launcher-Catalog-Cache: MISS');
        http_response_code(200);
        echo $json;
        exit;
    } finally {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
} catch (InvalidArgumentException $error) {
    launcherCatalogError('invalid_catalog_request', $error->getMessage(), 422);
} catch (Throwable $error) {
    error_log('Launcher file catalog failed: ' . $error->getMessage());
    launcherCatalogError('catalog_unavailable', 'Launcher file catalog is unavailable.', 503);
}
