<?php

declare(strict_types=1);

namespace FoxCMS\Engine\User;

final class UserActionResponder
{
    public function __construct(private readonly string $action)
    {
    }

    /** @param array<string, mixed> $payload */
    public function send(array $payload, int $status = 200): never
    {
        if ($status >= 400) {
            \RequestTelemetry::rejectHttp(
                'user_settings.operation.rejected',
                $status,
                (string)($payload['message'] ?? 'User settings operation was rejected.'),
                ['action' => $this->action],
            );
        }
        \JsonResponse::send($payload, $status);
    }
    /** @param array<string, mixed> $context */
    public function fatal(\Throwable $error, int $status = 500, array $context = []): never
    {
        $requestId = \RequestTelemetry::requestId();
        if ($requestId === '') {
            $requestId = \ExceptionContext::requestId('user-settings');
        }
        $this->send(
            \FoxCMS\Shared\Error\ThrowableDiagnostic::payload(
                $error,
                $requestId,
                defined('ROOT_DIR') ? ROOT_DIR : '',
                false,
                ['type' => 'error', 'action' => $this->action, ...$context],
            ),
            $status,
        );
    }

}
