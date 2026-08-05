<?php

declare(strict_types=1);

/** Lightweight PSR-4 loader for the standalone API runtime. */
spl_autoload_register(static function (string $class): void {
    $prefix = 'FoxCMS\\Api\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    if ($relative === false || $relative === '') {
        return;
    }

    $file = __DIR__ . '/src/FoxCMS/Api/' . str_replace('\\', '/', $relative) . '.php';
    if (is_file($file)) {
        require_once $file;
    }
});
