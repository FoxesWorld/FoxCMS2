<?php

declare(strict_types=1);

final class RuntimeErrorHandler
{
    private static string $rootDirectory = '';
    private static string $logFile = '';
    private static bool $debug = false;
    private static bool $handlingFailure = false;
    private static string $requestId = '';

    public static function register(string $rootDirectory, bool $debug = false): void
    {
        self::$rootDirectory = rtrim($rootDirectory, '/\\');
        self::$logFile = self::$rootDirectory
            . DIRECTORY_SEPARATOR . 'engine'
            . DIRECTORY_SEPARATOR . 'cache'
            . DIRECTORY_SEPARATOR . 'logs'
            . DIRECTORY_SEPARATOR . 'runtime.log';
        self::$requestId = self::createRequestId();
        self::setDebug($debug);
        $GLOBALS['foxRuntimeErrorHandlerRegistered'] = true;

        ini_set('log_errors', '1');
        error_reporting(E_ALL);

        set_error_handler([self::class, 'handleError']);
        set_exception_handler([self::class, 'handleException']);
        register_shutdown_function([self::class, 'handleShutdown']);
    }

    public static function setDebug(bool $debug): void
    {
        self::$debug = $debug;
        ini_set('display_errors', $debug ? '1' : '0');
        ini_set('display_startup_errors', $debug ? '1' : '0');
    }

    public static function adoptRequestId(string $requestId): void
    {
        $requestId = trim($requestId);
        if (preg_match('/^[A-Za-z0-9_.:-]{8,96}$/D', $requestId) === 1) {
            self::$requestId = $requestId;
        }
    }

    public static function requestId(): string
    {
        return self::$requestId;
    }

    public static function handleError(
        int $severity,
        string $message,
        string $file,
        int $line,
    ): bool {
        if ((error_reporting() & $severity) === 0) {
            return false;
        }

        self::writeLog('PHP error', $message, $file, $line, null, $severity);
        if (class_exists(RequestTelemetry::class, false)
            && in_array($severity, [E_WARNING, E_USER_WARNING, E_CORE_WARNING, E_COMPILE_WARNING], true)) {
            RequestTelemetry::deviation(
                'runtime.php_warning',
                'php_runtime_warning',
                'PHP emitted a runtime warning.',
                'warning',
                ['warningEmitted' => false],
                [
                    'warningEmitted' => true,
                    'severity' => $severity,
                    'message' => $message,
                    'file' => self::relativePath($file),
                    'line' => $line,
                ],
                ['component' => 'runtime'],
            );
        }

        if (in_array($severity, [E_USER_ERROR, E_RECOVERABLE_ERROR], true)) {
            throw new ErrorException($message, 0, $severity, $file, $line);
        }

        return true;
    }

    public static function handleException(Throwable $throwable): void
    {
        if (self::$handlingFailure) {
            self::fallbackLog($throwable);
            return;
        }

        self::$handlingFailure = true;
        if (class_exists(RequestTelemetry::class, false)) {
            $telemetryRequestId = RequestTelemetry::requestId();
            if ($telemetryRequestId !== '') {
                self::adoptRequestId($telemetryRequestId);
            }
            RequestTelemetry::failure(
                'runtime.unhandled_exception',
                $throwable,
                'Request terminated by an unhandled exception.',
                ['component' => 'runtime'],
            );
            RequestTelemetry::complete(500, ['completionMode' => 'exception_handler']);
        }
        self::writeLog(
            get_class($throwable),
            $throwable->getMessage(),
            $throwable->getFile(),
            $throwable->getLine(),
            $throwable->getTraceAsString(),
            $throwable->getCode(),
        );

        self::emitThrowableResponse($throwable);
    }

    public static function handleShutdown(): void
    {
        $error = error_get_last();
        if ($error === null) {
            return;
        }

        if (!in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
            return;
        }

        if (self::$handlingFailure) {
            return;
        }

        self::$handlingFailure = true;
        if (class_exists(RequestTelemetry::class, false)) {
            $telemetryRequestId = RequestTelemetry::requestId();
            if ($telemetryRequestId !== '') {
                self::adoptRequestId($telemetryRequestId);
            }
            RequestTelemetry::deviation(
                'runtime.fatal_error',
                'unhandled_fatal_error',
                'Request terminated by an unhandled fatal runtime error.',
                'critical',
                ['completion' => 'controlled'],
                [
                    'errorType' => (int)$error['type'],
                    'errorMessage' => (string)$error['message'],
                    'errorFile' => self::relativePath((string)$error['file']),
                    'errorLine' => (int)$error['line'],
                ],
                ['component' => 'runtime'],
            );
            RequestTelemetry::complete(500, ['completionMode' => 'runtime_shutdown']);
        }
        self::writeLog(
            'Fatal shutdown error',
            (string)$error['message'],
            (string)$error['file'],
            (int)$error['line'],
            null,
            (int)$error['type'],
        );

        self::emitThrowableResponse(new ErrorException(
            (string)$error['message'],
            0,
            (int)$error['type'],
            (string)$error['file'],
            (int)$error['line'],
        ));
    }

