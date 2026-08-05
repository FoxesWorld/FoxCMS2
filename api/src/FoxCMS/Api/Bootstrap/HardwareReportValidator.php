<?php

declare(strict_types=1);

namespace FoxCMS\Api\Bootstrap;

use FoxCMS\Api\Core\HttpException;

final class HardwareReportValidator
{
    private const SCHEMA_VERSION = 1;
    private const MAX_GPU_ADAPTERS = 16;

    /** @param array<string, mixed> $input */
    public function validate(array $input): HardwareReport
    {
        $schemaVersion = $input['schemaVersion'] ?? null;
        if (!is_int($schemaVersion) || $schemaVersion !== self::SCHEMA_VERSION) {
            $this->fail(422, 'hardware_report_schema_unsupported', 'Hardware report schemaVersion is unsupported.', [
                'supported' => self::SCHEMA_VERSION,
            ]);
        }

        $systemHwid = strtolower($this->requiredString($input, 'systemHWID', 64));
        if (preg_match('/^[a-f0-9]{64}$/D', $systemHwid) !== 1) {
            $this->fail(422, 'hardware_report_hwid_invalid', 'systemHWID must be a lowercase SHA-256 value.');
        }

        $platform = $this->requiredString($input, 'platform', 32);
        if (preg_match('/^(?:windows|linux|macos)-(?:x86|x86_64|aarch64)$/D', $platform) !== 1) {
            $this->fail(422, 'hardware_report_platform_invalid', 'Hardware report platform is unsupported.');
        }

        $updaterVersion = $this->requiredString($input, 'updaterVersion', 64);
        if (preg_match('/^[A-Za-z0-9][A-Za-z0-9._+-]{0,63}$/D', $updaterVersion) !== 1) {
            $this->fail(422, 'hardware_report_updater_version_invalid', 'Hardware report updaterVersion is invalid.');
        }

        $systemInformation = $this->requiredObject($input, 'systemInformation');
        $os = $this->requiredObject($systemInformation, 'os');
        $cpu = $this->requiredObject($systemInformation, 'cpu');
        $memory = $this->requiredObject($systemInformation, 'memory');
        $gpu = isset($systemInformation['gpu'])
            ? $this->requiredObject($systemInformation, 'gpu')
            : ['adapters' => []];

        $osName = $this->requiredString($os, 'name', 32);
        if (!in_array($osName, ['windows', 'linux', 'macos'], true)) {
            $this->fail(422, 'hardware_report_os_invalid', 'Hardware report operating system is unsupported.');
        }
        $architecture = $this->requiredString($os, 'architecture', 32);
        if (!in_array($architecture, ['x86', 'x86_64', 'aarch64'], true)) {
            $this->fail(422, 'hardware_report_architecture_invalid', 'Hardware report architecture is unsupported.');
        }
        if ($platform !== $osName . '-' . $architecture) {
            $this->fail(422, 'hardware_report_platform_mismatch', 'Hardware report platform does not match os and architecture.');
        }

        $logicalCores = $cpu['logicalCores'] ?? null;
        if (!is_int($logicalCores) || $logicalCores < 1 || $logicalCores > 4096) {
            $this->fail(422, 'hardware_report_cpu_cores_invalid', 'cpu.logicalCores must be an integer between 1 and 4096.');
        }
        $totalBytes = $memory['totalBytes'] ?? null;
        if (!is_int($totalBytes) || $totalBytes < 0) {
            $this->fail(422, 'hardware_report_memory_invalid', 'memory.totalBytes must be a non-negative integer.');
        }

        return new HardwareReport([
            'schemaVersion' => self::SCHEMA_VERSION,
            'systemHWID' => $systemHwid,
            'platform' => $platform,
            'updaterVersion' => $updaterVersion,
            'systemInformation' => [
                'os' => [
                    'name' => $osName,
                    'version' => $this->optionalString($os, 'version', 120),
                    'kernel' => $this->optionalString($os, 'kernel', 120),
                    'architecture' => $architecture,
                ],
                'cpu' => [
                    'brand' => $this->optionalString($cpu, 'brand', 200),
                    'logicalCores' => $logicalCores,
                ],
                'memory' => ['totalBytes' => $totalBytes],
                'gpu' => ['adapters' => $this->gpuAdapters($gpu)],
            ],
        ]);
    }

    /** @param array<string, mixed> $source @return array<string, mixed> */
    private function requiredObject(array $source, string $field): array
    {
        $value = $source[$field] ?? null;
        if (!is_array($value) || array_is_list($value)) {
            $this->fail(422, 'hardware_report_field_invalid', $field . ' must be a JSON object.', ['field' => $field]);
        }
        return $value;
    }

    /** @param array<string, mixed> $source */
    private function requiredString(array $source, string $field, int $maxBytes): string
    {
        $value = $source[$field] ?? null;
        if (!is_string($value)) {
            $this->fail(422, 'hardware_report_field_invalid', $field . ' must be a string.', ['field' => $field]);
        }
        return $this->normalizeString($value, $maxBytes, $field);
    }

    /** @param array<string, mixed> $source */
    private function optionalString(array $source, string $field, int $maxBytes): ?string
    {
        if (!array_key_exists($field, $source) || $source[$field] === null || $source[$field] === '') {
            return null;
        }
        if (!is_string($source[$field])) {
            $this->fail(422, 'hardware_report_field_invalid', $field . ' must be a string or null.', ['field' => $field]);
        }
        return $this->normalizeString($source[$field], $maxBytes, $field);
    }

    /** @param array<string, mixed> $gpu @return list<string> */
    private function gpuAdapters(array $gpu): array
    {
        $adapters = $gpu['adapters'] ?? [];
        if (!is_array($adapters) || !array_is_list($adapters) || count($adapters) > self::MAX_GPU_ADAPTERS) {
            $this->fail(422, 'hardware_report_gpu_invalid', 'gpu.adapters must be a bounded string list.');
        }
        $normalized = [];
        foreach ($adapters as $index => $adapter) {
            if (!is_string($adapter)) {
                $this->fail(422, 'hardware_report_gpu_invalid', 'gpu.adapters contains a non-string value.', ['index' => $index]);
            }
            $adapter = $this->normalizeString($adapter, 200, 'gpu.adapters');
            if (!in_array($adapter, $normalized, true)) {
                $normalized[] = $adapter;
            }
        }
        return $normalized;
    }

    private function normalizeString(string $value, int $maxBytes, string $field): string
    {
        $value = trim($value);
        if ($value === '' || strlen($value) > $maxBytes || preg_match('/[\x00-\x1F\x7F]/', $value) === 1) {
            $this->fail(422, 'hardware_report_field_invalid', $field . ' is empty, too long or contains control characters.', [
                'field' => $field,
                'maxBytes' => $maxBytes,
            ]);
        }
        return $value;
    }

    /** @param array<string, mixed> $details */
    private function fail(int $statusCode, string $errorCode, string $message, array $details = []): never
    {
        throw new HttpException($statusCode, $errorCode, $message, $details);
    }
}
