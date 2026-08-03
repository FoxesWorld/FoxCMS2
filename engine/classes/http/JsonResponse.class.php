<?php

declare(strict_types=1);

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
        if ($status < 100 || $status > 599) {
            throw new InvalidArgumentException('Invalid HTTP response status.');
        }
        if (str_contains($contentType, "\r") || str_contains($contentType, "\n")) {
            throw new InvalidArgumentException('Invalid response content type.');
        }
        foreach ($headers as $name => $value) {
            if (is_string($name) && is_scalar($value)) {
                self::validateHeader($name, (string)$value);
            }
        }

        http_response_code($status);
        self::emitHeader('Content-Type', $contentType);
        self::emitHeader('Cache-Control', 'no-store');
        self::emitHeader('X-Content-Type-Options', 'nosniff');
        foreach ($headers as $name => $value) {
            if (is_string($name) && is_scalar($value)) {
                self::emitHeader($name, (string)$value);
            }
        }
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
            self::emitHeader('X-Request-ID', $requestId);
        }
        if ($correlationId !== '') {
            self::emitHeader('X-Correlation-ID', $correlationId);
        }
    }

    private static function emitHeader(string $name, string $value): void
    {
        self::validateHeader($name, $value);
        header($name . ': ' . $value);
    }

    private static function validateHeader(string $name, string $value): void
    {
        if (preg_match('/^[A-Za-z0-9-]{1,64}$/D', $name) !== 1
            || str_contains($value, "\r")
            || str_contains($value, "\n")) {
            throw new InvalidArgumentException('Invalid HTTP response header.');
        }
    }
}
