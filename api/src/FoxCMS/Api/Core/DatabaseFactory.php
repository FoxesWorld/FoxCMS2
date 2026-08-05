<?php

declare(strict_types=1);

namespace FoxCMS\Api\Core;

final class DatabaseFactory
{
    /** @param array<string, mixed> $config */
    public static function create(array $config): \db
    {
        $database = is_array($config['database'] ?? null) ? $config['database'] : [];
        return new \db(
            (string)($database['dbUser'] ?? ''),
            (string)($database['dbPass'] ?? ''),
            (string)($database['dbName'] ?? ''),
            (string)($database['dbHost'] ?? '127.0.0.1'),
            (int)($database['dbPort'] ?? 3306),
            (string)($database['dbCharset'] ?? 'utf8mb4'),
            (int)($database['connectTimeout'] ?? 5),
        );
    }
}
