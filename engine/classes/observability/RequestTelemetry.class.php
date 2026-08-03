<?php

declare(strict_types=1);

final class RequestTelemetry
{
    private const DEFAULT_SLOW_REQUEST_MS = 2_000;
    private const DEFAULT_CRITICAL_REQUEST_MS = 5_000;
    private const DEFAULT_MEMORY_WARNING_BYTES = 67_108_864;
    private const FATAL_ERROR_TYPES = [
        E_ERROR,
        E_PARSE,
        E_CORE_ERROR,
        E_COMPILE_ERROR,
        E_USER_ERROR,
        E_RECOVERABLE_ERROR,
    ];

    private static ?Logger $logger = null;
    private static ?TraceContext $trace = null;
    private static bool $completed = false;
    private static bool $shutdownRegistered = false;
    /** @var array<string, mixed> */
    private static array $attributes = [];
    private static int $slowRequestMilliseconds = self::DEFAULT_SLOW_REQUEST_MS;
    private static int $criticalRequestMilliseconds = self::DEFAULT_CRITICAL_REQUEST_MS;
    private static int $memoryWarningBytes = self::DEFAULT_MEMORY_WARNING_BYTES;

    public static function bootstrap(
        Logger $logger,
        HttpRequest $request,
        UserSession $session,
        array $configuration = [],
    ): void {
        self::$logger = $logger;
        self::$trace = TraceContext::create(
            $request,
            $session,
            (string)($configuration['fingerprintKey'] ?? ''),
        );
        self::$completed = false;
        $requestContext = $request->telemetryContext();
        self::$attributes = array_merge($requestContext, [
            'operation' => self::normalizeOperation($request->telemetryOperation()),
            'component' => (string)($requestContext['requestChannel'] ?? 'http'),
        ]);
        self::$slowRequestMilliseconds = max(
            100,
            (int)($configuration['slowRequestMilliseconds'] ?? self::DEFAULT_SLOW_REQUEST_MS),
        );
        self::$criticalRequestMilliseconds = max(
            self::$slowRequestMilliseconds,
            (int)($configuration['criticalRequestMilliseconds'] ?? self::DEFAULT_CRITICAL_REQUEST_MS),
        );
        self::$memoryWarningBytes = max(
            8_388_608,
            (int)($configuration['memoryWarningBytes'] ?? self::DEFAULT_MEMORY_WARNING_BYTES),
        );

        Logger::setGlobalContext(self::$trace->logContext());
        Logger::mergeGlobalContext(self::$attributes);
        if (!self::$shutdownRegistered) {
            register_shutdown_function([self::class, 'handleShutdown']);
            self::$shutdownRegistered = true;
        }

    }

    public static function identify(string $operation, array $attributes = []): void
    {
        $operation = self::normalizeOperation($operation);
        if ($operation !== '') {
            self::$attributes['operation'] = $operation;
        }
        self::annotate($attributes);
    }

    public static function annotate(array $attributes): void
    {
        foreach ($attributes as $key => $value) {
            if (!is_string($key) || preg_match('/^[A-Za-z][A-Za-z0-9_.-]{0,63}$/D', $key) !== 1) {
                continue;
            }
            if (is_scalar($value) || $value === null || is_array($value)) {
                self::$attributes[$key] = $value;
            }
        }
        Logger::mergeGlobalContext(self::$attributes);
    }

    public static function event(
        string $event,
        string $message,
        array $context = [],
        string $level = 'INFO',
        string $outcome = 'observed',
    ): void {
        self::$logger?->event(
            $event,
            $message,
            array_merge(self::$attributes, $context),
            $level,
            $outcome,
        );
    }

    public static function deviation(
        string $event,
        string $code,
        string $message,
        string $severity = 'warning',
        array $expected = [],
        array $actual = [],
        array $context = [],
    ): void {
        self::$logger?->deviation(
            $event,
            $code,
            $message,
            $severity,
            $expected,
            $actual,
            array_merge(self::$attributes, $context),
        );
    }

    public static function failure(
        string $event,
        Throwable $error,
        string $message,
        array $context = [],
    ): void {
        self::$logger?->exception(
            $event,
            $error,
            $message,
            array_merge(self::$attributes, $context),
        );
    }

    public static function rejectHttp(
        string $event,
        int $status,
        string $message,
        array $context = [],
    ): void {
        $status = $status >= 400 && $status <= 599 ? $status : 500;
        $code = match ($status) {
            400, 405, 409, 413, 415, 422 => 'invalid_request',
            401 => 'authentication_required',
            403 => 'access_denied',
            404 => 'resource_not_found',
            408 => 'request_timeout',
            429 => 'rate_limit_exceeded',
            default => $status >= 500 ? 'internal_failure' : 'http_rejection',
        };
        self::deviation(
            $event,
            $code,
            $message,
            self::statusSeverity($status),
            ['httpStatusRange' => '100-399'],
            ['httpStatus' => $status],
            $context,
        );
    }

