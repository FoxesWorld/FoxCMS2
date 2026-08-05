<?php

declare(strict_types=1);

namespace FoxCMS\Api\Core;

use JsonException;
use RuntimeException;

final class JsonResponse
{
    /** @param array<string, string> $headers */
    public static function send(
        mixed $payload,
        int $status = 200,
        array $headers = [],
        bool $conditional = false,
    ): never {
        try {
            $body = json_encode(
                $payload,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR,
            );
        } catch (JsonException $error) {
            throw new RuntimeException('Unable to encode JSON response.', 0, $error);
        }

        http_response_code($status);
        header('Content-Type: application/json; charset=UTF-8');
        header('X-Content-Type-Options: nosniff');
        foreach ($headers as $name => $value) {
            header($name . ': ' . $value);
        }

        if ($conditional) {
            $etag = '"' . hash('sha256', $body) . '"';
            header('ETag: ' . $etag);
            if (trim((string)($_SERVER['HTTP_IF_NONE_MATCH'] ?? '')) === $etag) {
                http_response_code(304);
                exit;
            }
        }

        if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'HEAD') {
            echo $body;
        }
        exit;
    }

    /** @param array<string, mixed> $details */
    public static function error(
        string $code,
        string $message,
        int $status,
        array $details = [],
        array $headers = [],
    ): never {
        $payload = ['error' => $code, 'message' => $message];
        if ($details !== []) {
            $payload['details'] = $details;
        }
        self::send($payload, $status, array_merge(['Cache-Control' => 'no-store, max-age=0'], $headers));
    }
}
