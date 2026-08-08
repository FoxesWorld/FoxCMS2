<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

final class FakeHardwareStatement
{
    /** @var list<array<string, mixed>> */
    private array $rows;

    /** @param list<array<string, mixed>> $rows */
    public function __construct(array $rows)
    {
        $this->rows = $rows;
    }

    public function execute(array $parameters = []): bool
    {
        if ($parameters !== []) {
            throw new RuntimeException('Hardware inventory query must not depend on SQL parameters.');
        }
        return true;
    }

    /** @return list<array<string, mixed>> */
    public function fetchAll(int $mode = PDO::FETCH_DEFAULT): array
    {
        return $this->rows;
    }
}

final class FakeHardwareDatabase
{
    public int $queryCount = 0;

    public function prepare(string $sql): FakeHardwareStatement
    {
        $this->queryCount++;
        if (!str_contains($sql, 'FROM `system_hardware_inventory`')
            || !str_contains($sql, 'ORDER BY `firstSeenAt` DESC')) {
            throw new RuntimeException('Unexpected hardware inventory SQL: ' . $sql);
        }

        return new FakeHardwareStatement([
            [
                'systemHWID' => str_repeat('a', 64),
                'schemaVersion' => '1',
                'updaterVersion' => '0.4.5',
                'platform' => 'windows-x86_64',
                'osName' => 'windows',
                'osVersion' => 'Windows 11',
                'kernelVersion' => '10.0.26100',
                'architecture' => 'x86_64',
                'cpuBrand' => 'AMD Ryzen 9',
                'logicalCpuCount' => '16',
                'memoryBytes' => '34359738368',
                'gpuAdapters' => '["NVIDIA GeForce RTX 4080","Intel UHD Graphics"]',
                'firstSeenAt' => '2026-08-02 10:00:00',
            ],
            [
                'systemHWID' => str_repeat('b', 64),
                'schemaVersion' => '1',
                'updaterVersion' => '0.4.5',
                'platform' => 'windows-x86_64',
                'osName' => 'windows',
                'osVersion' => 'Windows 10',
                'kernelVersion' => '10.0.19045',
                'architecture' => 'x86_64',
                'cpuBrand' => 'Intel Core i7',
                'logicalCpuCount' => '8',
                'memoryBytes' => '17179869184',
                'gpuAdapters' => '["AMD Radeon RX 7900","AMD Radeon RX 7900"]',
                'firstSeenAt' => '2026-08-01 10:00:00',
            ],
        ]);
    }
}

$root = dirname(__DIR__);
require_once $root . '/engine/classes/services/HardwareInventoryStatisticsService.class.php';

$fakeDatabase = new FakeHardwareDatabase();
$statistics = (new HardwareInventoryStatisticsService([$fakeDatabase, 'prepare']))->statistics();
assertSame(1, $fakeDatabase->queryCount, 'database query count');
assertSame(2, $statistics['summary']['totalSystems'] ?? null, 'totalSystems');
assertSame(25769803776, $statistics['summary']['averageMemoryBytes'] ?? null, 'averageMemoryBytes');
assertSame(12.0, $statistics['summary']['averageLogicalCpuCount'] ?? null, 'averageLogicalCpuCount');
assertSame('2026-08-01 10:00:00', $statistics['summary']['firstSeenAt'] ?? null, 'firstSeenAt');
assertSame('2026-08-02 10:00:00', $statistics['summary']['lastSeenAt'] ?? null, 'lastSeenAt');
assertSame('AMD', $statistics['cpuVendors'][0]['label'] ?? null, 'CPU vendor parsing');
assertSame(1, $statistics['cpuVendors'][0]['count'] ?? null, 'CPU vendor count');
assertSame(3, count($statistics['gpuModels'] ?? []), 'GPU JSON parsing and per-system de-duplication');
assertSame('aaaaaaaaaaaa', $statistics['systems'][0]['systemId'] ?? null, 'masked system identifier');
assertSame(2, count($statistics['systems'] ?? []), 'system rows');

$encoded = json_encode($statistics, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
if (str_contains($encoded, str_repeat('a', 64)) || str_contains($encoded, str_repeat('b', 64))) {
    throw new RuntimeException('Full systemHWID leaked into the administrative response.');
}

$adminSource = requireText($root . '/engine/src/FoxCMS/Engine/Admin/AdminSystemController.php');
$serviceSource = requireText($root . '/engine/classes/services/HardwareInventoryStatisticsService.class.php');
$frontendSource = requireText($root . '/templates/foxengine2/src/foxEngine/admin/Overview.vue');
$schemaSource = requireText($root . '/database/schema-000.sql');

assertContains('system_hardware_inventory', $adminSource, 'overview must count the new inventory table');
assertContains('system_hardware_inventory', $serviceSource, 'statistics service must use the new inventory table');
assertContains('gpuAdapters', $serviceSource, 'statistics service must parse the new GPU field');
assertContains('hardware.systems', $frontendSource, 'admin UI must render individual hardware records');
assertContains('hardware.memoryBuckets', $frontendSource, 'admin UI must render memory statistics');
assertContains('CREATE TABLE `system_hardware_inventory`', $schemaSource, 'fresh schema must include hardware inventory');

foreach ([$adminSource, $serviceSource, $frontendSource] as $source) {
    if (str_contains($source, 'user_hardware_reports')) {
        throw new RuntimeException('Legacy user_hardware_reports fallback is forbidden.');
    }
}

fwrite(STDOUT, "Admin hardware contract passed: one compatible inventory query, complete statistics, masked HWID and no legacy fallback.\n");

function assertSame(mixed $expected, mixed $actual, string $label): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($label . ' mismatch; expected=' . var_export($expected, true) . '; actual=' . var_export($actual, true));
    }
}

function assertContains(string $needle, string $haystack, string $message): void
{
    if (!str_contains($haystack, $needle)) {
        throw new RuntimeException($message . '; missing=' . $needle);
    }
}

function requireText(string $path): string
{
    $content = file_get_contents($path);
    if (!is_string($content)) throw new RuntimeException('Cannot read ' . $path);
    return $content;
}
