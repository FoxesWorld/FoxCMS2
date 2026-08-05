<?php

declare(strict_types=1);

namespace FoxCMS\Api\Launcher;

use FoxCMS\Api\Bootstrap\BootstrapConfig;
use FoxCMS\Api\Bootstrap\RuntimeCatalog;
use FoxCMS\Api\Core\HttpException;
use FoxCMS\Api\Core\JsonResponse;
use FoxCMS\Api\Core\Request;
use Throwable;

final class RuntimeCatalogController
{
    private const PUBLIC_STORAGE_PREFIX = '/uploads/bootstrap/';

    public function __construct(
        private readonly string $rootDirectory,
        private readonly Request $request,
    ) {
    }

    public function run(): never
    {
        $config = BootstrapConfig::load($this->rootDirectory);
        $this->applyHeaders();

        try {
            $this->request->requireMethod('POST');
            (new BridgeAuthenticator())->authenticate($this->request);
            $platform = trim((string)$this->request->post('platform'));
            $version = trim((string)$this->request->post('version'));
            if ($platform === '' || $version === '') {
                throw new HttpException(422, 'runtime_request_incomplete', 'platform and version are required.');
            }

            $previousQuery = $_GET;
            $_GET = [
                'platform' => $platform,
                'version' => $version,
                'distribution' => 'any',
                'allow_prerelease' => 'false',
                'client_version' => trim((string)($this->request->post('client_version') ?? 'KaylasLauncher')),
            ];
            try {
                $locator = new RuntimeArchiveLocator();
                $storageDirectory = $locator->storageDirectory($config['storage_directory'] ?? null);
                $runtime = (new RuntimeCatalog())->resolve($storageDirectory);
            } finally {
                $_GET = $previousQuery;
            }

            $artifacts = is_array($runtime['artifacts'] ?? null) ? $runtime['artifacts'] : [];
            $descriptor = $artifacts[$platform] ?? null;
            if (!is_array($descriptor)) {
                throw new HttpException(500, 'runtime_descriptor_missing', 'Selected runtime descriptor is missing.');
            }

            $url = (string)($descriptor['url'] ?? '');
            if (!str_starts_with($url, self::PUBLIC_STORAGE_PREFIX)) {
                throw new HttpException(500, 'runtime_descriptor_unsafe', 'Selected runtime URL is outside bootstrap storage.');
            }
            $relativePath = rawurldecode(substr($url, strlen(self::PUBLIC_STORAGE_PREFIX)));
            $archive = $locator->resolve($storageDirectory, $relativePath);
            $md5 = md5_file($archive);
            if (!is_string($md5) || preg_match('/^[a-f0-9]{32}$/D', $md5) !== 1) {
                throw new HttpException(500, 'runtime_hash_failed', 'Selected runtime MD5 cannot be calculated.');
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
                throw new HttpException(500, 'runtime_descriptor_invalid', 'Selected runtime descriptor is incomplete.');
            }
            JsonResponse::send($response, headers: ['Cache-Control' => 'private, max-age=60']);
        } catch (HttpException $error) {
            JsonResponse::error($error->errorCode(), $error->getMessage(), $error->statusCode(), $error->details(), [
                'X-FoxesCraft-Error-Code' => $error->errorCode(),
            ]);
        } catch (Throwable $error) {
            error_log(sprintf(
                '[FoxesCraft launcher runtime catalog] exception=%s message=%s source=%s:%d',
                $error::class,
                $error->getMessage(),
                $error->getFile(),
                $error->getLine(),
            ));
            JsonResponse::error('runtime_catalog_internal_error', 'Runtime catalog request failed.', 500);
        }
    }

    private function applyHeaders(): void
    {
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: no-referrer');
    }
}
