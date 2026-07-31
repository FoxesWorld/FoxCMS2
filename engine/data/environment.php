<?php

declare(strict_types=1);

/**
 * Load a local .env file without overriding variables provided by the process,
 * PHP-FPM pool, web server or container runtime.
 */
function foxLoadEnv(string $path): void
{
    static $loadedPaths = [];

    $resolvedPath = realpath($path);
    $cacheKey = $resolvedPath !== false ? $resolvedPath : $path;
    if (isset($loadedPaths[$cacheKey])) {
        return;
    }
    $loadedPaths[$cacheKey] = true;

    if (!is_file($path) || !is_readable($path)) {
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES);
    if ($lines === false) {
        throw new RuntimeException('Unable to read environment file: ' . $path);
    }

    foreach ($lines as $lineNumber => $line) {
        $line = trim((string)$line);
        if ($lineNumber === 0) {
            $line = ltrim($line, "\xEF\xBB\xBF");
        }

        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }

        if (str_starts_with($line, 'export ')) {
            $line = ltrim(substr($line, 7));
        }

        $separator = strpos($line, '=');
        if ($separator === false) {
            throw new RuntimeException(
                sprintf('Invalid .env declaration at %s:%d', $path, $lineNumber + 1)
            );
        }

        $name = trim(substr($line, 0, $separator));
        if (preg_match('/^[A-Z_][A-Z0-9_]*$/i', $name) !== 1) {
            throw new RuntimeException(
                sprintf('Invalid environment variable name at %s:%d', $path, $lineNumber + 1)
            );
        }

        if (getenv($name) !== false || array_key_exists($name, $_ENV) || array_key_exists($name, $_SERVER)) {
            continue;
        }

        $value = trim(substr($line, $separator + 1));
        $length = strlen($value);
        if ($length >= 2 && $value[0] === '"' && $value[$length - 1] === '"') {
            $value = stripcslashes(substr($value, 1, -1));
        } elseif ($length >= 2 && $value[0] === "'" && $value[$length - 1] === "'") {
            $value = substr($value, 1, -1);
        } else {
            $value = preg_replace('/\s+#.*$/', '', $value) ?? $value;
            $value = trim($value);
        }

        putenv($name . '=' . $value);
        $_ENV[$name] = $value;
    }
}

function foxEnv(string $name, ?string $default = null): ?string
{
    $value = getenv($name);
    if ($value === false && array_key_exists($name, $_ENV)) {
        $value = $_ENV[$name];
    }
    if ($value === false && array_key_exists($name, $_SERVER)) {
        $value = $_SERVER[$name];
    }
    if ($value === false || $value === null || $value === '') {
        return $default;
    }
    return (string)$value;
}

function foxEnvBool(string $name, bool $default = false): bool
{
    $value = foxEnv($name);
    if ($value === null) {
        return $default;
    }
    $parsed = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    return $parsed ?? $default;
}

function foxEnvInt(string $name, int $default): int
{
    $value = foxEnv($name);
    if ($value === null || filter_var($value, FILTER_VALIDATE_INT) === false) {
        return $default;
    }
    return (int)$value;
}
