<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

use FoxCMS\Api\Bootstrap\BootstrapCorsPolicy;
use FoxCMS\Api\Bootstrap\Runtime\RuntimeRequest;
use FoxCMS\Api\Core\HttpException;
use FoxCMS\Engine\Monitoring\MonitoringRecordStore;
use FoxCMS\Shared\Error\ThrowableDiagnostic;
use FoxCMS\Shared\Http\ResponseHeaders;

$root = dirname(__DIR__);
require_once $root . '/autoload.php';
if (!defined('FOXXEY')) {
    define('FOXXEY', true);
}
require_once $root . '/engine/autoload.php';
require_once $root . '/engine/classes/syslib/database.php';
require_once $root . '/engine/classes/syslib/syslog';

$request = new HttpRequest([], [], [], [], ['REQUEST_METHOD' => 'GET']);
$database = (new ReflectionClass(db::class))->newInstanceWithoutConstructor();
$logger = (new ReflectionClass(Logger::class))->newInstanceWithoutConstructor();
$sessionReflection = new ReflectionClass(UserSession::class);
$session = $sessionReflection->newInstanceWithoutConstructor();
foreach ([
    'user' => ['isLogged' => false, 'uuid' => '', 'login' => 'anonymous'],
    'idleSeconds' => 7200,
    'absoluteSeconds' => 86400,
] as $propertyName => $propertyValue) {
    $property = $sessionReflection->getProperty($propertyName);
    $property->setValue($session, $propertyValue);
}
(new FoxCMS\Engine\Auth\AuthSessionLifecycle(
    $database,
    $logger,
    $request,
    $session,
    [],
    'userToken',
))->restoreSafely();

$diagnostic = ThrowableDiagnostic::payload(
    new RuntimeException('Visible database failure password=hunter2 at ' . $root . '/engine/test.php'),
    'request-123',
    $root,
);
assertSame(true, $diagnostic['fatal'] ?? null, 'fatal diagnostic flag');
assertSame(RuntimeException::class, $diagnostic['exception'] ?? null, 'fatal diagnostic exception class');
assertSame(
    'Visible database failure password=[redacted] at [project]/engine/test.php',
    $diagnostic['message'] ?? null,
    'fatal diagnostic sanitization',
);
assertSame('request-123', $diagnostic['requestId'] ?? null, 'fatal diagnostic request ID');

ResponseHeaders::validate('X-Request-ID', 'request-123');
assertThrows(
    static fn (): null => invalidHeaderName(),
    InvalidArgumentException::class,
    'invalid response header name must be rejected',
);
assertThrows(
    static fn (): null => invalidHeaderValue(),
    InvalidArgumentException::class,
    'CRLF response header value must be rejected',
);
assertThrows(
    static fn (): null => invalidStatus(),
    InvalidArgumentException::class,
    'invalid response status must be rejected',
);

assertSame('https://example.com', BootstrapCorsPolicy::normalizeOrigin('https://Example.COM:443/'), 'origin canonicalization');
assertSame('', BootstrapCorsPolicy::normalizeOrigin('https://example.com/path'), 'origin path rejection');

$majorRequest = RuntimeRequest::fromArray([
    'platform' => 'linux-x86_64',
    'version' => '17',
    'distribution' => 'jdk',
    'allow_prerelease' => 'false',
    'client_version' => 'contract-test',
]);
assertSame('major', $majorRequest['version_mode'] ?? null, 'major runtime request mode');
assertSame(17, $majorRequest['java_major'] ?? null, 'runtime Java major');
assertThrows(
    static fn (): array => RuntimeRequest::fromArray(['platform' => '../windows', 'version' => '17']),
    HttpException::class,
    'unsafe runtime platform must be rejected',
);

$temporary = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'foxcms-security-' . bin2hex(random_bytes(6));
if (!mkdir($temporary, 0700, true) && !is_dir($temporary)) {
    throw new RuntimeException('Unable to create security test directory.');
}
try {
    $store = new MonitoringRecordStore([
        'absoluteRecordPath' => $temporary . DIRECTORY_SEPARATOR . 'all.record',
        'dayRecordPath' => $temporary . DIRECTORY_SEPARATOR . 'day.record',
    ]);
    assertSame(4, $store->updateAbsolute(4), 'initial absolute monitoring record');
    assertSame(4, $store->updateAbsolute(2), 'monitoring record must not decrease');
    assertSame(9, $store->updateAbsolute(9), 'monitoring record must increase');
    assertSame(3, $store->updateDay(3), 'daily monitoring record');
    assertSame(['all' => 9, 'day' => 3], $store->load(), 'monitoring record persistence');
} finally {
    foreach (glob($temporary . DIRECTORY_SEPARATOR . '*') ?: [] as $file) {
        @unlink($file);
    }
    @rmdir($temporary);
}

fwrite(STDOUT, "Security boundary runtime tests passed.\n");

function invalidHeaderName(): null
{
    ResponseHeaders::validate("X-Test\r\nInjected", 'value');
    return null;
}

function invalidHeaderValue(): null
{
    ResponseHeaders::validate('X-Test', "value\r\nInjected: yes");
    return null;
}

function invalidStatus(): null
{
    ResponseHeaders::validateStatus(700);
    return null;
}

function assertSame(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message . '; expected=' . var_export($expected, true) . '; actual=' . var_export($actual, true));
    }
}

/** @param class-string<Throwable> $expected */
function assertThrows(callable $operation, string $expected, string $message): void
{
    try {
        $operation();
    } catch (Throwable $error) {
        if ($error instanceof $expected) {
            return;
        }
        throw new RuntimeException($message . '; unexpected=' . $error::class, 0, $error);
    }
    throw new RuntimeException($message . '; no exception thrown');
}
