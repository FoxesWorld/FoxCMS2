<?php

declare(strict_types=1);

/** Stable alias for the newest bootstrapper published for a platform. */

$config = require __DIR__ . '/config.php';
require_once __DIR__ . '/artifact-catalog.php';

try {
    $storageDirectory = isset($config['storage_directory']) ? (string) $config['storage_directory'] : '';
    if ($storageDirectory === '') {
        throw new BootstrapCatalogException(500, 'bootstrap_configuration_invalid', 'Bootstrap storage is not configured.');
    }

    $platform = catalogRequestPlatform();
    $bootstrapper = discoverBootstrapperArtifact($storageDirectory, $platform);

    header('Cache-Control: no-store');
    header('X-Content-Type-Options: nosniff');
    header('X-FoxesCraft-Bootstrapper-Version: ' . $bootstrapper['version']);
    header('X-FoxesCraft-Bootstrapper-SHA256: ' . $bootstrapper['artifact']['sha256']);
    header('Location: ' . $bootstrapper['artifact']['url'], true, 302);
    exit;
} catch (BootstrapCatalogException $exception) {
    respondWithDownloadError(
        $exception->getStatusCode(),
        $exception->getErrorCode(),
        $exception->getMessage()
    );
} catch (Throwable $exception) {
    error_log('[FoxesCraft bootstrap download] ' . (string) $exception);
    respondWithDownloadError(500, 'bootstrap_download_internal_error', 'Bootstrapper download cannot be resolved.');
}

function respondWithDownloadError(int $statusCode, string $errorCode, string $message): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    header('X-Content-Type-Options: nosniff');
    $body = json_encode(
        array('error' => $errorCode, 'message' => $message),
        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
    );
    echo $body === false ? '{"error":"bootstrap_download_error"}' : $body;
    exit;
}
