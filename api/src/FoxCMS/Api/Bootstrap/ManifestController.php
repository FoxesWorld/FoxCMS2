<?php

declare(strict_types=1);

namespace FoxCMS\Api\Bootstrap;

use FoxCMS\Api\Core\HttpException;
use FoxCMS\Api\Core\JsonResponse;
use FoxCMS\Api\Core\Request;
use FoxCMS\Api\Core\RequestId;
use Throwable;

final class ManifestController
{
    private const SCHEMA_VERSION = 1;

    /** @param array<string, mixed> $config */
    public function __construct(
        array $config,
        private readonly Request $request,
    ) {
        $this->settings = new BootstrapSettings($config);
    }

    private readonly BootstrapSettings $settings;

    public function run(): never
    {
        $requestId = RequestId::create();
        $this->applyHeaders($requestId);

        if ($this->request->method() === 'OPTIONS') {
            header('Cache-Control: no-store');
            http_response_code(204);
            exit;
        }

        try {
            $this->request->requireMethod('GET', 'POST');
            if ($this->request->method() === 'POST') {
                (new HardwareInventoryRegistrar($this->settings))->register($requestId);
            } else {
                header('X-FoxesCraft-Hardware-Inventory: not-provided');
            }

            $isGet = $this->request->method() === 'GET';
            JsonResponse::send(
                (new ManifestBuilder($this->settings))->build(ArtifactCatalog::requestPlatform()),
                headers: [
                    'Cache-Control' => $isGet
                        ? sprintf('public, max-age=%d, stale-while-revalidate=300', $this->settings->cacheMaxAge())
                        : 'no-store',
                ],
                conditional: $isGet,
            );
        } catch (HttpException $error) {
            $this->respondError($requestId, $error);
        } catch (Throwable $error) {
            error_log(sprintf(
                '[FoxesCraft bootstrap manifest] request=%s exception=%s message=%s source=%s:%d',
                $requestId,
                $error::class,
                $error->getMessage(),
                $error->getFile(),
                $error->getLine(),
            ));
            $this->respondError($requestId, new HttpException(
                500,
                'bootstrap_manifest_internal_error',
                'Bootstrap manifest cannot be generated because an unexpected server error occurred.',
            ));
        }
    }

    private function applyHeaders(string $requestId): void
    {
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: no-referrer');
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, If-None-Match');
        header('X-FoxesCraft-Request-Id: ' . $requestId);
        header('X-FoxesCraft-Manifest-Schema: ' . self::SCHEMA_VERSION);
    }

    private function respondError(string $requestId, HttpException $error): never
    {
        $headers = [
            'X-FoxesCraft-Error-Code' => $error->errorCode(),
            'X-FoxesCraft-Request-Id' => $requestId,
        ];
        if ($error->statusCode() >= 500) {
            $headers['Retry-After'] = '30';
        }
        JsonResponse::send([
            'error' => $error->errorCode(),
            'message' => $error->getMessage(),
            'request_id' => $requestId,
            ...($error->details() !== [] ? ['details' => $error->details()] : []),
        ], $error->statusCode(), ['Cache-Control' => 'no-store', ...$headers]);
    }
}
