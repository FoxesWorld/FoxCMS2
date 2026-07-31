<?php

declare(strict_types=1);

final class UtilityLoader
{
    private static array $loaded = [];

    public static function load(string $name, string $version): void
    {
        if (!preg_match('/^[A-Za-z][A-Za-z0-9_]*$/', $name) || !preg_match('/^\d+\.\d+\.\d+$/', $version)) {
            throw new InvalidArgumentException('Invalid utility identifier.');
        }

        $key = $name . '@' . $version;
        if (isset(self::$loaded[$key])) {
            return;
        }

        $path = UTILS_DIR . $name . DIRECTORY_SEPARATOR . $version . DIRECTORY_SEPARATOR . $name . '.class.php';
        if (!is_file($path)) {
            throw new RuntimeException('Utility not found: ' . $key);
        }

        require_once $path;
        self::$loaded[$key] = true;
    }
}
