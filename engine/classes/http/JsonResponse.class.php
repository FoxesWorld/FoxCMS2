<?php

declare(strict_types=1);

use FoxCMS\Shared\Http\ResponseHeaders;

final class JsonResponse
{
    /**
     * @param array<string, mixed>|JsonSerializable $payload
     * @param array<string, scalar> $headers
     */
    public static function send(
        array|JsonSerializable $payload,
        int $status = 200,
        array $headers = [],
    ): never {
        $encoded = json_encode(
            $payload,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE,
        );
        if (!is_string($encoded)) {
            throw new RuntimeException('Unable to encode JSON response.');
        }

        self::begin($status, 'application/json; charset=UTF-8', $headers);
        exit($encoded);
    }

    /**
     * @param array<string, scalar> $headers
     * @param array<string, mixed> $context
     */
    public static function error(
        string $message,
        int $status,
        array $headers = [],
        array $context = [],
    ): never {
        if (class_exists(RequestTelemetry::class, false)) {
            $requestId = RequestTelemetry::requestId();
            $correlationId = RequestTelemetry::correlationId();
            if ($requestId !== '') {
                $context['requestId'] = $requestId;
            }
            if ($correlationId !== '') {
                $context['correlationId'] = $correlationId;
            }
        }

        self::send(array_merge($context, [
            'message' => $message,
            'type' => 'error',
        ]), $status, $headers);
    }

    /** @param array<string, scalar> $headers */
    public static function rawJson(string $payload, int $status = 200, array $headers = []): never
    {
        json_decode($payload, true, 32, JSON_THROW_ON_ERROR);
        self::begin($status, 'application/json; charset=UTF-8', $headers);
        exit($payload);
    }

    /** @param array<string, scalar> $headers */
    public static function text(
        string $payload,
        string $contentType = 'text/plain; charset=UTF-8',
        int $status = 200,
        array $headers = [],
    ): never {
        self::begin($status, $contentType, $headers);
        exit($payload);
    }

    /** @param array<string, scalar> $headers */
    private static function begin(int $status, string $contentType, array $headers): void
    {
        ResponseHeaders::begin($status, $contentType, $headers);
        self::emitTraceHeaders();

        if (class_exists(RequestTelemetry::class, false)) {
            RequestTelemetry::complete($status, [
                'responseContentType' => $contentType,
            ]);
        }
    }

    private static function emitTraceHeaders(): void
    {
        if (!class_exists(RequestTelemetry::class, false)) {
            return;
        }
        $requestId = RequestTelemetry::requestId();
        $correlationId = RequestTelemetry::correlationId();
        if ($requestId !== '') {
            ResponseHeaders::emit('X-Request-ID', $requestId);
        }
        if ($correlationId !== '') {
            ResponseHeaders::emit('X-Correlation-ID', $correlationId);
        }
    }
}
