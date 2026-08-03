<?php

declare(strict_types=1);

$rootDirectory = dirname(__DIR__, 2);
require_once $rootDirectory . '/engine/data/environment.php';
foxLoadEnv($rootDirectory . '/.env');

return [
    'storage_directory' => (string)(
        foxEnv('FOXESCRAFT_BOOTSTRAP_STORAGE_DIRECTORY')
        ?? ($rootDirectory . '/uploads/bootstrap')
    ),
    'cache_max_age' => max(0, foxEnvInt('FOXESCRAFT_BOOTSTRAP_CACHE_MAX_AGE', 60)),
    'launcher_file_name' => 'launcher.jar',
    'launcher_jvm_args' => [
        '-Xms256m',
        '-Xmx2g',
        '-Dfile.encoding=UTF-8',
        '-Djava.net.preferIPv4Stack=true',
    ],
    'launcher_args' => [],
    'hardware_inventory' => [
        'enabled' => foxEnvBool('FOXESCRAFT_HARDWARE_INVENTORY_ENABLED', true),
        'max_payload_bytes' => max(4096, min(131072, foxEnvInt('FOXESCRAFT_HARDWARE_INVENTORY_MAX_BYTES', 32768))),
    ],
    'database' => [
        'host' => foxEnv('FOXESCRAFT_DB_HOST', '127.0.0.1') ?? '127.0.0.1',
        'port' => max(1, min(65535, foxEnvInt('FOXESCRAFT_DB_PORT', 3306))),
        'name' => foxEnv('FOXESCRAFT_DB_NAME', 'foxescraft') ?? 'foxescraft',
        'user' => foxEnv('FOXESCRAFT_DB_USER', 'foxescraft') ?? 'foxescraft',
        'password' => foxEnv('FOXESCRAFT_DB_PASSWORD', '') ?? '',
        'connect_timeout' => max(1, min(30, foxEnvInt('FOXESCRAFT_DB_CONNECT_TIMEOUT', 5))),
    ],
];
