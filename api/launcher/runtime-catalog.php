<?php

declare(strict_types=1);

/**
 * Private server-to-server runtime catalog for KaylasLauncherBackend.
 *
 * Runtime archives remain authoritative in uploads/bootstrap/runtime. This endpoint only selects
 * one compatible archive through the existing bootstrap runtime catalog and adds the MD5 required
 * by the legacy launcher file protocol.
 */

$rootDirectory = dirname(__DIR__, 2);
require_once $rootDirectory . '/engine/data/environment.php';
foxLoadEnv($rootDirectory . '/.env');

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: private, max-age=60');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');

try {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        runtimeBridgeFail(405, 'method_not_allowed', 'POST is required.');
    }

    $configuredToken = trim((string)(foxEnv('FOXESCRAFT_LAUNCHER_BRIDGE_TOKEN', '') ?? ''));
    $providedToken = trim((string)($_SERVER['HTTP_X_KAYLAS_BRIDGE_TOKEN'] ?? ''));
    if (strlen($configuredToken) < 32 || !hash_equals($configuredToken, $providedToken)) {
        runtimeBridgeFail(403, 'bridge_forbidden', 'Launcher bridge token is invalid.');
    }

    $platform = trim((string)($_POST['platform'] ?? ''));
    $version = trim((string)($_POST['version'] ?? ''));
    if ($platform === '' || $version === '') {
        runtimeBridgeFail(422, 'runtime_request_incomplete', 'platform and version are required.');
    }

    // The canonical resolver reads its validated request from $_GET.
    $_GET = [
        'platform' => $platform,
        'version' => $version,
        'distribution' => 'any',
        'allow_prerelease' => 'false',
        'client_version' => trim((string)($_POST['client_version'] ?? 'KaylasLauncher')),
    ];

    $config = require $rootDirectory . '/api/bootstrap/config.php';
    $storageDirectory = runtimeBridgeStorageDirectory($config['storage_directory'] ?? null);

    require_once $rootDirectory . '/api/bootstrap/artifact-catalog.php';
    require_once $rootDirectory . '/api/bootstrap/runtime-catalog.php';

    $runtime = resolveRuntimeForRequest($storageDirectory);
    $artifacts = is_array($runtime['artifacts'] ?? null) ? $runtime['artifacts'] : [];
    $descriptor = $artifacts[$platform] ?? null;
    if (!is_array($descriptor)) {
        runtimeBridgeFail(500, 'runtime_descriptor_missing', 'Selected runtime descriptor is missing.');
    }

    $url = (string)($descriptor['url'] ?? '');
    $prefix = '/uploads/bootstrap/';
    if (!str_starts_with($url, $prefix)) {
        runtimeBridgeFail(500, 'runtime_descriptor_unsafe', 'Selected runtime URL is outside bootstrap storage.');
    }

    $relativePath = rawurldecode(substr($url, strlen($prefix)));
    $archive = runtimeBridgeResolveFile($storageDirectory, $relativePath);
    $md5 = md5_file($archive);
    if (!is_string($md5) || preg_match('/^[a-f0-9]{32}$/D', $md5) !== 1) {
        runtimeBridgeFail(500, 'runtime_hash_failed', 'Selected runtime MD5 cannot be calculated.');
    }

    $response = [
        'filename' => $url,
        'hash' => $md5,
        'sha256' => (string)($descriptor['sha256'] ?? ''),
        'size' => (int)($descriptor['size'] ?? 0),
        'fileName' => (string)($descriptor['file_name'] ?? basename($archive)),
        'installPath' => (string)($descriptor['install_path'] ?? ''),
        'javaPath' => (string)($descriptor['java_path'] ?? ''),
        'archive' => (string)($descriptor['archive'] ?? ''),
        'stripComponents' => (int)($descriptor['strip_components'] ?? 0),
        'vendor' => (string)($descriptor['vendor'] ?? ''),
        'distribution' => (string)($descriptor['distribution'] ?? ''),
        'version' => (string)($descriptor['version'] ?? ($runtime['version'] ?? '')),
        'javaMajor' => (int)($descriptor['java_major'] ?? 0),
        'platform' => (string)($descriptor['platform'] ?? $platform),
    ];

    if ($response['size'] <= 0 || preg_match('/^[a-f0-9]{64}$/D', $response['sha256']) !== 1) {
        runtimeBridgeFail(500, 'runtime_descriptor_invalid', 'Selected runtime descriptor is incomplete.');
    }

    $encoded = json_encode($response, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if (!is_string($encoded)) {
        runtimeBridgeFail(500, 'runtime_encoding_failed', 'Runtime descriptor cannot be encoded.');
    }
    header('Content-Length: ' . strlen($encoded));
    echo $encoded;
} catch (BootstrapCatalogException $exception) {
    runtimeBridgeRespond(
        $exception->getStatusCode(),
        $exception->getErrorCode(),
        $exception->getMessage(),
        $exception->getDetails()
    );
} catch (RuntimeBridgeHttpException $exception) {
    runtimeBridgeRespond(
        $exception->getStatusCode(),
        $exception->getErrorCode(),
        $exception->getMessage(),
        $exception->getDetails()
    );
} catch (Throwable $exception) {
    error_log(sprintf(
        '[FoxesCraft launcher runtime catalog] exception=%s message=%s source=%s:%d',
        get_class($exception),
        $exception->getMessage(),
        $exception->getFile(),
        $exception->getLine()
    ));
    runtimeBridgeRespond(500, 'runtime_catalog_internal_error', 'Runtime catalog request failed.');
}