    private static function emitThrowableResponse(Throwable $throwable): void
    {
        self::discardOutputBuffers();

        if (!headers_sent()) {
            http_response_code(500);
            header('Cache-Control: no-store');
            header('X-Content-Type-Options: nosniff');
            header('X-FoxCMS-Request-Id: ' . self::$requestId);
            header('X-Request-ID: ' . self::$requestId);
            if (class_exists(RequestTelemetry::class, false) && RequestTelemetry::correlationId() !== '') {
                header('X-Correlation-ID: ' . RequestTelemetry::correlationId());
            }
        }

        if (self::expectsJson()) {
            if (!headers_sent()) {
                header('Content-Type: application/json; charset=UTF-8');
            }

            $payload = [
                'type' => 'error',
                'fatal' => true,
                'exception' => get_class($throwable),
                'message' => self::publicMessage($throwable->getMessage()),
                'requestId' => self::$requestId,
                'correlationId' => class_exists(RequestTelemetry::class, false)
                    ? RequestTelemetry::correlationId()
                    : self::$requestId,
            ];

            if (self::$debug) {
                $payload['file'] = self::relativePath($throwable->getFile());
                $payload['line'] = $throwable->getLine();
                $payload['trace'] = $throwable->getTraceAsString();
            }

            $json = json_encode(
                $payload,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE,
            );
            echo is_string($json)
                ? $json
                : '{"type":"error","fatal":true,"message":"Unable to encode fatal error response."}';
            return;
        }

        if (!headers_sent()) {
            header('Content-Type: text/html; charset=UTF-8');
        }

        echo self::renderHtml($throwable);
    }

    private static function discardOutputBuffers(): void
    {
        while (ob_get_level() > 0) {
            if (!@ob_end_clean()) {
                break;
            }
        }
    }

