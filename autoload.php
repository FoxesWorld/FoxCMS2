<?php

declare(strict_types=1);

/**
 * Repository-wide PSR-4 loader used when Composer's generated autoloader is
 * unavailable. Legacy global engine classes remain delegated to
 * engine/autoload.php.
 */
spl_autoload_register(static function (string $class): void {
    static $prefixes = [
        'FoxCMS\\Shared\\' => __DIR__ . '/src/FoxCMS/Shared/',
        'FoxCMS\\Api\\' => __DIR__ . '/api/src/FoxCMS/Api/',
        'FoxCMS\\Engine\\' => __DIR__ . '/engine/src/FoxCMS/Engine/',
    ];

    foreach ($prefixes as $prefix => $directory) {
        if (!str_starts_with($class, $prefix)) {
            continue;
        }
        $relative = substr($class, strlen($prefix));
        if ($relative === false || $relative === '') {
            return;
        }
        $file = $directory . str_replace('\\', '/', $relative) . '.php';
        if (is_file($file)) {
            require_once $file;
        }
        return;
    }
});
