<?php

declare(strict_types=1);

namespace FoxCMS\Api\Bootstrap;

use FoxCMS\Api\Core\HttpException;

final class BootstrapSettings
{
    /** @param array<string, mixed> $config */
    public function __construct(private readonly array $config)
    {
    }

    public function storageDirectory(): string
    {
        $value = $this->config['storage_directory'] ?? null;
        if (!is_string($value) || $value === '') {
            throw new HttpException(500, 'bootstrap_configuration_invalid', 'Bootstrap storage directory is empty or invalid.');
        }
        $isUnixAbsolute = str_starts_with($value, DIRECTORY_SEPARATOR);
        $isWindowsAbsolute = strlen($value) >= 3
            && ctype_alpha($value[0])
            && $value[1] === ':'
            && ($value[2] === '\\' || $value[2] === '/');
        if (!$isUnixAbsolute && !$isWindowsAbsolute) {
            throw new HttpException(500, 'bootstrap_configuration_invalid', 'Bootstrap storage directory must be absolute.');
        }
        return rtrim($value, '/\\');
    }

    public function cacheMaxAge(): int
    {
        $value = $this->config['cache_max_age'] ?? 60;
        if (!is_int($value) || $value < 0) {
            throw new HttpException(500, 'bootstrap_configuration_invalid', 'cache_max_age must be a non-negative integer.');
        }
        return $value;
    }

    public function launcherFileName(): string
    {
        return (string)($this->config['launcher_file_name'] ?? 'launcher.jar');
    }

    /** @return list<string> */
    public function launcherJvmArgs(): array
    {
        return $this->stringList('launcher_jvm_args');
    }

    /** @return list<string> */
    public function launcherArgs(): array
    {
        return $this->stringList('launcher_args');
    }

    public function hardwareInventoryEnabled(): bool
    {
        return ($this->hardwareInventory()['enabled'] ?? true) === true;
    }

    public function hardwareInventoryMaxPayloadBytes(): int
    {
        $value = (int)($this->hardwareInventory()['max_payload_bytes'] ?? 32768);
        if ($value < 4096 || $value > 131072) {
            throw new HttpException(500, 'bootstrap_configuration_invalid', 'Hardware inventory payload limit is invalid.');
        }
        return $value;
    }

    /** @return array<string, mixed> */
    public function database(): array
    {
        return is_array($this->config['database'] ?? null) ? $this->config['database'] : [];
    }

    /** @return array<string, mixed> */
    private function hardwareInventory(): array
    {
        return is_array($this->config['hardware_inventory'] ?? null)
            ? $this->config['hardware_inventory']
            : [];
    }

    /** @return list<string> */
    private function stringList(string $field): array
    {
        $value = $this->config[$field] ?? [];
        if (!is_array($value)) {
            throw new HttpException(500, 'bootstrap_configuration_invalid', $field . ' must be an array.');
        }
        foreach ($value as $index => $entry) {
            if (!is_string($entry) || str_contains($entry, "\0")) {
                throw new HttpException(
                    500,
                    'bootstrap_configuration_invalid',
                    $field . ' contains an invalid value.',
                    ['index' => $index],
                );
            }
        }
        return array_values($value);
    }
}
