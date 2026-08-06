<?php

declare(strict_types=1);

namespace FoxCMS\Api\Launcher;

use FoxCMS\Shared\Environment\Environment;
use FoxCMS\Api\Core\HttpException;
use FoxCMS\Api\Core\Request;
use InvalidArgumentException;
use Throwable;

final class FileCatalogController
{
    private const IDENTIFIER_PATTERN = '/^[A-Za-z0-9._-]{1,64}$/D';
    private const MIN_PLATFORM = 0;
    private const MAX_PLATFORM = 4;

    public function __construct(
        private readonly string $rootDirectory,
        private readonly Request $request,
    ) {
    }

    public function run(): never
    {
        $environment = Environment::boot($this->rootDirectory);
        $this->applyHeaders();

        try {
            $this->request->requireMethod('POST');
            (new BridgeAuthenticator($environment))->authenticate($this->request);
            [$client, $version, $platform] = $this->validatedParameters();
            $this->loadScanner();

            $ttl = $environment->integer('FOXESCRAFT_LAUNCHER_CATALOG_TTL', 60, 1, 3600);
            $cache = new FileCatalogCache(
                $this->rootDirectory . '/engine/cache/launcher-file-catalog',
                $ttl,
            );
            $key = hash('sha256', $client . "\0" . $version . "\0" . (string)$platform);
            $result = $cache->remember($key, function () use ($client, $version, $platform, $environment): string {
                $config = [
                    'launcherSettings' => [
                        'gameFiles' => trim((string)($environment->string('FOXESCRAFT_GAME_FILES_DIR', 'game/') ?? 'game/')),
                    ],
                ];
                $scanner = new \GameScanner($client, $version, $platform, $config);
                $scanner->scan();
                return $scanner->toJson();
            });

            header('X-Launcher-Catalog-Cache: ' . $result['state']);
            http_response_code(200);
            echo $result['body'];
            exit;
        } catch (InvalidArgumentException $error) {
            $this->error('invalid_catalog_request', $error->getMessage(), 422);
        } catch (HttpException $error) {
            $this->error($error->errorCode(), $error->getMessage(), $error->statusCode());
        } catch (Throwable $error) {
            $requestId = \FoxCMS\Api\Core\RequestId::create();
            error_log('[FoxesCraft launcher file catalog][' . $requestId . '] ' . $error->getMessage());
            \FoxCMS\Api\Core\JsonResponse::send(
                \FoxCMS\Shared\Error\ThrowableDiagnostic::payload(
                    $error,
                    $requestId,
                    $this->rootDirectory,
                    $environment->boolean('FOXESCRAFT_DEBUG', false),
                    ['error' => 'catalog_unavailable'],
                ),
                503,
                ['Cache-Control' => 'no-store', 'X-Request-ID' => $requestId],
            );
        }
    }

    /** @return array{0: string, 1: string, 2: int} */
    private function validatedParameters(): array
    {
        $client = trim((string)$this->request->post('client'));
        $version = trim((string)$this->request->post('version'));
        $platformRaw = $this->request->post('platform');
        if (preg_match(self::IDENTIFIER_PATTERN, $client) !== 1) {
            throw new HttpException(422, 'invalid_client', 'Invalid client identifier.');
        }
        if (preg_match(self::IDENTIFIER_PATTERN, $version) !== 1) {
            throw new HttpException(422, 'invalid_version', 'Invalid version identifier.');
        }
        if (filter_var($platformRaw, FILTER_VALIDATE_INT) === false) {
            throw new HttpException(422, 'invalid_platform', 'Invalid launcher platform.');
        }
        $platform = (int)$platformRaw;
        if ($platform < self::MIN_PLATFORM || $platform > self::MAX_PLATFORM) {
            throw new HttpException(422, 'invalid_platform', 'Unsupported launcher platform.');
        }
        return [$client, $version, $platform];
    }

    private function loadScanner(): void
    {
        if (!defined('FOXXEY')) {
            define('FOXXEY', true);
        }
        if (!defined('ROOT_DIR')) {
            define('ROOT_DIR', $this->rootDirectory);
        }
        if (!defined('UPLOADS_DIR')) {
            define('UPLOADS_DIR', '/uploads/');
        }
        require_once $this->rootDirectory . '/engine/classes/modules/GameScanner/GameScanner.class.php';
    }

    private function applyHeaders(): void
    {
        header('Content-Type: application/json; charset=UTF-8');
        header('Cache-Control: private, no-store');
        header('X-Content-Type-Options: nosniff');
    }

    private function error(string $code, string $message, int $status): never
    {
        http_response_code($status);
        echo json_encode([
            'type' => 'error',
            'code' => $code,
            'message' => $message,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        exit;
    }
}
