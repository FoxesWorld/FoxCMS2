<?php

declare(strict_types=1);

namespace FoxCMS\Api\Core;

use FoxCMS\Shared\Error\ThrowableDiagnostic;
use Throwable;

final class FatalResponse
{
    /** @param array<string, mixed> $context */
    public static function send(
        Throwable $error,
        ApplicationContext $application,
        string $errorCode,
        int $status = 500,
        string $requestId = '',
        array $context = [],
    ): never {
        $requestId = trim($requestId) !== '' ? trim($requestId) : RequestId::create();
        error_log(sprintf(
            '[FoxCMS API][%s] code=%s exception=%s message=%s source=%s:%d',
            $requestId,
            $errorCode,
            $error::class,
            $error->getMessage(),
            $error->getFile(),
            $error->getLine(),
        ));

        JsonResponse::send(
            ThrowableDiagnostic::payload(
                $error,
                $requestId,
                $application->rootDirectory(),
                $application->environment()->boolean('FOXESCRAFT_DEBUG', false),
                ['error' => $errorCode, ...$context],
            ),
            $status,
            [
                'Cache-Control' => 'no-store, max-age=0',
                'X-Request-ID' => $requestId,
                'X-FoxesCraft-Error-Code' => $errorCode,
            ],
        );
    }
}
