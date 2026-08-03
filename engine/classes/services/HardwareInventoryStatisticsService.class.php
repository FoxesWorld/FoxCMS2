<?php

declare(strict_types=1);

final class HardwareInventoryStatisticsService
{
    private const SYSTEM_LIMIT = 100;
    private const DISTRIBUTION_LIMIT = 20;

    private Closure $prepareStatement;

    public function __construct(callable $prepareStatement)
    {
        $this->prepareStatement = Closure::fromCallable($prepareStatement);
    }

    public static function fromDatabase(db $db): self
    {
        return new self(static fn(string $sql): PDOStatement => $db->prepare($sql));
    }

    /** @return array<string, mixed> */
    public function statistics(): array
    {
        $rows = $this->inventoryRows();
        $totalSystems = count($rows);
        $totalMemoryBytes = 0;
        $totalLogicalCpuCount = 0;
        $platforms = [];
        $operatingSystems = [];
        $architectures = [];
        $updaterVersions = [];
        $cpuModels = [];
        $gpuModels = [];
        $memoryBuckets = [];
        $systems = [];
        $firstSeenAt = null;
        $lastSeenAt = null;

        foreach ($rows as $index => $row) {
            $memoryBytes = self::nonNegativeInteger($row['memoryBytes'] ?? 0);
            $logicalCpuCount = max(0, (int)($row['logicalCpuCount'] ?? 0));
            $cpuBrand = self::text($row['cpuBrand'] ?? null, 'Не определён');
            $gpuAdapters = self::decodeStringList($row['gpuAdapters'] ?? null);
            $seenAt = self::nullableText($row['firstSeenAt'] ?? null);

            $totalMemoryBytes = self::safeAdd($totalMemoryBytes, $memoryBytes);
            $totalLogicalCpuCount = self::safeAdd($totalLogicalCpuCount, $logicalCpuCount);
            self::increment($platforms, self::text($row['platform'] ?? null, 'Не определена'));
            self::increment($operatingSystems, self::text($row['osName'] ?? null, 'Не определена'));
            self::increment($architectures, self::text($row['architecture'] ?? null, 'Не определена'));
            self::increment($updaterVersions, self::text($row['updaterVersion'] ?? null, 'Не определена'));
            self::increment($cpuModels, $cpuBrand);
            self::increment($memoryBuckets, self::memoryBucket($memoryBytes));

            foreach ($gpuAdapters as $adapter) {
                self::increment($gpuModels, $adapter);
            }

            if ($seenAt !== null) {
                $firstSeenAt = $firstSeenAt === null || strcmp($seenAt, $firstSeenAt) < 0 ? $seenAt : $firstSeenAt;
                $lastSeenAt = $lastSeenAt === null || strcmp($seenAt, $lastSeenAt) > 0 ? $seenAt : $lastSeenAt;
            }

            if ($index < self::SYSTEM_LIMIT) {
                $systems[] = $this->system($row, $cpuBrand, $gpuAdapters, $memoryBytes, $logicalCpuCount, $seenAt);
            }
        }

        $cpuModelItems = self::sortedCounts($cpuModels);
        $gpuModelItems = self::sortedCounts($gpuModels);
        $gpuCount = array_sum(array_column($gpuModelItems, 'count'));

        return [
            'summary' => [
                'totalSystems' => $totalSystems,
                'totalMemoryBytes' => $totalMemoryBytes,
                'averageMemoryBytes' => $totalSystems > 0 ? (int)round($totalMemoryBytes / $totalSystems) : 0,
                'averageLogicalCpuCount' => $totalSystems > 0
                    ? round($totalLogicalCpuCount / $totalSystems, 1)
                    : 0.0,
                'firstSeenAt' => $firstSeenAt,
                'lastSeenAt' => $lastSeenAt,
            ],
            'platforms' => $this->distribution(self::sortedCounts($platforms), $totalSystems),
            'operatingSystems' => $this->distribution(self::sortedCounts($operatingSystems), $totalSystems),
            'architectures' => $this->distribution(self::sortedCounts($architectures), $totalSystems),
            'updaterVersions' => $this->distribution(self::sortedCounts($updaterVersions), $totalSystems),
            'cpuVendors' => $this->distribution(self::vendorTotals($cpuModelItems, 'cpu'), $totalSystems),
            'gpuVendors' => $this->distribution(self::vendorTotals($gpuModelItems, 'gpu'), $gpuCount),
            'cpuModels' => $this->distribution(array_slice($cpuModelItems, 0, self::DISTRIBUTION_LIMIT), $totalSystems),
            'gpuModels' => $this->distribution(array_slice($gpuModelItems, 0, self::DISTRIBUTION_LIMIT), $gpuCount),
            'memoryBuckets' => $this->orderedMemoryDistribution($memoryBuckets, $totalSystems),
            'systems' => $systems,
        ];
    }

