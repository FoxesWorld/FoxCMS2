<?php

declare(strict_types=1);

namespace FoxCMS\Api\Core;

use FoxCMS\Shared\Http\ResponseHeaders;
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

        if ($conditional) {
            $etag = '"' . hash('sha256', $body) . '"';
            $headers['ETag'] = $etag;
            if (trim((string)($_SERVER['HTTP_IF_NONE_MATCH'] ?? '')) === $etag) {
                ResponseHeaders::begin(304, 'application/json; charset=UTF-8', $headers);
                exit;
            }
        }

        ResponseHeaders::begin($status, 'application/json; charset=UTF-8', $headers);
        if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'HEAD') {
            echo $body;
        }
        exit;
    }

    /** @param array<string, mixed> $details @param array<string, string> $headers */
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
