<?php

declare(strict_types=1);

namespace FoxCMS\Api\Bootstrap;

use FoxCMS\Api\Core\HttpException;
use FoxCMS\Api\Core\JsonResponse;
use Throwable;

final class DownloadController
{
    /** @param array<string, mixed> $config */
    public function __construct(private readonly array $config)
    {
    }

    public function run(): never
    {
        try {
            $storageDirectory = trim((string)($this->config['storage_directory'] ?? ''));
            if ($storageDirectory === '') {
                throw new HttpException(500, 'bootstrap_configuration_invalid', 'Bootstrap storage is not configured.');
            }
            $platform = ArtifactCatalog::requestPlatform();
            $bootstrapper = (new ArtifactCatalog($storageDirectory))->discoverBootstrapper($platform);

            header('Cache-Control: no-store');
            header('X-Content-Type-Options: nosniff');
            header('X-FoxesCraft-Bootstrapper-Version: ' . $bootstrapper['version']);
            header('X-FoxesCraft-Bootstrapper-SHA256: ' . $bootstrapper['artifact']['sha256']);
            header('Location: ' . $bootstrapper['artifact']['url'], true, 302);
            exit;
        } catch (HttpException $error) {
            JsonResponse::error($error->errorCode(), $error->getMessage(), $error->statusCode(), $error->details());
        } catch (Throwable $error) {
            error_log('[FoxesCraft bootstrap download] ' . (string)$error);
            JsonResponse::error(
                'bootstrap_download_internal_error',
                'Bootstrapper download cannot be resolved.',
                500,
            );
        }
    }
}