function runtimeBridgeStorageDirectory($value): string
{
    if (!is_string($value) || $value === '') {
        runtimeBridgeFail(500, 'runtime_storage_invalid', 'Bootstrap storage directory is not configured.');
    }
    $real = realpath($value);
    if ($real === false || !is_dir($real) || !is_readable($real)) {
        runtimeBridgeFail(503, 'runtime_storage_unavailable', 'Bootstrap storage directory is unavailable.');
    }
    return rtrim($real, '/\\');
}

function runtimeBridgeResolveFile(string $storageDirectory, string $relativePath): string
{
    $normalized = str_replace('\\', '/', $relativePath);
    if ($normalized === '' || str_contains($normalized, "\0") || str_starts_with($normalized, '/')) {
        runtimeBridgeFail(500, 'runtime_path_unsafe', 'Runtime archive path is unsafe.');
    }
    foreach (explode('/', $normalized) as $segment) {
        if ($segment === '' || $segment === '.' || $segment === '..') {
            runtimeBridgeFail(500, 'runtime_path_unsafe', 'Runtime archive path is unsafe.');
        }
    }
    if (!str_starts_with($normalized, 'runtime/')) {
        runtimeBridgeFail(500, 'runtime_path_unsafe', 'Runtime archive is outside runtime storage.');
    }

    $candidate = $storageDirectory . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $normalized);
    $real = realpath($candidate);
    $storagePrefix = rtrim(str_replace('\\', '/', $storageDirectory), '/') . '/';
    $normalizedReal = $real === false ? '' : str_replace('\\', '/', $real);
    if ($real === false || !is_file($real) || is_link($candidate) || !str_starts_with($normalizedReal, $storagePrefix)) {
        runtimeBridgeFail(404, 'runtime_archive_missing', 'Selected runtime archive is unavailable.');
    }
    return $real;
}

function runtimeBridgeFail(int $statusCode, string $errorCode, string $message, array $details = []): never
{
    throw new RuntimeBridgeHttpException($statusCode, $errorCode, $message, $details);
}

/** Required by api/bootstrap/runtime-catalog/*.php. */
function fail(int $statusCode, string $errorCode, string $message, array $details = []): never
{
    runtimeBridgeFail($statusCode, $errorCode, $message, $details);
}

function runtimeBridgeRespond(int $statusCode, string $errorCode, string $message, array $details = []): never
{
    http_response_code($statusCode);
    header('Cache-Control: no-store');
    header('X-FoxesCraft-Error-Code: ' . $errorCode);
    $payload = ['error' => $errorCode, 'message' => $message];
    if ($details !== []) {
        $payload['details'] = $details;
    }
    $body = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    echo is_string($body) ? $body : '{"error":"runtime_catalog_error"}';
    exit;
}

final class RuntimeBridgeHttpException extends RuntimeException
{
    public function __construct(
        private readonly int $statusCode,
        private readonly string $errorCode,
        string $message,
        private readonly array $details = [],
    ) {
        parent::__construct($message);
    }

    public function getStatusCode(): int { return $this->statusCode; }
    public function getErrorCode(): string { return $this->errorCode; }
    public function getDetails(): array { return $this->details; }
}
