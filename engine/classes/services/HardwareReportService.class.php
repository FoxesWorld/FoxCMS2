<?php

declare(strict_types=1);

final class HardwareReportService
{
    private const MAXIMUM_PAYLOAD_BYTES = 65_536;
    private const MAXIMUM_FIELDS = 32;
    private const MAXIMUM_DEPTH = 4;
    private const MAXIMUM_STRING_LENGTH = 4096;
    private const SENSITIVE_KEYS = [
        'accesstoken', 'authorization', 'cookie', 'cpuid', 'email', 'machineid',
        'mac', 'macaddress', 'password', 'secret', 'serial', 'serialnumber',
        'session', 'token', 'userid', 'username', 'uuid',
    ];

    public function __construct(
        private db $database,
        private ?Logger $logger = null,
    ) {
    }

    public function store(string $json, string $userUuid): void
    {
        $userUuid = Uuid::normalize($userUuid);
        if (strlen($json) > self::MAXIMUM_PAYLOAD_BYTES) {
            throw new HttpException('Hardware report is too large.', 413);
        }

        try {
            $report = json_decode($json, true, 8, JSON_THROW_ON_ERROR);
        } catch (JsonException $error) {
            throw new HttpException('Malformed hardware report.', 400, [], $error);
        }
        if (!is_array($report) || array_is_list($report)) {
            throw new HttpException('Hardware report must be an object.', 400);
        }

        $redactedFields = 0;
        $droppedFields = 0;
        $cpuId = $this->identifier($report, ['cpuId', 'cpu_id', 'machineId', 'machine_id']);
        $sanitized = $this->sanitizeObject($report, 0, $redactedFields, $droppedFields);
        $cpu = mb_substr((string)($sanitized['cpu'] ?? $sanitized['cpuName'] ?? ''), 0, 255);
        $rawGpus = $sanitized['gpus'] ?? $sanitized['gpu'] ?? [];
        $gpus = is_array($rawGpus)
            ? array_values(array_map(
                static fn (mixed $value): string => mb_substr((string)$value, 0, 255),
                array_slice($rawGpus, 0, 16),
            ))
            : [mb_substr((string)$rawGpus, 0, 255)];

        $payload = json_encode(
            $sanitized,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
        );
        $gpuJson = json_encode(
            $gpus,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
        );

        $statement = $this->database->prepare(
            'INSERT INTO `user_hardware_reports` (`userUuid`, `cpuIdHash`, `cpu`, `gpus`, `payload`) '
            . 'VALUES (:userUuid, :cpuIdHash, :cpu, :gpus, :payload) '
            . 'ON DUPLICATE KEY UPDATE '
            . '`cpuIdHash` = VALUES(`cpuIdHash`), `cpu` = VALUES(`cpu`), '
            . '`gpus` = VALUES(`gpus`), `payload` = VALUES(`payload`), '
            . '`updatedAt` = CURRENT_TIMESTAMP(4)'
        );
        $statement->execute([
            ':userUuid' => $userUuid,
            ':cpuIdHash' => $cpuId === '' ? null : hash('sha256', $cpuId),
            ':cpu' => $cpu,
            ':gpus' => $gpuJson,
            ':payload' => $payload,
        ]);

        if ($redactedFields > 0) {
            $this->logger?->deviation(
                'hardware_report.sensitive_fields_removed',
                'sensitive_hardware_fields_present',
                'Sensitive fields were removed from a hardware report before storage.',
                'notice',
                ['sensitiveFieldCount' => 0],
                ['sensitiveFieldCount' => $redactedFields],
                ['component' => 'hardware_report', 'targetUserUuid' => $userUuid],
            );
        }
        if ($droppedFields > 0) {
            $this->logger?->deviation(
                'hardware_report.fields_dropped',
                'hardware_report_limits_exceeded',
                'Hardware report fields were dropped because they exceeded schema limits.',
                'notice',
                ['droppedFieldCount' => 0],
                ['droppedFieldCount' => $droppedFields],
                ['component' => 'hardware_report', 'targetUserUuid' => $userUuid],
            );
        }
        $this->logger?->event(
            'hardware_report.stored',
            'Hardware report stored.',
            [
                'component' => 'hardware_report',
                'operation' => 'store',
                'targetUserUuid' => $userUuid,
                'payloadBytes' => strlen($payload),
                'gpuCount' => count($gpus),
                'cpuIdentifierPresent' => $cpuId !== '',
                'redactedFieldCount' => $redactedFields,
                'droppedFieldCount' => $droppedFields,
            ],
            'INFO',
            'success',
        );
    }

    /** @param list<string> $keys */
    private function identifier(array $source, array $keys): string
    {
        foreach ($keys as $key) {
            $value = trim((string)($source[$key] ?? ''));
            if ($value !== '') {
                return mb_substr($value, 0, self::MAXIMUM_STRING_LENGTH);
            }
        }
        return '';
    }

    private function isSensitiveKey(string $key): bool
    {
        $normalized = preg_replace('/[^a-z0-9]/', '', strtolower($key)) ?? '';
        return in_array($normalized, self::SENSITIVE_KEYS, true);
    }

    /** @return array<string, mixed> */
    private function sanitizeObject(
        array $source,
        int $depth,
        int &$redactedFields,
        int &$droppedFields,
    ): array {
        if ($depth >= self::MAXIMUM_DEPTH) {
            $droppedFields += count($source);
            return [];
        }

        if (count($source) > self::MAXIMUM_FIELDS) {
            $droppedFields += count($source) - self::MAXIMUM_FIELDS;
        }
        $result = [];
        foreach (array_slice($source, 0, self::MAXIMUM_FIELDS, true) as $key => $value) {
            if (!is_string($key) || preg_match('/^[A-Za-z_][A-Za-z0-9_]{0,63}$/D', $key) !== 1) {
                $droppedFields++;
                continue;
            }
            if ($this->isSensitiveKey($key)) {
                $redactedFields++;
                continue;
            }
            $result[$key] = $this->sanitizeValue(
                $value,
                $depth + 1,
                $redactedFields,
                $droppedFields,
            );
        }
        return $result;
    }

    private function sanitizeValue(
        mixed $value,
        int $depth,
        int &$redactedFields,
        int &$droppedFields,
    ): mixed {
        if (is_string($value)) {
            return mb_substr($value, 0, self::MAXIMUM_STRING_LENGTH);
        }
        if (is_scalar($value) || $value === null) {
            return $value;
        }
        if (!is_array($value) || $depth >= self::MAXIMUM_DEPTH) {
            $droppedFields++;
            return null;
        }
        if (array_is_list($value)) {
            if (count($value) > self::MAXIMUM_FIELDS) {
                $droppedFields += count($value) - self::MAXIMUM_FIELDS;
            }
            return array_map(
                fn (mixed $entry): mixed => $this->sanitizeValue(
                    $entry,
                    $depth + 1,
                    $redactedFields,
                    $droppedFields,
                ),
                array_slice($value, 0, self::MAXIMUM_FIELDS),
            );
        }
        return $this->sanitizeObject($value, $depth, $redactedFields, $droppedFields);
    }
}
