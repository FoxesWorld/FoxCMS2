<?php

declare(strict_types=1);

namespace FoxCMS\Api\Bootstrap;

use FoxCMS\Api\Core\HttpException;
use FoxCMS\Api\Core\Request;

/**
 * CORS boundary for the public bootstrap manifest. Read requests are public;
 * browser-origin hardware writes require an explicitly allowed origin. Native
 * launcher requests carry no Origin header and remain supported.
 */
final class BootstrapCorsPolicy
{
    /** @param list<string> $allowedWriteOrigins */
    public function __construct(private readonly array $allowedWriteOrigins)
    {
    }

    public function apply(Request $request): void
    {
        if ($request->method() === 'GET') {
            header('Access-Control-Allow-Origin: *');
            return;
        }
        if ($request->method() !== 'POST') {
            return;
        }

        $origin = $request->origin();
        if ($origin === '') {
            return;
        }
        $origin = $this->requireAllowedWriteOrigin($origin);
        header('Access-Control-Allow-Origin: ' . $origin);
        header('Vary: Origin', false);
    }

    public function handlePreflight(Request $request): never
    {
        $requestedMethod = strtoupper($request->header('Access-Control-Request-Method'));
        if (!in_array($requestedMethod, ['GET', 'POST'], true)) {
            throw new HttpException(405, 'cors_method_not_allowed', 'Requested CORS method is not allowed.');
        }

        if ($requestedMethod === 'GET') {
            header('Access-Control-Allow-Origin: *');
        } else {
            $origin = $request->origin();
            if ($origin === '') {
                throw new HttpException(403, 'cors_origin_required', 'A browser origin is required for hardware inventory preflight.');
            }
            $origin = $this->requireAllowedWriteOrigin($origin);
            header('Access-Control-Allow-Origin: ' . $origin);
            header('Vary: Origin', false);
        }

        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, If-None-Match');
        header('Access-Control-Max-Age: 600');
        header('Cache-Control: no-store');
        http_response_code(204);
        exit;
    }

    private function requireAllowedWriteOrigin(string $origin): string
    {
        $normalized = self::normalizeOrigin($origin);
        if ($normalized === '' || !in_array($normalized, $this->allowedWriteOrigins, true)) {
            throw new HttpException(403, 'cors_origin_not_allowed', 'Browser origin is not allowed to submit hardware inventory.');
        }
        return $normalized;
    }

    public static function normalizeOrigin(string $value): string
    {
        $value = trim($value);
        if ($value === '' || str_contains($value, "\r") || str_contains($value, "\n")) {
            return '';
        }
        $parts = parse_url($value);
        if (!is_array($parts)) {
            return '';
        }
        $scheme = strtolower((string)($parts['scheme'] ?? ''));
        $host = strtolower((string)($parts['host'] ?? ''));
        if (!in_array($scheme, ['http', 'https'], true) || $host === '') {
            return '';
        }
        $path = (string)($parts['path'] ?? '');
        if (isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])
            || !in_array($path, ['', '/'], true)) {
            return '';
        }
        $port = isset($parts['port']) ? (int)$parts['port'] : null;
        if ($port !== null && ($port < 1 || $port > 65535)) {
            return '';
        }
        $defaultPort = ($scheme === 'https' && $port === 443) || ($scheme === 'http' && $port === 80);
        return $scheme . '://' . $host . ($port !== null && !$defaultPort ? ':' . $port : '');
    }
}
