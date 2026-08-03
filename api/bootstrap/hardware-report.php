<?php

declare(strict_types=1);

/**
 * Validated, privacy-bounded hardware capability report supplied by UpdaterNorth.
 * systemHWID is already a domain-separated SHA-256 value; raw machine identifiers
 * are never accepted by this endpoint.
 */
final class BootstrapHardwareReport
{
    private const SCHEMA_VERSION = 1;
    private const MAX_GPU_ADAPTERS = 16;

    /** @var array<string, mixed> */
    private array $payload;

    /** @param array<string, mixed> $payload */
    private function __construct(array $payload)
    {
        $this->payload = $payload;
    }

    public static function fromHttpBody(int $maxBytes): self
    {
        $contentType = strtolower(trim((string)($_SERVER['CONTENT_TYPE'] ?? '')));
        if ($contentType !== '' && !str_starts_with($contentType, 'application/json')) {
            fail(415, 'hardware_report_content_type_invalid', 'Hardware report requests must use application/json.');
        }

        $declaredLength = filter_var($_SERVER['CONTENT_LENGTH'] ?? null, FILTER_VALIDATE_INT);
        if (is_int($declaredLength) && $declaredLength > $maxBytes) {
            fail(413, 'hardware_report_too_large', 'Hardware report exceeds the permitted request size.');
        }

        $body = file_get_contents('php://input', false, null, 0, $maxBytes + 1);
        if (!is_string($body)) {
            fail(400, 'hardware_report_unreadable', 'Hardware report request body cannot be read.');
        }
        if (strlen($body) > $maxBytes) {
            fail(413, 'hardware_report_too_large', 'Hardware report exceeds the permitted request size.');
        }
        if (trim($body) === '') {
            fail(422, 'hardware_report_required', 'Hardware report request body is required.');
        }

        try {
            $decoded = json_decode($body, true, 32, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            fail(400, 'hardware_report_json_invalid', 'Hardware report contains invalid JSON.');
        }

        if (!is_array($decoded) || array_is_list($decoded)) {
            fail(422, 'hardware_report_object_required', 'Hardware report must be a JSON object.');
        }

        return self::fromArray($decoded);
    }

    /** @param array<string, mixed> $input */
    public static function fromArray(array $input): self
    {
        $schemaVersion = $input['schemaVersion'] ?? null;
        if (!is_int($schemaVersion) || $schemaVersion !== self::SCHEMA_VERSION) {
            fail(422, 'hardware_report_schema_unsupported', 'Hardware report schemaVersion is unsupported.', [
                'supported' => self::SCHEMA_VERSION,
            ]);
        }

        $systemHwid = strtolower(self::requiredString($input, 'systemHWID', 64));
        if (preg_match('/^[a-f0-9]{64}$/D', $systemHwid) !== 1) {
            fail(422, 'hardware_report_hwid_invalid', 'systemHWID must be a lowercase SHA-256 value.');
        }

        $platform = self::requiredString($input, 'platform', 32);
        if (preg_match('/^(?:windows|linux|macos)-(?:x86|x86_64|aarch64)$/D', $platform) !== 1) {
            fail(422, 'hardware_report_platform_invalid', 'Hardware report platform is unsupported.');
        }

        $updaterVersion = self::requiredString($input, 'updaterVersion', 64);
        if (preg_match('/^[A-Za-z0-9][A-Za-z0-9._+-]{0,63}$/D', $updaterVersion) !== 1) {
            fail(422, 'hardware_report_updater_version_invalid', 'Hardware report updaterVersion is invalid.');
        }

        $systemInformation = self::requiredObject($input, 'systemInformation');
        $os = self::requiredObject($systemInformation, 'os');
        $cpu = self::requiredObject($systemInformation, 'cpu');
        $memory = self::requiredObject($systemInformation, 'memory');
        $gpu = isset($systemInformation['gpu'])
            ? self::requiredObject($systemInformation, 'gpu')
            : ['adapters' => []];

        $osName = self::requiredString($os, 'name', 32);
        if (!in_array($osName, ['windows', 'linux', 'macos'], true)) {
            fail(422, 'hardware_report_os_invalid', 'Hardware report operating system is unsupported.');
        }
        $architecture = self::requiredString($os, 'architecture', 32);
        if (!in_array($architecture, ['x86', 'x86_64', 'aarch64'], true)) {
            fail(422, 'hardware_report_architecture_invalid', 'Hardware report architecture is unsupported.');
        }
        if ($platform !== $osName . '-' . $architecture) {
            fail(422, 'hardware_report_platform_mismatch', 'Hardware report platform does not match os and architecture.');
        }

        $logicalCores = $cpu['logicalCores'] ?? null;
        if (!is_int($logicalCores) || $logicalCores < 1 || $logicalCores > 4096) {
            fail(422, 'hardware_report_cpu_cores_invalid', 'cpu.logicalCores must be an integer between 1 and 4096.');
        }

        $totalBytes = $memory['totalBytes'] ?? null;
        if (!is_int($totalBytes) || $totalBytes < 0) {
            fail(422, 'hardware_report_memory_invalid', 'memory.totalBytes must be a non-negative integer.');
        }

        $adapters = $gpu['adapters'] ?? [];
        if (!is_array($adapters) || !array_is_list($adapters) || count($adapters) > self::MAX_GPU_ADAPTERS) {
            fail(422, 'hardware_report_gpu_invalid', 'gpu.adapters must be a bounded string list.');
        }
        $normalizedAdapters = [];
        foreach ($adapters as $index => $adapter) {
            if (!is_string($adapter)) {
                fail(422, 'hardware_report_gpu_invalid', 'gpu.adapters contains a non-string value.', ['index' => $index]);
            }
            $adapter = self::normalizeString($adapter, 200, 'gpu.adapters');
            if (!in_array($adapter, $normalizedAdapters, true)) {
                $normalizedAdapters[] = $adapter;
            }
        }

        $payload = [
            'schemaVersion' => self::SCHEMA_VERSION,
            'systemHWID' => $systemHwid,
            'platform' => $platform,
            'updaterVersion' => $updaterVersion,
            'systemInformation' => [
                'os' => [
                    'name' => $osName,
                    'version' => self::optionalString($os, 'version', 120),
                    'kernel' => self::optionalString($os, 'kernel', 120),
                    'architecture' => $architecture,
                ],
                'cpu' => [
                    'brand' => self::optionalString($cpu, 'brand', 200),
                    'logicalCores' => $logicalCores,
                ],
                'memory' => [
                    'totalBytes' => $totalBytes,
                ],
                'gpu' => [
                    'adapters' => $normalizedAdapters,
                ],
            ],
        ];

        return new self($payload);
    }

    public function systemHwid(): string
    {
        return (string)$this->payload['systemHWID'];
    }

    public function schemaVersion(): int
    {
        return (int)$this->payload['schemaVersion'];
    }

    public function platform(): string
    {
        return (string)$this->payload['platform'];
    }

    public function updaterVersion(): string
    {
        return (string)$this->payload['updaterVersion'];
    }

    public function osName(): string
    {
        return (string)$this->payload['systemInformation']['os']['name'];
    }

    public function osVersion(): ?string
    {
        return $this->payload['systemInformation']['os']['version'];
    }

    public function kernelVersion(): ?string
    {
        return $this->payload['systemInformation']['os']['kernel'];
    }

    public function architecture(): string
    {
        return (string)$this->payload['systemInformation']['os']['architecture'];
    }

    public function cpuBrand(): ?string
    {
        return $this->payload['systemInformation']['cpu']['brand'];
    }

    public function logicalCpuCount(): int
    {
        return (int)$this->payload['systemInformation']['cpu']['logicalCores'];
    }

    public function memoryBytes(): int
    {
        return (int)$this->payload['systemInformation']['memory']['totalBytes'];
    }

    /** @return list<string> */
    public function gpuAdapters(): array
    {
        return $this->payload['systemInformation']['gpu']['adapters'];
    }

    /** @return array<string, mixed> */
    public function payload(): array
    {
        return $this->payload;
    }

    /** @param array<string, mixed> $source */
    private static function requiredObject(array $source, string $field): array
    {
        $value = $source[$field] ?? null;
        if (!is_array($value) || array_is_list($value)) {
            fail(422, 'hardware_report_field_invalid', $field . ' must be a JSON object.', ['field' => $field]);
        }
        return $value;
    }

    /** @param array<string, mixed> $source */
    private static function requiredString(array $source, string $field, int $maxBytes): string
    {
        $value = $source[$field] ?? null;
        if (!is_string($value)) {
            fail(422, 'hardware_report_field_invalid', $field . ' must be a string.', ['field' => $field]);
        }
        return self::normalizeString($value, $maxBytes, $field);
    }

    /** @param array<string, mixed> $source */
    private static function optionalString(array $source, string $field, int $maxBytes): ?string
    {
        if (!array_key_exists($field, $source) || $source[$field] === null || $source[$field] === '') {
            return null;
        }
        if (!is_string($source[$field])) {
            fail(422, 'hardware_report_field_invalid', $field . ' must be a string or null.', ['field' => $field]);
        }
        return self::normalizeString($source[$field], $maxBytes, $field);
    }

    private static function normalizeString(string $value, int $maxBytes, string $field): string
    {
        $value = trim($value);
        if ($value === '' || strlen($value) > $maxBytes || preg_match('/[\x00-\x1F\x7F]/', $value) === 1) {
            fail(422, 'hardware_report_field_invalid', $field . ' is empty, too long or contains control characters.', [
                'field' => $field,
                'maxBytes' => $maxBytes,
            ]);
        }
        return $value;
    }
}
