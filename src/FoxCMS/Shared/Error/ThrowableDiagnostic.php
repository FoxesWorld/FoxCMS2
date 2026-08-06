<?php

declare(strict_types=1);

namespace FoxCMS\Shared\Error;

use Throwable;

/** Produces a client-visible diagnostic without leaking credentials or absolute project paths. */
final class ThrowableDiagnostic
{
    /**
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    public static function payload(
        Throwable $error,
        string $requestId,
        string $rootDirectory = '',
        bool $debug = false,
        array $context = [],
    ): array {
        $payload = [
            ...$context,
            'fatal' => true,
            'exception' => $error::class,
            'message' => self::sanitizeMessage($error->getMessage(), $rootDirectory),
            'requestId' => trim($requestId) !== '' ? trim($requestId) : 'unavailable',
        ];

        if ($debug) {
            $payload['file'] = self::relativePath($error->getFile(), $rootDirectory);
            $payload['line'] = $error->getLine();
            $payload['trace'] = $error->getTraceAsString();
        }

        return $payload;
    }

    public static function sanitizeMessage(string $message, string $rootDirectory = ''): string
    {
        $message = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]+/u', ' ', $message) ?? $message;
        $message = trim(preg_replace('/\s+/u', ' ', $message) ?? $message);
        $rootDirectory = rtrim($rootDirectory, '/\\');
        if ($rootDirectory !== '') {
            $message = str_ireplace(
                [$rootDirectory, str_replace('\\', '/', $rootDirectory)],
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
            return 'Fatal error without a diagnostic message.';
        }

        return function_exists('mb_substr')
            ? mb_substr($message, 0, 2000)
            : substr($message, 0, 2000);
    }

    private static function relativePath(string $file, string $rootDirectory): string
    {
        $rootDirectory = rtrim($rootDirectory, '/\\');
        if ($rootDirectory !== '' && str_starts_with($file, $rootDirectory)) {
            return ltrim(substr($file, strlen($rootDirectory)), '/\\');
        }

        return $file;
    }
}