    /** @return list<array<string, mixed>> */
    private function inventoryRows(): array
    {
        $statement = $this->prepare(
            'SELECT `systemHWID`, `schemaVersion`, `updaterVersion`, `platform`, '
            . '`osName`, `osVersion`, `kernelVersion`, `architecture`, `cpuBrand`, '
            . '`logicalCpuCount`, `memoryBytes`, `gpuAdapters`, `firstSeenAt` '
            . 'FROM `system_hardware_inventory` '
            . 'ORDER BY `firstSeenAt` DESC, `systemHWID` ASC'
        );
        $statement->execute();
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        return is_array($rows) ? array_values(array_filter($rows, 'is_array')) : [];
    }

    private function prepare(string $sql): object
    {
        $statement = ($this->prepareStatement)($sql);
        if (!is_object($statement)
            || !method_exists($statement, 'execute')
            || !method_exists($statement, 'fetchAll')
        ) {
            throw new RuntimeException('Hardware statistics query factory returned an invalid statement.');
        }
        return $statement;
    }

    /**
     * @param array<string, mixed> $row
     * @param list<string> $gpuAdapters
     * @return array<string, int|string|null|list<string>>
     */
    private function system(
        array $row,
        string $cpuBrand,
        array $gpuAdapters,
        int $memoryBytes,
        int $logicalCpuCount,
        ?string $seenAt,
    ): array {
        $hardwareId = strtolower(trim((string)($row['systemHWID'] ?? '')));
        return [
            'systemId' => preg_match('/^[a-f0-9]{64}$/D', $hardwareId) === 1
                ? substr($hardwareId, 0, 12)
                : 'invalid-id',
            'schemaVersion' => max(0, (int)($row['schemaVersion'] ?? 0)),
            'updaterVersion' => self::text($row['updaterVersion'] ?? null, '—'),
            'platform' => self::text($row['platform'] ?? null, '—'),
            'osName' => self::text($row['osName'] ?? null, '—'),
            'osVersion' => self::nullableText($row['osVersion'] ?? null),
            'kernelVersion' => self::nullableText($row['kernelVersion'] ?? null),
            'architecture' => self::text($row['architecture'] ?? null, '—'),
            'cpuBrand' => $cpuBrand === 'Не определён' ? null : $cpuBrand,
            'logicalCpuCount' => $logicalCpuCount,
            'memoryBytes' => $memoryBytes,
            'gpuAdapters' => $gpuAdapters,
            'firstSeenAt' => $seenAt,
        ];
    }

    /**
     * @param list<array{label:string,count:int}> $items
     * @return list<array{label:string,count:int,percentage:float}>
     */
    private function distribution(array $items, int $total): array
    {
        return array_map(static function (array $item) use ($total): array {
            $count = max(0, (int)$item['count']);
            return [
                'label' => $item['label'],
                'count' => $count,
                'percentage' => $total > 0 ? round(($count / $total) * 100, 1) : 0.0,
            ];
        }, $items);
    }

    /** @param array<string, int> $counts @return list<array{label:string,count:int,percentage:float}> */
    private function orderedMemoryDistribution(array $counts, int $totalSystems): array
    {
        $order = ['Не определено', '< 8 GB', '8–15 GB', '16–31 GB', '32–63 GB', '64+ GB'];
        $items = [];
        foreach ($order as $label) {
            $count = $counts[$label] ?? 0;
            if ($count > 0) $items[] = ['label' => $label, 'count' => $count];
        }
        return $this->distribution($items, $totalSystems);
    }

