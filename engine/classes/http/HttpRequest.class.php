<?php

declare(strict_types=1);

final class HttpRequest
{
    private const TELEMETRY_ACTION_FIELDS = [
        'sysRequest' => 'system_requests',
        'admPanel' => 'admin_panel',
        'user_doaction' => 'user_settings',
        'userAction' => 'authentication',
        'newsAction' => 'news',
    ];

    private array $post;
    private array $query;
    private array $cookies;
    private array $files;
    private array $server;

    public function __construct(
        array $post = [],
        array $query = [],
        array $cookies = [],
        array $files = [],
        array $server = [],
        private ?NetworkContext $network = null,
    ) {
        $this->post = $this->normalizeArray($post);
        $this->query = $this->normalizeArray($query);
        $this->cookies = $this->normalizeArray($cookies);
        $this->files = array_slice($files, 0, 50, true);
        $this->server = $server;
    }

    public static function fromGlobals(?NetworkContext $network = null): self
    {
        return new self($_POST, $_GET, $_COOKIE, $_FILES, $_SERVER, $network);
    }

    public function method(): string
    {
        return strtoupper((string)($this->server['REQUEST_METHOD'] ?? 'GET'));
    }

    public function isPost(): bool
    {
        return $this->method() === 'POST';
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->post);
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $this->post[$key] ?? $default;
    }

    public function query(string $key, mixed $default = null): mixed
    {
        return $this->query[$key] ?? $default;
    }

    public function string(string $key, string $default = ''): string
    {
        return $this->scalarString($this->input($key, $default), $default);
    }

    public function queryString(string $key, string $default = ''): string
    {
        return $this->scalarString($this->query($key, $default), $default);
    }

    public function integer(string $key, int $default = 0): int
    {
        $value = filter_var($this->input($key), FILTER_VALIDATE_INT);
        return $value === false ? $default : (int)$value;
    }

    public function boolean(string $key, bool $default = false): bool
    {
        $value = filter_var($this->input($key), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        return $value ?? $default;
    }

    public function all(): array
    {
        return $this->post;
    }

    public function allQuery(): array
    {
        return $this->query;
    }

    public function file(string $key): ?array
    {
        $file = $this->files[$key] ?? null;
        return is_array($file) ? $file : null;
    }

    public function cookie(string $key): ?string
    {
        $value = $this->cookies[$key] ?? null;
        return is_string($value) ? $value : null;
    }

    public function header(string $name, string $default = ''): string
    {
        $normalized = strtoupper(str_replace('-', '_', trim($name)));
        $key = in_array($normalized, ['CONTENT_TYPE', 'CONTENT_LENGTH'], true)
            ? $normalized
            : 'HTTP_' . $normalized;
        $value = $this->server[$key] ?? $default;
        return is_scalar($value) ? trim((string)$value) : $default;
    }

    public function userAgent(): string
    {
        return $this->header('User-Agent');
    }

    public function csrfToken(): ?string
    {
        $bodyToken = $this->input('csrf_token');
        if (is_string($bodyToken) && $bodyToken !== '') {
            return $bodyToken;
        }
        $headerToken = $this->header('X-CSRF-Token');
        return $headerToken !== '' ? $headerToken : null;
    }

    public function clientIp(): string
    {
        return $this->network?->clientIp() ?? (string)($this->server['REMOTE_ADDR'] ?? '127.0.0.1');
    }

    public function isSecure(): bool
    {
        return $this->network?->isSecure()
            ?? strtolower((string)($this->server['HTTPS'] ?? '')) === 'on';
    }

    public function uri(): string
    {
        return (string)($this->server['REQUEST_URI'] ?? '/');
    }

    public function path(): string
    {
        $path = parse_url($this->uri(), PHP_URL_PATH);
        return is_string($path) && $path !== '' ? $path : '/';
    }

    public function expectsJson(): bool
    {
        return str_contains(strtolower($this->header('Accept')), 'application/json')
            || strtolower($this->header('X-Requested-With')) === 'xmlhttprequest'
            || str_starts_with($this->path(), '/api/');
    }

    /** @return array<string, mixed> */
    public function telemetryContext(): array
    {
        $actionField = '';
        $action = '';
        $channel = '';
        foreach (self::TELEMETRY_ACTION_FIELDS as $field => $candidateChannel) {
            $candidate = $this->scalarString($this->post[$field] ?? $this->query[$field] ?? '', '');
            if ($candidate === '') {
                continue;
            }
            $actionField = $field;
            $action = $this->safeTelemetryValue($candidate);
            $channel = $candidateChannel;
            break;
        }

        if ($channel === '') {
            $channel = str_starts_with($this->path(), '/api/')
                ? 'api'
                : ($this->isPost() ? 'form' : ($this->expectsJson() ? 'ajax' : 'frontend'));
        }

        $context = [
            'requestChannel' => $channel,
            'requestInputKeys' => $this->safeTelemetryKeys($this->post),
            'queryKeys' => $this->safeTelemetryKeys($this->query),
            'uploadFields' => $this->safeTelemetryKeys($this->files),
            'expectsJson' => $this->expectsJson(),
        ];
        if ($actionField !== '') {
            $context['actionField'] = $actionField;
            $context['action'] = $action;
        }

        $contentType = mb_substr($this->header('Content-Type'), 0, 160);
        if ($contentType !== '') {
            $context['contentType'] = $contentType;
        }
        $contentLength = filter_var($this->header('Content-Length'), FILTER_VALIDATE_INT);
        if ($contentLength !== false && $contentLength >= 0) {
            $context['contentLength'] = (int)$contentLength;
        }
        return $context;
    }

    public function telemetryOperation(): string
    {
        $context = $this->telemetryContext();
        $channel = (string)($context['requestChannel'] ?? 'request');
        $action = (string)($context['action'] ?? 'request');
        return $channel . '.' . ($action !== '' ? $action : 'request');
    }

    /** @param array<int|string, mixed> $values @return list<string> */
    private function safeTelemetryKeys(array $values): array
    {
        $keys = [];
        foreach (array_keys($values) as $key) {
            if (!is_string($key) || preg_match('/^[A-Za-z0-9_.-]{1,64}$/D', $key) !== 1) {
                continue;
            }
            $normalized = preg_replace('/[^a-z0-9]/', '', strtolower($key)) ?? '';
            if (preg_match('/(?:password|passwd|secret|token|authorization|cookie|csrf|securekey)/', $normalized) === 1) {
                continue;
            }
            $keys[] = $key;
            if (count($keys) >= 40) {
                break;
            }
        }
        sort($keys, SORT_STRING);
        return $keys;
    }

    private function safeTelemetryValue(string $value): string
    {
        $value = preg_replace('/[^A-Za-z0-9_.-]+/', '.', trim($value)) ?? '';
        return trim(substr($value, 0, 96), '.-');
    }

    private function scalarString(mixed $value, string $default): string
    {
        return is_scalar($value) ? trim((string)$value) : $default;
    }

    private function normalizeArray(array $input, int $depth = 0): array
    {
        if ($depth > 3) {
            return [];
        }

        $normalized = [];
        foreach (array_slice($input, 0, 200, true) as $key => $value) {
            if (!is_int($key) && (!is_string($key) || preg_match('/^[A-Za-z0-9_.-]{1,64}$/', $key) !== 1)) {
                continue;
            }
            if (is_array($value)) {
                $normalized[$key] = $this->normalizeArray($value, $depth + 1);
            } elseif (is_scalar($value) || $value === null) {
                $normalized[$key] = is_string($value) ? trim($value) : $value;
            }
        }
        return $normalized;
    }
}
