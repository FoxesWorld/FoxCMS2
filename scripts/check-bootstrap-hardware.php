<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$root = dirname(__DIR__);
require_once $root . '/api/autoload.php';

use FoxCMS\Api\Bootstrap\BootstrapCorsPolicy;
use FoxCMS\Api\Bootstrap\HardwareReportFactory;
use FoxCMS\Api\Core\HttpException;

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

assertSame('https://example.com', BootstrapCorsPolicy::normalizeOrigin('https://Example.COM:443/'), 'HTTPS default port must be canonicalized');
assertSame('http://localhost:8080', BootstrapCorsPolicy::normalizeOrigin('http://LOCALHOST:8080'), 'non-default port must be retained');
assertSame('', BootstrapCorsPolicy::normalizeOrigin('https://example.com/path'), 'origin paths must be rejected');
assertSame('', BootstrapCorsPolicy::normalizeOrigin('https://user@example.com'), 'origin credentials must be rejected');
assertSame('', BootstrapCorsPolicy::normalizeOrigin("https://example.com\r\nX-Test: injected"), 'CRLF origins must be rejected');
assertSame('', BootstrapCorsPolicy::normalizeOrigin('ftp://example.com'), 'non-HTTP origins must be rejected');

$factory = new HardwareReportFactory();
$report = $factory->fromArray($valid);
assertSame(str_repeat('a', 64), $report->systemHwid(), 'systemHWID must be preserved');
assertSame('windows-x86_64', $report->platform(), 'platform must be preserved');
assertSame(16, $report->logicalCpuCount(), 'logical CPU count must be preserved');
assertSame(34359738368, $report->memoryBytes(), 'memory bytes must be preserved');

$duplicateAdapters = $valid;
$duplicateAdapters['systemInformation']['gpu']['adapters'] = ['Contract GPU', 'Contract GPU'];
assertSame(
    ['Contract GPU'],
    $factory->fromArray($duplicateAdapters)->gpuAdapters(),
    'GPU adapter names must be de-duplicated',
);

$invalidHwid = $valid;
$invalidHwid['systemHWID'] = 'raw-machine-guid';
assertRejected($invalidHwid, 'hardware_report_hwid_invalid');

$platformMismatch = $valid;
$platformMismatch['systemInformation']['os']['architecture'] = 'aarch64';
assertRejected($platformMismatch, 'hardware_report_platform_mismatch');

$repositorySource = requireText($root . '/api/src/FoxCMS/Api/Bootstrap/HardwareInventoryRepository.php');
assertContains('INSERT IGNORE INTO `system_hardware_inventory`', $repositorySource, 'repository must insert idempotently');
if (str_contains(strtoupper($repositorySource), 'ON DUPLICATE KEY UPDATE')) {
    throw new RuntimeException('Hardware inventory must not update an existing first-seen record.');
}

$migrationSource = requireText($root . '/database/migrations/011_system_hardware_inventory.sql');
assertContains('PRIMARY KEY (`systemHWID`)', $migrationSource, 'systemHWID must be the uniqueness boundary');
assertContains('`report` JSON NOT NULL', $migrationSource, 'canonical report JSON must be retained');

fwrite(STDOUT, "Bootstrap hardware contract passed: validation, restricted CORS, privacy-safe systemHWID and insert-once SQL are present.\n");

/** @param array<string, mixed> $payload */
function assertRejected(array $payload, string $expectedCode): void
{
    try {
        (new HardwareReportFactory())->fromArray($payload);
    } catch (HttpException $exception) {
        assertSame($expectedCode, $exception->errorCode(), 'unexpected validation error code');
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