    private static function publicMessage(string $message): string
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
            '/\b(password|passwd|pwd|secret|token|authorization)\s*[:=]\s*([^\s;,&]+)/i',
            '$1=[redacted]',
            $message,
        ) ?? $message;

        if ($message === '') {
            return 'Fatal error without a diagnostic message.';
        }

        return function_exists('mb_substr')
            ? mb_substr($message, 0, 2000)
            : substr($message, 0, 2000);
    }

    private static function writeLog(
        string $type,
        string $message,
        string $file,
        int $line,
        ?string $trace,
        int|string $code,
    ): void {
        $context = [
            'timestamp' => gmdate('c'),
            'requestId' => self::$requestId,
            'type' => $type,
            'code' => $code,
            'message' => $message,
            'file' => self::relativePath($file),
            'line' => $line,
            'method' => (string)($_SERVER['REQUEST_METHOD'] ?? 'CLI'),
            'uri' => self::sanitizeUri((string)($_SERVER['REQUEST_URI'] ?? '')),
        ];

        if ($trace !== null && $trace !== '') {
            $context['trace'] = $trace;
        }

        $lineValue = json_encode(
            $context,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE,
        );
        if (!is_string($lineValue)) {
            $lineValue = gmdate('c') . ' [' . self::$requestId . '] ' . $type . ': ' . $message;
        }
        $lineValue .= PHP_EOL;

        $directory = dirname(self::$logFile);
        if ((is_dir($directory) || @mkdir($directory, 0775, true)) && is_writable($directory)) {
            if (@file_put_contents(self::$logFile, $lineValue, FILE_APPEND | LOCK_EX) !== false) {
                return;
            }
        }

        error_log(rtrim($lineValue));
    }

    private static function fallbackLog(Throwable $throwable): void
    {
        error_log(
            '[FoxCMS][' . self::$requestId . '] '
            . get_class($throwable) . ': ' . $throwable->getMessage()
            . ' in ' . $throwable->getFile() . ':' . $throwable->getLine()
        );
    }

    private static function renderHtml(Throwable $throwable): string
    {
        $title = get_class($throwable);
        $message = self::publicMessage($throwable->getMessage());
        $details = '';

        if (self::$debug) {
            $location = self::relativePath($throwable->getFile()) . ':' . $throwable->getLine();
            $details = '<details class="runtime-error__details" open>'
                . '<summary>Технические детали</summary><dl>'
                . '<dt>Расположение</dt><dd><code>' . self::escape($location) . '</code></dd>'
                . '<dt>Стек вызовов</dt><dd><pre>' . self::escape($throwable->getTraceAsString()) . '</pre></dd>'
                . '</dl></details>';
        }

        $stylesheet = self::errorStylesheet();
        $season = self::currentSeason();

        return '<!doctype html><html lang="ru"><head><meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width,initial-scale=1">'
            . '<meta name="robots" content="noindex,nofollow">'
            . '<meta name="theme-color" content="#152019">'
            . '<link rel="icon" type="image/png" href="/templates/foxengine2/assets/img/logo.png">'
            . '<title>FoxCMS · HTTP 500</title>'
            . '<style>html{color-scheme:dark}body{margin:0;min-height:100vh;display:grid;place-items:center;padding:24px;box-sizing:border-box;background:#090d0b;color:#eef4f0;font-family:system-ui,sans-serif}main{width:min(920px,100%)}</style>'
            . $stylesheet
            . '</head><body data-season="' . self::escape($season) . '">'
            . '<main class="runtime-error-shell">'
            . '<section class="runtime-error-card" aria-labelledby="runtime-error-title">'
            . '<header class="runtime-error-card__header">'
            . '<div class="runtime-error-brand"><img class="runtime-error-brand__logo"'
                . ' src="/templates/foxengine2/assets/img/logo.png" alt="" width="72" height="54">'
            . '<span><strong>FoxCMS</strong><small>Runtime protection layer</small></span></div>'
            . '<span class="runtime-error-status"><i aria-hidden="true"></i>HTTP 500</span>'
            . '</header>'
            . '<div class="runtime-error-code" aria-hidden="true">500</div>'
            . '<p class="runtime-error-eyebrow">Системная ошибка</p>'
            . '<h1 id="runtime-error-title">' . self::escape($title) . '</h1>'
            . '<p class="runtime-error-message">' . self::escape($message) . '</p>'
            . '<div class="runtime-error-request"><span>Request ID</span><code>'
            . self::escape(self::$requestId) . '</code></div>'
            . $details
            . '<nav class="runtime-error-actions" aria-label="Действия страницы ошибки">'
            . '<a class="runtime-error-button runtime-error-button--primary" href="">Повторить запрос</a>'
            . '<a class="runtime-error-button" href="/">На главную</a>'
            . '</nav>'
            . '<footer>Укажите Request ID при обращении к администратору.</footer>'
            . '</section></main></body></html>';
    }

    private static function errorStylesheet(): string
    {
        $relative = 'templates' . DIRECTORY_SEPARATOR . 'foxengine2'
            . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'css'
            . DIRECTORY_SEPARATOR . 'error.css';
        $file = self::$rootDirectory . DIRECTORY_SEPARATOR . $relative;

        if (!is_file($file) || !is_readable($file)) {
            return '';
        }

        return '<link rel="stylesheet" href="/templates/foxengine2/assets/css/error.css">';
    }

    private static function currentSeason(): string
    {
        $month = (int)date('n');
        if ($month >= 3 && $month <= 5) {
            return 'spring';
        }
        if ($month >= 6 && $month <= 8) {
            return 'summer';
        }
        if ($month >= 9 && $month <= 11) {
            return 'autumn';
        }
        return 'winter';
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

    private static function createRequestId(): string
    {
        try {
            return bin2hex(random_bytes(8));
        } catch (Throwable) {
            return str_replace('.', '', uniqid('fox', true));
        }
    }

    private static function relativePath(string $file): string
    {
        if (self::$rootDirectory !== '' && str_starts_with($file, self::$rootDirectory)) {
            return ltrim(substr($file, strlen(self::$rootDirectory)), '/\\');
        }

        return $file;
    }

    private static function sanitizeUri(string $uri): string
    {
        $questionMark = strpos($uri, '?');
        return $questionMark === false ? $uri : substr($uri, 0, $questionMark);
    }

    private static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
