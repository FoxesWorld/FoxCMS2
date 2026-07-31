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
];