    /**
     * @param list<array{label:string,count:int}> $models
     * @return list<array{label:string,count:int}>
     */
    public static function vendorTotals(array $models, string $component): array
    {
        $counts = [];
        foreach ($models as $model) {
            self::increment($counts, self::vendor((string)$model['label'], $component), max(0, (int)$model['count']));
        }
        return self::sortedCounts($counts);
    }

    private static function vendor(string $model, string $component): string
    {
        if ($component === 'cpu') {
            if (preg_match('/\b(?:AMD|Ryzen|Threadripper|EPYC)\b/i', $model) === 1) return 'AMD';
            if (preg_match('/\b(?:Intel|Core\s+i[3579]|Xeon|Celeron|Pentium)\b/i', $model) === 1) return 'Intel';
            if (preg_match('/\bApple\b|\bM[1-9](?:\s|$)/i', $model) === 1) return 'Apple';
            if (preg_match('/\b(?:Qualcomm|Snapdragon)\b/i', $model) === 1) return 'Qualcomm';
        } else {
            if (preg_match('/\b(?:NVIDIA|GeForce|Quadro|RTX|GTX)\b/i', $model) === 1) return 'NVIDIA';
            if (preg_match('/\b(?:AMD|Radeon)\b/i', $model) === 1) return 'AMD';
            if (preg_match('/\b(?:Intel|Arc\s+[A-Z0-9]|Iris|UHD Graphics)\b/i', $model) === 1) return 'Intel';
            if (preg_match('/\bApple\b|\bM[1-9](?:\s|$)/i', $model) === 1) return 'Apple';
        }
        return 'Другие';
    }

    /** @param array<string, int> $counts */
    private static function increment(array &$counts, string $label, int $amount = 1): void
    {
        if ($amount <= 0) return;
        $counts[$label] = ($counts[$label] ?? 0) + $amount;
    }

    /** @param array<string, int> $counts @return list<array{label:string,count:int}> */
    private static function sortedCounts(array $counts): array
    {
        $items = [];
        foreach ($counts as $label => $count) {
            if ($count > 0) $items[] = ['label' => $label, 'count' => $count];
        }
        usort($items, static function (array $left, array $right): int {
            return $right['count'] <=> $left['count'] ?: strnatcasecmp($left['label'], $right['label']);
        });
        return $items;
    }

    /** @return list<string> */
    public static function decodeStringList(mixed $value): array
    {
        if (is_string($value)) {
            try {
                $value = json_decode($value, true, 16, JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                return [];
            }
        }
        if (!is_array($value) || !array_is_list($value)) return [];

        $items = [];
        foreach ($value as $entry) {
            if (!is_string($entry)) continue;
            $entry = trim($entry);
            if ($entry === '' || strlen($entry) > 200 || preg_match('/[\x00-\x1F\x7F]/', $entry) === 1) continue;
            if (!in_array($entry, $items, true)) $items[] = $entry;
            if (count($items) >= 16) break;
        }
        return $items;
    }

    private static function memoryBucket(int $memoryBytes): string
    {
        if ($memoryBytes <= 0) return 'Не определено';
        if ($memoryBytes < 8 * 1024 ** 3) return '< 8 GB';
        if ($memoryBytes < 16 * 1024 ** 3) return '8–15 GB';
        if ($memoryBytes < 32 * 1024 ** 3) return '16–31 GB';
        if ($memoryBytes < 64 * 1024 ** 3) return '32–63 GB';
        return '64+ GB';
    }

    private static function safeAdd(int $left, int $right): int
    {
        if ($right <= 0) return $left;
        return $left > PHP_INT_MAX - $right ? PHP_INT_MAX : $left + $right;
    }

    private static function nonNegativeInteger(mixed $value): int
    {
        if (is_int($value)) return max(0, $value);
        $value = trim((string)$value);
        if ($value === '' || preg_match('/^\d+$/D', $value) !== 1) return 0;
        $number = (float)$value;
        return $number >= PHP_INT_MAX ? PHP_INT_MAX : (int)$number;
    }

    private static function text(mixed $value, string $fallback): string
    {
        $value = is_scalar($value) ? trim((string)$value) : '';
        return $value !== '' ? $value : $fallback;
    }

    private static function nullableText(mixed $value): ?string
    {
        $value = is_scalar($value) ? trim((string)$value) : '';
        return $value !== '' ? $value : null;
    }
}
