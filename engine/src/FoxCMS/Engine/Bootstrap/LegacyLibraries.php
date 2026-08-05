<?php

declare(strict_types=1);

namespace FoxCMS\Engine\Bootstrap;

final class LegacyLibraries
{
    private const FILES = [
        'antiBrute',
        'auth',
        'database.php',
        'date',
        'file',
        'filesInDir',
        'functions',
        'getPerms',
        'randTexts',
        'syslog',
    ];

    public static function load(string $directory): void
    {
        foreach (self::FILES as $library) {
            $path = rtrim($directory, '/\\') . DIRECTORY_SEPARATOR . $library;
            if (!is_file($path)) {
                throw new \RuntimeException('Required engine library is missing: ' . $path);
            }
            require_once $path;
        }
    }
}
