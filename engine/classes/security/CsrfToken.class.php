<?php

declare(strict_types=1);

final class CsrfToken
{
    private const SESSION_KEY = 'foxescraft_csrf_token';

    public static function issue(): string
    {
        self::ensureSession();
        $token = $_SESSION[self::SESSION_KEY] ?? null;
        if (!is_string($token) || strlen($token) !== 64) {
            $token = bin2hex(random_bytes(32));
            $_SESSION[self::SESSION_KEY] = $token;
        }
        return $token;
    }

    public static function validate(?string $candidate): bool
    {
        if ($candidate === null || $candidate === '') {
            return false;
        }
        return hash_equals(self::issue(), $candidate);
    }

    public static function requireValid(?string $candidate): void
    {
        if (self::validate($candidate)) {
            return;
        }

        http_response_code(403);
        header('Content-Type: application/json; charset=UTF-8');
        die(json_encode([
            'type' => 'error',
            'message' => 'Защитный токен устарел. Обновите страницу.',
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    public static function rotate(): string
    {
        self::ensureSession();
        unset($_SESSION[self::SESSION_KEY]);
        return self::issue();
    }

    private static function ensureSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }
}