    public static function complete(int $status, array $context = []): void
    {
        if (self::$completed || self::$logger === null || self::$trace === null) {
            return;
        }
        self::$completed = true;

        $status = $status >= 100 && $status <= 599 ? $status : 500;
        $duration = self::$trace->durationMilliseconds();
        $memory = memory_get_peak_usage(true);
        $completion = array_merge(self::$attributes, $context, [
            'component' => 'http',
            'operation' => self::$attributes['operation'] ?? 'request',
            'httpStatus' => $status,
            'durationMs' => $duration,
            'peakMemoryBytes' => $memory,
            'outcome' => $status < 400 ? 'success' : 'rejected',
        ]);

        if ($duration >= self::$slowRequestMilliseconds) {
            self::$logger->deviation(
                'http.request.slow',
                'request_duration_exceeded',
                'Request duration exceeded the normal threshold.',
                $duration >= self::$criticalRequestMilliseconds ? 'critical' : 'warning',
                ['maximumDurationMs' => self::$slowRequestMilliseconds],
                ['durationMs' => $duration],
                $completion,
            );
        }
        if ($memory >= self::$memoryWarningBytes) {
            self::$logger->deviation(
                'http.request.memory_high',
                'request_memory_exceeded',
                'Request peak memory exceeded the normal threshold.',
                'warning',
                ['maximumPeakMemoryBytes' => self::$memoryWarningBytes],
                ['peakMemoryBytes' => $memory],
                $completion,
            );
        }
        if ($status >= 400) {
            self::$logger->deviation(
                'http.request.status_deviation',
                'http_status_outside_success_range',
                'HTTP request completed outside the expected success range.',
                self::statusSeverity($status),
                ['statusRange' => '100-399'],
                ['httpStatus' => $status],
                $completion,
            );
        }

        self::$logger->event(
            'http.request.completed',
            self::requestMessage($status, $duration),
            $completion,
            $status >= 500 ? 'ERROR' : ($status >= 400 ? 'WARNING' : 'INFO'),
            $status < 400 ? 'success' : 'rejected',
        );
    }

    public static function requestId(): string
    {
        return self::$trace?->requestId() ?? '';
    }

    public static function correlationId(): string
    {
        return self::$trace?->correlationId() ?? '';
    }

    public static function handleShutdown(): void
    {
        if (self::$completed || self::$logger === null) {
            return;
        }

        $error = error_get_last();
        if (is_array($error) && in_array((int)($error['type'] ?? 0), self::FATAL_ERROR_TYPES, true)) {
            self::$logger->deviation(
                'runtime.fatal_error',
                'unhandled_fatal_error',
                'Request terminated by an unhandled fatal runtime error.',
                'critical',
                ['completion' => 'controlled'],
                [
                    'errorType' => (int)($error['type'] ?? 0),
                    'errorMessage' => (string)($error['message'] ?? ''),
                    'errorFile' => (string)($error['file'] ?? ''),
                    'errorLine' => (int)($error['line'] ?? 0),
                ],
                self::$attributes,
            );
            self::complete(500, ['completionMode' => 'shutdown_fatal']);
            return;
        }

        $status = http_response_code();
        self::complete(
            is_int($status) && $status >= 100 ? $status : 200,
            ['completionMode' => 'shutdown'],
        );
    }

    private static function requestMessage(?int $status = null, ?float $duration = null): string
    {
        $trace = self::$trace?->logContext() ?? [];
        $method = (string)($trace['httpMethod'] ?? 'HTTP');
        $path = (string)($trace['httpPath'] ?? '/');
        $operation = (string)(self::$attributes['operation'] ?? 'request');
        return sprintf(
            '%s %s [%s] completed with HTTP %d in %.3f ms.',
            $method,
            $path,
            $operation,
            $status ?? 500,
            $duration ?? 0.0,
        );
    }

    private static function statusSeverity(int $status): string
    {
        if ($status >= 500) {
            return 'critical';
        }
        if (in_array($status, [401, 403, 408, 429], true)) {
            return 'warning';
        }
        return 'notice';
    }

    private static function normalizeOperation(string $operation): string
    {
        $operation = strtolower(trim($operation));
        $operation = preg_replace('/[^a-z0-9_.-]+/', '.', $operation) ?? '';
        return trim(substr($operation, 0, 96), '.-');
    }
}
