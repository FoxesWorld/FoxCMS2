<?php

declare(strict_types=1);

namespace FoxCMS\Shared\Environment;

use RuntimeException;

/**
 * Process environment boundary shared by the web engine, standalone API and
 * deployment scripts. The current() singleton exists only for legacy wrapper
 * functions; new code should receive Environment explicitly.
 */
final class Environment
{
    private static ?self $current = null;

    /** @var array<string, true> */
    private static array $loadedFiles = [];

    private function __construct(private readonly string $rootDirectory)
    {
    }

    public static function boot(string $rootDirectory): self
    {
        $rootDirectory = rtrim($rootDirectory, "/\\");
        if ($rootDirectory === '' || !is_dir($rootDirectory)) {
            throw new RuntimeException('Application root directory is invalid.');
        }

        $environment = new self($rootDirectory);
        $environment->loadFile($rootDirectory . DIRECTORY_SEPARATOR . '.env');
        self::$current = $environment;
        return $environment;
    }

    public static function current(): self
    {
        if (!self::$current instanceof self) {
            throw new RuntimeException('Process environment has not been bootstrapped.');
        }
        return self::$current;
    }

    public function rootDirectory(): string
    {
        return $this->rootDirectory;
    }

    public function loadFile(string $path): void
    {
        $resolvedPath = realpath($path);
        $cacheKey = $resolvedPath !== false ? $resolvedPath : $path;
        if (isset(self::$loadedFiles[$cacheKey])) {
            return;
        }
        self::$loadedFiles[$cacheKey] = true;

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
                $line = ltrim($line, "ï»¿");
            }
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            if (str_starts_with($line, 'export ')) {
                $line = ltrim(substr($line, 7));
            }

            $separator = strpos($line, '=');
            if ($separator === false) {
                throw new RuntimeException(sprintf('Invalid .env declaration at %s:%d', $path, $lineNumber + 1));
            }
            $name = trim(substr($line, 0, $separator));
            if (preg_match('/^[A-Z_][A-Z0-9_]*$/iD', $name) !== 1) {
                throw new RuntimeException(sprintf('Invalid environment variable name at %s:%d', $path, $lineNumber + 1));
            }
            if ($this->exists($name)) {
                continue;
            }

            $value = $this->parseValue(substr($line, $separator + 1));
            putenv($name . '=' . $value);
            $_ENV[$name] = $value;
        }
    }

    public function string(string $name, ?string $default = null): ?string
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

    public function boolean(string $name, bool $default = false): bool
    {
        $value = $this->string($name);
        if ($value === null) {
            return $default;
        }
        $parsed = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        return $parsed ?? $default;
    }

    public function integer(string $name, int $default, ?int $minimum = null, ?int $maximum = null): int
    {
        $value = $this->string($name);
        $parsed = $value !== null ? filter_var($value, FILTER_VALIDATE_INT) : false;
        $result = $parsed === false ? $default : (int)$parsed;
        if ($minimum !== null) {
            $result = max($minimum, $result);
        }
        if ($maximum !== null) {
            $result = min($maximum, $result);
        }
        return $result;
    }

    /** @return list<string> */
    public function csv(string $name): array
    {
        $value = $this->string($name, '') ?? '';
        $entries = array_map('trim', explode(',', $value));
        return array_values(array_unique(array_filter(
            $entries,
            static fn (string $entry): bool => $entry !== '',
        )));
    }

    private function exists(string $name): bool
    {
        return getenv($name) !== false
            || array_key_exists($name, $_ENV)
            || array_key_exists($name, $_SERVER);
    }

    private function parseValue(string $rawValue): string
    {
        $value = trim($rawValue);
        $length = strlen($value);
        if ($length >= 2 && $value[0] === '"' && $value[$length - 1] === '"') {
            return stripcslashes(substr($value, 1, -1));
        }
        if ($length >= 2 && $value[0] === "'" && $value[$length - 1] === "'") {
            return substr($value, 1, -1);
        }
        $value = preg_replace('/\s+#.*$/', '', $value) ?? $value;
        return trim($value);
    }
}
