<?php

declare(strict_types=1);

namespace FoxCMS\Api\Health;

use FoxCMS\Api\Core\ApplicationContext;
use FoxCMS\Api\Core\DatabaseFactory;
use FoxCMS\Api\Core\HttpException;
use FoxCMS\Api\Core\JsonResponse;
use FoxCMS\Api\Core\Request;
use Throwable;

final class HealthApiApplication
{
    public function __construct(
        private readonly ApplicationContext $context,
        private readonly Request $request,
    ) {
        $context->requireEngine(
            'classes/syslib/database.php',
            'classes/services/HealthCheckService.class.php',
        );
    }

    public function run(): never
    {
        try {
            $this->request->requireMethod('GET', 'HEAD');
            $config = $this->context->config();
            $this->authorize($config);
            $payload = $this->inspect($config);

            $healthy = ($payload['ok'] ?? false) === true;
            JsonResponse::send(
                $payload,
                $healthy ? 200 : 503,
                array_filter([
                    'Cache-Control' => 'no-store, max-age=0',
                    'Retry-After' => $healthy ? null : '30',
                ], static fn (mixed $value): bool => is_string($value)),
            );
        } catch (HttpException $error) {
            JsonResponse::error($error->errorCode(), $error->getMessage(), $error->statusCode(), $error->details());
        }
    }

    /** @param array<string, mixed> $config @return array<string, mixed> */
    private function inspect(array $config): array
    {
        try {
            return (new \HealthCheckService(
                DatabaseFactory::create($config),
                $config,
                $this->context->rootDirectory(),
            ))->inspect();
        } catch (Throwable $error) {
            error_log('[FoxCMS health] ' . $error->getMessage());
            return [
                'ok' => false,
                'service' => (string)($config['other']['webserviceName'] ?? 'FoxesCraft'),
                'version' => (string)($config['siteSettings']['ServiceVersion'] ?? 'unknown'),
                'timestamp' => gmdate('c'),
                'checks' => ['database' => ['ok' => false]],
            ];
        }
    }

    /** @param array<string, mixed> $config */
    private function authorize(array $config): void
    {
        $healthToken = (string)($config['other']['healthToken'] ?? '');
        if ($healthToken === '') {
            return;
        }
        $authorization = $this->request->header('Authorization');
        $provided = str_starts_with($authorization, 'Bearer ')
            ? substr($authorization, 7)
            : '';
        if ($provided === '' || !hash_equals($healthToken, $provided)) {
            http_response_code(404);
            exit;
        }
    }
}
