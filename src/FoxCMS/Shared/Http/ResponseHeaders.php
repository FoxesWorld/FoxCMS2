<?php

declare(strict_types=1);

namespace FoxCMS\Shared\Http;

use InvalidArgumentException;

/** Shared response-header validation for the web engine and standalone API. */
final class ResponseHeaders
{
    /** @param array<string, scalar> $headers */
    public static function begin(
        int $status,
        string $contentType,
        array $headers = [],
        bool $defaultNoStore = true,
    ): void {
        self::validateStatus($status);
        self::validateValue($contentType);
        foreach ($headers as $name => $value) {
            if (!is_string($name) || !is_scalar($value)) {
                throw new InvalidArgumentException('Invalid HTTP response header.');
            }
            self::validate($name, (string)$value);
        }

        http_response_code($status);
        self::emit('Content-Type', $contentType);
        if ($defaultNoStore) {
            self::emit('Cache-Control', 'no-store');
        }
        self::emit('X-Content-Type-Options', 'nosniff');
        foreach ($headers as $name => $value) {
            self::emit($name, (string)$value);
        }
    }

    public static function emit(string $name, string $value, bool $replace = true): void
    {
        self::validate($name, $value);
        header($name . ': ' . $value, $replace);
    }

    public static function validate(string $name, string $value): void
    {
        if (preg_match('/^[A-Za-z0-9-]{1,64}$/D', $name) !== 1) {
            throw new InvalidArgumentException('Invalid HTTP response header name.');
        }
        self::validateValue($value);
    }

    public static function validateStatus(int $status): void
    {
        if ($status < 100 || $status > 599) {
            throw new InvalidArgumentException('Invalid HTTP response status.');
        }
    }

    private static function validateValue(string $value): void
    {
        if (str_contains($value, "\r") || str_contains($value, "\n")) {
            throw new InvalidArgumentException('Invalid HTTP response header value.');
        }
    }
}
