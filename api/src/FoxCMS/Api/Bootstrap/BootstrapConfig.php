<?php

declare(strict_types=1);

namespace FoxCMS\Api\Bootstrap;

use FoxCMS\Shared\Environment\Environment;

final class BootstrapConfig
{
    /** @return array<string, mixed> */
    public static function load(string $rootDirectory): array
    {
        $environment = Environment::boot($rootDirectory);
        $rootDirectory = $environment->rootDirectory();
        $corsOrigins = $environment->csv('FOXESCRAFT_BOOTSTRAP_CORS_ORIGINS');
        $publicOrigin = BootstrapCorsPolicy::normalizeOrigin(
            $environment->string('FOXESCRAFT_PUBLIC_BASE_URL', '') ?? '',
        );
        if ($publicOrigin !== '') {
            $corsOrigins[] = $publicOrigin;
        }
        $corsOrigins = array_values(array_unique($corsOrigins));
        return [
            'root_directory' => $rootDirectory,
            'debug' => $environment->boolean('FOXESCRAFT_DEBUG', false),
            'storage_directory' => (string)(
                $environment->string('FOXESCRAFT_BOOTSTRAP_STORAGE_DIRECTORY')
                ?? ($rootDirectory . '/uploads/bootstrap')
            ),
            'cache_max_age' => max(0, $environment->integer('FOXESCRAFT_BOOTSTRAP_CACHE_MAX_AGE', 60)),
            'cors_allowed_origins' => $corsOrigins,
            'launcher_file_name' => 'launcher.jar',
            'launcher_jvm_args' => [
                '-Xms256m',
                '-Xmx2g',
                '-Dfile.encoding=UTF-8',
                '-Djava.net.preferIPv4Stack=true',
            ],
            'launcher_args' => [],
            'hardware_inventory' => [
                'enabled' => $environment->boolean('FOXESCRAFT_HARDWARE_INVENTORY_ENABLED', true),
                'max_payload_bytes' => max(
                    4096,
                    min(131072, $environment->integer('FOXESCRAFT_HARDWARE_INVENTORY_MAX_BYTES', 32768)),
                ),
            ],
            'database' => [
                'host' => $environment->string('FOXESCRAFT_DB_HOST', '127.0.0.1') ?? '127.0.0.1',
                'port' => max(1, min(65535, $environment->integer('FOXESCRAFT_DB_PORT', 3306))),
                'name' => $environment->string('FOXESCRAFT_DB_NAME', 'foxescraft') ?? 'foxescraft',
                'user' => $environment->string('FOXESCRAFT_DB_USER', 'foxescraft') ?? 'foxescraft',
                'password' => $environment->string('FOXESCRAFT_DB_PASSWORD', '') ?? '',
                'connect_timeout' => max(1, min(30, $environment->integer('FOXESCRAFT_DB_CONNECT_TIMEOUT', 5))),
            ],
        ];
    }
}
