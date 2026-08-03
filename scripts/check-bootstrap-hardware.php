<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

final class BootstrapHardwareContractException extends RuntimeException
{
    public function __construct(
        public readonly int $status,
        public readonly string $errorCode,
        string $message,
        public readonly array $details = [],
    ) {
        parent::__construct($message);
    }
}

function fail(int $statusCode, string $errorCode, string $message, array $details = []): void
{
    throw new BootstrapHardwareContractException($statusCode, $errorCode, $message, $details);
}

$root = dirname(__DIR__);
require_once $root . '/api/bootstrap/hardware-report.php';

$valid = [
    'schemaVersion' => 1,
    'systemHWID' => str_repeat('a', 64),
    'platform' => 'windows-x86_64',
    'updaterVersion' => '0.4.3',
    'systemInformation' => [
        'os' => [
            'name' => 'windows',
            'version' => 'Windows 11',
            'kernel' => '10.0.26100',
            'architecture' => 'x86_64',
        ],
        'cpu' => [
            'brand' => 'Contract CPU',
            'logicalCores' => 16,
        ],
        'memory' => [
            'totalBytes' => 34359738368,
        ],
        'gpu' => [
            'adapters' => ['Contract GPU'],
        ],
    ],
];

$report = BootstrapHardwareReport::fromArray($valid);
assertSame(str_repeat('a', 64), $report->systemHwid(), 'systemHWID must be preserved');
assertSame('windows-x86_64', $report->platform(), 'platform must be preserved');
assertSame(16, $report->logicalCpuCount(), 'logical CPU count must be preserved');
assertSame(34359738368, $report->memoryBytes(), 'memory bytes must be preserved');

$duplicateAdapters = $valid;
$duplicateAdapters['systemInformation']['gpu']['adapters'] = ['Contract GPU', 'Contract GPU'];
assertSame(
    ['Contract GPU'],
    BootstrapHardwareReport::fromArray($duplicateAdapters)->gpuAdapters(),
    'GPU adapter names must be de-duplicated',
);

$invalidHwid = $valid;
$invalidHwid['systemHWID'] = 'raw-machine-guid';
assertRejected($invalidHwid, 'hardware_report_hwid_invalid');

$platformMismatch = $valid;
$platformMismatch['systemInformation']['os']['architecture'] = 'aarch64';
assertRejected($platformMismatch, 'hardware_report_platform_mismatch');

$repositorySource = requireText($root . '/api/bootstrap/hardware-inventory.php');
assertContains('INSERT IGNORE INTO `system_hardware_inventory`', $repositorySource, 'repository must insert idempotently');
if (str_contains(strtoupper($repositorySource), 'ON DUPLICATE KEY UPDATE')) {
    throw new RuntimeException('Hardware inventory must not update an existing first-seen record.');
}

$migrationSource = requireText($root . '/database/migrations/011_system_hardware_inventory.sql');
assertContains('PRIMARY KEY (`systemHWID`)', $migrationSource, 'systemHWID must be the uniqueness boundary');
assertContains('`report` JSON NOT NULL', $migrationSource, 'canonical report JSON must be retained');

fwrite(STDOUT, "Bootstrap hardware contract passed: validation, privacy-safe systemHWID and insert-once SQL are present.\n");

/** @param array<string, mixed> $payload */
function assertRejected(array $payload, string $expectedCode): void
{
    try {
        BootstrapHardwareReport::fromArray($payload);
    } catch (BootstrapHardwareContractException $exception) {
        assertSame($expectedCode, $exception->errorCode, 'unexpected validation error code');
        return;
    }
    throw new RuntimeException('Expected hardware report validation to fail with ' . $expectedCode);
}

function assertSame(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message . '; expected=' . var_export($expected, true) . '; actual=' . var_export($actual, true));
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
    if (!is_string($content)) {
        throw new RuntimeException('Cannot read ' . $path);
    }
    return $content;
}
