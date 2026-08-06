<?php

declare(strict_types=1);

/**
 * Dependency-free failure handler loaded before the repository autoloader.
 * It guarantees a diagnostic response even when bootstrap or autoload cannot start.
 */
final class EmergencyRuntimeHandler
{
    private static string $rootDirectory = '';
    private static bool $registered = false;
    private static bool $emitted = false;

    public static function register(string $rootDirectory): void
    {
        if (self::$registered) {
            return;
        }

        self::$registered = true;
        self::$rootDirectory = rtrim($rootDirectory, '/\\');
        $GLOBALS['foxRuntimeErrorHandlerRegistered'] = false;
        ini_set('display_errors', '0');
        ini_set('display_startup_errors', '0');
        ini_set('log_errors', '1');
        error_reporting(E_ALL);

        set_exception_handler(static function (Throwable $throwable): void {
            if (($GLOBALS['foxRuntimeErrorHandlerRegistered'] ?? false) === true
                && class_exists('RuntimeErrorHandler', false)) {
                RuntimeErrorHandler::handleException($throwable);
                return;
            }

            self::emit($throwable);
        });

        register_shutdown_function(static function (): void {
            if (self::$emitted || ($GLOBALS['foxRuntimeErrorHandlerRegistered'] ?? false) === true) {
                return;
            }

            $error = error_get_last();
            if (!is_array($error)
                || !in_array((int)($error['type'] ?? 0), [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
                return;
            }

            self::emit($error);
        });
    }

    private static function emit(Throwable|array $failure): void
    {
        if (self::$emitted) {
            return;
        }
        self::$emitted = true;

        while (ob_get_level() > 0) {
            if (!@ob_end_clean()) {
                break;
            }
        }

        $requestId = self::requestId();
        if ($failure instanceof Throwable) {
            $type = $failure::class;
            $message = $failure->getMessage();
            $file = $failure->getFile();
            $line = $failure->getLine();
        } else {
            $type = self::errorTypeName((int)($failure['type'] ?? 0));
            $message = (string)($failure['message'] ?? 'Unknown bootstrap failure.');
            $file = (string)($failure['file'] ?? 'unknown');
            $line = (int)($failure['line'] ?? 0);
        }

        $relativeFile = self::relativePath($file);
        $publicMessage = self::sanitizeMessage($message);
        $expectsJson = self::expectsJson();

        error_log(sprintf(
            '[FoxCMS bootstrap][%s] %s: %s at %s:%d',
            $requestId,
            $type,
            $message,
            $relativeFile,
            $line,
        ));

        if (!headers_sent()) {
            http_response_code(500);
            header('Cache-Control: no-store');
            header('X-Content-Type-Options: nosniff');
            header('X-FoxCMS-Request-Id: ' . $requestId);
            header('X-Request-ID: ' . $requestId);
            header('Content-Type: ' . ($expectsJson
                ? 'application/json; charset=UTF-8'
                : 'text/html; charset=UTF-8'));
        }

        if ($expectsJson) {
            $json = json_encode([
                'type' => 'error',
                'fatal' => true,
                'phase' => 'bootstrap',
                'exception' => $type,
                'message' => $publicMessage,
                'file' => $relativeFile,
                'line' => $line,
                'requestId' => $requestId,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
            echo is_string($json) ? $json : '{"type":"error","fatal":true,"phase":"bootstrap"}';
            return;
        }

        echo '<!doctype html><html lang="ru"><head><meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width,initial-scale=1">'
            . '<meta name="robots" content="noindex,nofollow">'
            . '<title>FoxCMS — fatal bootstrap error</title>'
            . '<style>html{color-scheme:dark}body{margin:0;min-height:100vh;display:grid;place-items:center;padding:24px;background:#090d0b;color:#eef4f0;font:16px/1.5 system-ui,sans-serif}main{width:min(920px,100%);padding:28px;border:1px solid #39443e;border-radius:16px;background:#111713}h1{margin:.25rem 0 1rem;font-size:clamp(1.6rem,4vw,2.6rem)}code{overflow-wrap:anywhere;color:#d6e5dc}dl{display:grid;grid-template-columns:max-content 1fr;gap:.65rem 1rem}dt{color:#9fb0a6}dd{margin:0}</style>'
            . '</head><body><main><p>FoxCMS bootstrap protection</p>'
            . '<h1>' . self::escape($type) . '</h1>'
            . '<p>' . self::escape($publicMessage) . '</p><dl>'
            . '<dt>Location</dt><dd><code>' . self::escape($relativeFile . ':' . $line) . '</code></dd>'
            . '<dt>Request ID</dt><dd><code>' . self::escape($requestId) . '</code></dd>'
            . '</dl></main></body></html>';
    }

    private static function sanitizeMessage(string $message): string
    {
        $message = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]+/u', ' ', $message) ?? $message;
        $message = trim(preg_replace('/\s+/u', ' ', $message) ?? $message);
        if (self::$rootDirectory !== '') {
            $message = str_ireplace(
                [self::$rootDirectory, str_replace('\\', '/', self::$rootDirectory)],
                '[project]',
                $message,
            );
        }
        $message = preg_replace(
            '#([a-z][a-z0-9+.-]*://)([^/@\s:]+):([^@\s]+)@#i',
            '$1[credentials-redacted]@',
            $message,
        ) ?? $message;
        $message = preg_replace(
            '/\b(password|passwd|pwd|secret|token|authorization|cookie|session|csrf)\s*[:=]\s*([^\s;,&]+)/i',
            '$1=[redacted]',
            $message,
        ) ?? $message;

        if ($message === '') {
            return 'Fatal bootstrap error without a diagnostic message.';
        }

        return function_exists('mb_substr')
            ? mb_substr($message, 0, 2000)
            : substr($message, 0, 2000);
    }

    private static function expectsJson(): bool
    {
        $accept = strtolower((string)($_SERVER['HTTP_ACCEPT'] ?? ''));
        $requestedWith = strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''));
        $uri = strtolower((string)($_SERVER['REQUEST_URI'] ?? ''));

        return str_contains($accept, 'application/json')
            || $requestedWith === 'xmlhttprequest'
            || str_contains($uri, '/api/');
    }

    private static function relativePath(string $file): string
    {
        if (self::$rootDirectory !== '' && str_starts_with($file, self::$rootDirectory)) {
            return ltrim(substr($file, strlen(self::$rootDirectory)), '/\\');
        }
        return $file;
    }

    private static function requestId(): string
    {
        try {
            return bin2hex(random_bytes(8));
        } catch (Throwable) {
            return str_replace('.', '', uniqid('fox-bootstrap-', true));
        }
    }

    private static function errorTypeName(int $type): string
    {
        return match ($type) {
            E_PARSE => 'ParseError',
            E_CORE_ERROR => 'CoreError',
            E_COMPILE_ERROR => 'CompileError',
            default => 'FatalError',
        };
    }

    private static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
