<?php

declare(strict_types=1);

/**
 * Dynamic FoxesCraft bootstrap manifest.
 *
 * Published files are authoritative: versions come from version directories;
 * size and SHA-256 are calculated from the selected files.
 */

$config = require __DIR__ . '/config.php';
require_once __DIR__ . '/artifact-catalog.php';
require_once __DIR__ . '/runtime-catalog.php';
require_once __DIR__ . '/hardware-report.php';
require_once __DIR__ . '/hardware-inventory.php';
$requestId = createRequestId();
$requestMethod = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, If-None-Match');
header('X-FoxesCraft-Request-Id: ' . $requestId);
header('X-FoxesCraft-Manifest-Schema: 1');

if ($requestMethod === 'OPTIONS') {
    header('Cache-Control: no-store');
    http_response_code(204);
    exit;
}
if (!in_array($requestMethod, ['GET', 'POST'], true)) {
    header('Allow: GET, POST, OPTIONS');
    respondWithError(405, 'bootstrap_manifest_method_not_allowed', 'Bootstrap manifest accepts GET or POST requests.');
}

try {
    if ($requestMethod === 'POST') {
        registerHardwareInventory($config, $requestId);
    } else {
        header('X-FoxesCraft-Hardware-Inventory: not-provided');
    }

    $storageDirectory = requireAbsoluteDirectory(isset($config['storage_directory']) ? $config['storage_directory'] : null);
    $cacheMaxAge = requireNonNegativeInteger(isset($config['cache_max_age']) ? $config['cache_max_age'] : 60, 'cache_max_age');
    $platform = catalogRequestPlatform();

    $bootstrapper = discoverBootstrapperArtifact($storageDirectory, $platform);
    $launcher = discoverLauncherArtifact(
        $storageDirectory,
        isset($config['launcher_file_name']) ? (string) $config['launcher_file_name'] : 'launcher.jar'
    );

    $manifest = array(
        'schema_version' => 1,
        'bootstrapper' => array(
            'version' => $bootstrapper['version'],
            'artifacts' => array(
                $platform => publicArtifact($bootstrapper['artifact']),
            ),
        ),
        'jvm' => resolveRuntimeForRequest($storageDirectory),
        'launcher' => array(
            'version' => $launcher['version'],
            'file_name' => $launcher['file_name'],
            'artifact' => publicArtifact($launcher['artifact']),
            'jvm_args' => requireStringList(
                isset($config['launcher_jvm_args']) ? $config['launcher_jvm_args'] : array(),
                'launcher_jvm_args'
            ),
            'launcher_args' => requireStringList(
                isset($config['launcher_args']) ? $config['launcher_args'] : array(),
                'launcher_args'
            ),
        ),
    );

    $encoded = json_encode($manifest, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if ($encoded === false) {
        fail(500, 'bootstrap_manifest_encoding_failed', 'Bootstrap manifest cannot be encoded.');
    }

    $etag = '"' . hash('sha256', $encoded) . '"';
    header('ETag: ' . $etag);
    if ($requestMethod === 'GET') {
        header(sprintf('Cache-Control: public, max-age=%d, stale-while-revalidate=300', $cacheMaxAge));
        $requestEtag = trim(isset($_SERVER['HTTP_IF_NONE_MATCH']) ? (string) $_SERVER['HTTP_IF_NONE_MATCH'] : '');
        if ($requestEtag !== '' && hash_equals($etag, $requestEtag)) {
            http_response_code(304);
            exit;
        }
    } else {
        header('Cache-Control: no-store');
    }

    header('Content-Length: ' . strlen($encoded));
    echo $encoded;
} catch (BootstrapCatalogException $exception) {
    respondWithError(
        $exception->getStatusCode(),
        $exception->getErrorCode(),
        $exception->getMessage(),
        $exception->getDetails()
    );
} catch (BootstrapHttpException $exception) {
    respondWithError(
        $exception->getStatusCode(),
        $exception->getErrorCode(),
        $exception->getMessage(),
        $exception->getDetails()
    );
} catch (Throwable $exception) {
    error_log(sprintf(
        '[FoxesCraft bootstrap manifest] request=%s exception=%s message=%s source=%s:%d',
        $requestId,
        get_class($exception),
        $exception->getMessage(),
        $exception->getFile(),
        $exception->getLine()
    ));
    respondWithError(
        500,
        'bootstrap_manifest_internal_error',
        'Bootstrap manifest cannot be generated because an unexpected server error occurred.'
    );
}

/** @param array<string, mixed> $bootstrapConfig */
function registerHardwareInventory(array $bootstrapConfig, string $requestId): void
{
    $inventoryConfig = is_array($bootstrapConfig['hardware_inventory'] ?? null)
        ? $bootstrapConfig['hardware_inventory']
        : [];
    if (($inventoryConfig['enabled'] ?? true) !== true) {
        header('X-FoxesCraft-Hardware-Inventory: disabled');
        return;
    }

    $maxPayloadBytes = (int)($inventoryConfig['max_payload_bytes'] ?? 32768);
    if ($maxPayloadBytes < 4096 || $maxPayloadBytes > 131072) {
        fail(500, 'bootstrap_configuration_invalid', 'Hardware inventory payload limit is invalid.');
    }

    $report = BootstrapHardwareReport::fromHttpBody($maxPayloadBytes);
    $databaseConfig = is_array($bootstrapConfig['database'] ?? null)
        ? $bootstrapConfig['database']
        : [];

    try {
        $repository = new BootstrapHardwareInventoryRepository($databaseConfig);
        $inserted = $repository->insertIfMissing($report);
        header('X-FoxesCraft-Hardware-Inventory: ' . ($inserted ? 'inserted' : 'existing'));
    } catch (Throwable $exception) {
        header('X-FoxesCraft-Hardware-Inventory: unavailable');
        error_log(sprintf(
            '[FoxesCraft hardware inventory] request=%s system_hwid_prefix=%s exception=%s message=%s',
            $requestId,
            substr($report->systemHwid(), 0, 12),
            get_class($exception),
            $exception->getMessage()
        ));
    }
}

function publicArtifact(array $artifact): array
{
    return array(
        'url' => $artifact['url'],
        'sha256' => $artifact['sha256'],
        'size' => $artifact['size'],
    );
}

function requireAbsoluteDirectory($value): string
{
    if (!is_string($value) || $value === '') {
        fail(500, 'bootstrap_configuration_invalid', 'Bootstrap storage directory is empty or invalid.');
    }
    $isUnixAbsolute = substr($value, 0, 1) === DIRECTORY_SEPARATOR;
    $isWindowsAbsolute = preg_match('/^[A-Za-z]:[\\\\\/]/D', $value) === 1;
    if (!$isUnixAbsolute && !$isWindowsAbsolute) {
        fail(500, 'bootstrap_configuration_invalid', 'Bootstrap storage directory must be absolute.');
    }
    return rtrim($value, '/\\');
}

function requireStringList($value, string $field): array
{
    if (!is_array($value)) {
        fail(500, 'bootstrap_configuration_invalid', $field . ' must be an array.');
    }
    foreach ($value as $index => $entry) {
        if (!is_string($entry) || strpos($entry, "\0") !== false) {
            fail(500, 'bootstrap_configuration_invalid', $field . ' contains an invalid value.', array('index' => $index));
        }
    }
    return array_values($value);
}

function requireNonNegativeInteger($value, string $field): int
{
    if (!is_int($value) || $value < 0) {
        fail(500, 'bootstrap_configuration_invalid', $field . ' must be a non-negative integer.');
    }
    return $value;
}

function respondWithError(int $statusCode, string $errorCode, string $message, array $details = array()): void
{
    global $requestId;
    http_response_code($statusCode);
    header('Cache-Control: no-store');
    header('X-FoxesCraft-Error-Code: ' . $errorCode);
    if ($statusCode >= 500) {
        header('Retry-After: 30');
    }

    $payload = array(
        'error' => $errorCode,
        'message' => $message,
        'request_id' => is_string($requestId) ? $requestId : '',
    );
    if (count($details) > 0) {
        $payload['details'] = $details;
    }
    $body = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    echo $body === false ? '{"error":"bootstrap_manifest_error"}' : $body;
    exit;
}

function createRequestId(): string
{
    try {
        return bin2hex(random_bytes(8));
    } catch (Throwable $exception) {
        return str_replace('.', '', uniqid('manifest-', true));
    }
}

function fail(int $statusCode, string $errorCode, string $message, array $details = array()): void
{
    throw new BootstrapHttpException($statusCode, $errorCode, $message, $details);
}

final class BootstrapHttpException extends RuntimeException
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
