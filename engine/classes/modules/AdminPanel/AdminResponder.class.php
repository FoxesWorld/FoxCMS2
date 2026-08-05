<?php

declare(strict_types=1);

/**
 * Single response boundary for administrative HTTP use-cases.
 */
final class AdminResponder
{
    public function __construct(private string $action)
    {
    }

    public function send(array $payload, int $status = 200): never
    {
        if ($status >= 400) {
            RequestTelemetry::rejectHttp(
                'admin.operation.rejected',
                $status,
                (string)($payload['message'] ?? 'Administrative operation was rejected.'),
                ['action' => $this->action],
            );
        }
        JsonResponse::send($payload, $status);
    }
}
