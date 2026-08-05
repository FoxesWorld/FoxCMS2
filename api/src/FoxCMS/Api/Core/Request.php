<?php

declare(strict_types=1);

namespace FoxCMS\Api\Core;

use JsonException;

final class Request
{
    private ?string $rawBody = null;

    /**
     * @param array<string, mixed> $server
     * @param array<string, mixed> $query
     * @param array<string, mixed> $post
     */
    public function __construct(
        private readonly array $server,
        private readonly array $query,
        private readonly array $post,
    ) {
    }

    public static function fromGlobals(): self
    {
        return new self($_SERVER, $_GET, $_POST);
    }

    public function method(): string
    {
        return strtoupper((string)($this->server['REQUEST_METHOD'] ?? 'GET'));
    }

    public function requestPath(): string
    {
        $path = parse_url((string)($this->server['REQUEST_URI'] ?? ''), PHP_URL_PATH);
        if (!is_string($path) || $path === '') {
            return '/';
        }
        return '/' . ltrim($path, '/');
    }

    public function apiRoute(string $prefix = '/api'): string
    {
        $path = $this->requestPath();
        if (str_contains(strtolower($path), '.php')) {
            throw new HttpException(404, 'route_not_found', 'API route not found.');
        }

        $explicit = trim((string)($this->server['FOX_API_ROUTE'] ?? ''));
        if ($explicit !== '') {
            return '/' . trim($explicit, '/');
        }

        $normalizedPrefix = '/' . trim($prefix, '/');
        if ($path === $normalizedPrefix || $path === $normalizedPrefix . '/') {
            return '/';
        }
        if (str_starts_with($path, $normalizedPrefix . '/')) {
            $path = substr($path, strlen($normalizedPrefix));
        }
        return '/' . trim($path, '/');
    }

    public function header(string $name): string
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        if (strcasecmp($name, 'Content-Type') === 0) {
            $key = 'CONTENT_TYPE';
        } elseif (strcasecmp($name, 'Content-Length') === 0) {
            $key = 'CONTENT_LENGTH';
        }
        return trim((string)($this->server[$key] ?? ''));
    }

    public function contentType(): string
    {
        return strtolower(trim(explode(';', $this->header('Content-Type'))[0]));
    }

    public function query(string $name): ?string
    {
        if (!array_key_exists($name, $this->query)) {
            return null;
        }
        $value = $this->query[$name];
        if (!is_scalar($value)) {
            throw new HttpException(400, 'invalid_request', 'Query parameter ' . $name . ' must be scalar.');
        }
        return trim((string)$value);
    }

    public function post(string $name): mixed
    {
        return $this->post[$name] ?? null;
    }

    public function integerQuery(string $name, int $default, int $minimum, int $maximum): int
    {
        $raw = $this->query($name);
        if ($raw === null || $raw === '') {
            return $default;
        }
        $value = filter_var($raw, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => $minimum, 'max_range' => $maximum],
        ]);
        if (!is_int($value)) {
            throw new HttpException(
                400,
                'invalid_request',
                sprintf('Query parameter %s must be an integer between %d and %d.', $name, $minimum, $maximum),
            );
        }
        return $value;
    }

    public function booleanQuery(string $name, bool $default): bool
    {
        $raw = $this->query($name);
        if ($raw === null || $raw === '') {
            return $default;
        }
        $value = filter_var($raw, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        if (!is_bool($value)) {
            throw new HttpException(400, 'invalid_request', 'Query parameter ' . $name . ' must be boolean.');
        }
        return $value;
    }

    public function requireMethod(string ...$allowed): void
    {
        $allowed = array_values(array_unique(array_map('strtoupper', $allowed)));
        if (in_array($this->method(), $allowed, true)) {
            return;
        }
        header('Allow: ' . implode(', ', $allowed));
        throw new HttpException(405, 'method_not_allowed', 'Method is not allowed.');
    }

    /** @return array<string, mixed> */
    public function jsonObject(int $maximumBytes): array
    {
        $declaredLength = max(0, (int)$this->header('Content-Length'));
        if ($declaredLength > $maximumBytes) {
            throw new HttpException(413, 'request_too_large', 'Request body exceeds the allowed size.');
        }

        $body = file_get_contents('php://input', false, null, 0, $maximumBytes + 1);
        if (!is_string($body)) {
            throw new HttpException(400, 'request_body_unavailable', 'Request body could not be read.');
        }
        $this->rawBody = $body;
        if (strlen($body) > $maximumBytes) {
            throw new HttpException(413, 'request_too_large', 'Request body exceeds the allowed size.');
        }
        if (trim($body) === '') {
            throw new HttpException(400, 'request_body_empty', 'Request body must not be empty.');
        }

        try {
            $payload = json_decode($body, true, 128, JSON_THROW_ON_ERROR);
        } catch (JsonException $error) {
            throw new HttpException(400, 'request_json_invalid', 'Request body contains invalid JSON.', [], $error);
        }
        if (!is_array($payload) || array_is_list($payload)) {
            throw new HttpException(400, 'request_json_invalid', 'Request JSON root must be an object.');
        }
        return $payload;
    }

    public function rawBody(): string
    {
        return $this->rawBody ?? '';
    }
}
