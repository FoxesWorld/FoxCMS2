<?php

declare(strict_types=1);

use FoxCMS\Api\Bootstrap\ArtifactCatalog;
use FoxCMS\Api\Bootstrap\BootstrapSettings;
use FoxCMS\Api\Bootstrap\HardwareReportFactory;
use FoxCMS\Api\Bootstrap\PublishedArtifactInspector;
use FoxCMS\Api\Bootstrap\Runtime\RuntimeFilesystem;
use FoxCMS\Api\Bootstrap\Runtime\RuntimeMetadata;
use FoxCMS\Api\Bootstrap\Runtime\RuntimePlatform;
use FoxCMS\Api\Bootstrap\Runtime\RuntimeSelection;
use FoxCMS\Api\Core\HttpException;
use FoxCMS\Api\Core\Request;
use FoxCMS\Api\Launcher\RuntimeArchiveLocator;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__) . '/autoload.php';
require_once dirname(__DIR__, 2) . '/engine/classes/services/RuntimeJdkCatalog.class.php';

$routeRequest = new Request(
    ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/api/game/achievements/player'],
    [],
    [],
);
assertSame('/game/achievements/player', $routeRequest->apiRoute(), 'API prefix must be removed from route');

$explicitRoute = new Request(
    ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/ignored', 'FOX_API_ROUTE' => '/game/achievements/event'],
    [],
    [],
);
assertSame('/game/achievements/event', $explicitRoute->apiRoute(), 'Explicit endpoint route must win');

assertSame('17.0.16+8', RuntimeMetadata::runtimeNormalizeVersion('17.0.16+8'), 'Runtime version must normalize');
assertSame(
    'windows-x86_64',
    RuntimeJdkCatalog::normalizePlatform('windows-amd64'),
    'Engine RuntimeJdkCatalog must load the namespaced runtime layer',
);
assertSame(8, RuntimeMetadata::runtimeMajorFromVersion('8u402'), 'Legacy Java major must normalize');
assertSame(true, RuntimeFilesystem::isRuntimeArchiveName('jdk-17.0.16.zip'), 'ZIP runtime must be accepted');
assertSame(false, RuntimeFilesystem::isRuntimeArchiveName('jdk-17.zip.part'), 'Partial runtime must be rejected');
assertSame(
    'windows-x86_64',
    RuntimePlatform::platformFromJavaRelease(['OS_NAME' => 'Windows', 'OS_ARCH' => 'amd64']),
    'Java release platform must normalize',
);
$compatibility = RuntimeSelection::evaluateRuntimeCompatibility([
    'platform' => 'windows-x86_64',
    'version_core' => '17.0.16',
    'java_major' => 17,
    'distribution' => 'jdk',
    'stable' => true,
], [
    'platform' => 'windows-x86_64',
    'version' => '17',
    'version_mode' => 'major',
    'java_major' => 17,
    'distribution' => 'any',
    'allow_prerelease' => false,
]);
assertSame(true, $compatibility['compatible'], 'Compatible runtime candidate must be accepted');

$report = (new HardwareReportFactory())->fromArray([
    'schemaVersion' => 1,
    'systemHWID' => str_repeat('b', 64),
    'platform' => 'linux-x86_64',
    'updaterVersion' => '1.0.0',
    'systemInformation' => [
        'os' => ['name' => 'linux', 'version' => 'test', 'kernel' => 'test', 'architecture' => 'x86_64'],
        'cpu' => ['brand' => 'Test CPU', 'logicalCores' => 8],
        'memory' => ['totalBytes' => 17179869184],
        'gpu' => ['adapters' => ['GPU 1', 'GPU 1', 'GPU 2']],
    ],
]);
assertSame(['GPU 1', 'GPU 2'], $report->gpuAdapters(), 'Hardware adapters must be de-duplicated');

$temporaryRoot = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'foxcms-api-' . bin2hex(random_bytes(6));
$storage = $temporaryRoot . DIRECTORY_SEPARATOR . 'bootstrap-storage';
$bootstrapDirectory = $storage . DIRECTORY_SEPARATOR . 'bootstrapper' . DIRECTORY_SEPARATOR . 'windows-x86_64' . DIRECTORY_SEPARATOR . '1.2.0';
$launcherDirectory = $storage . DIRECTORY_SEPARATOR . 'launcher' . DIRECTORY_SEPARATOR . '2.0.0';
mkdir($bootstrapDirectory, 0750, true);
mkdir($launcherDirectory, 0750, true);
file_put_contents($bootstrapDirectory . DIRECTORY_SEPARATOR . 'FoxesCraft.exe', 'bootstrapper-binary');
file_put_contents($launcherDirectory . DIRECTORY_SEPARATOR . 'launcher.jar', 'launcher-binary');

try {
    $settings = new BootstrapSettings([
        'storage_directory' => $storage,
        'cache_max_age' => 60,
        'launcher_jvm_args' => ['-Xmx2g'],
        'launcher_args' => [],
    ]);
    assertSame($storage, $settings->storageDirectory(), 'Absolute storage path must be preserved');

    $catalog = new ArtifactCatalog($storage);
    $bootstrapper = $catalog->discoverBootstrapper('windows-x86_64');
    assertSame('1.2.0', $bootstrapper['version'], 'Newest bootstrapper version must be selected');
    assertSame(hash('sha256', 'bootstrapper-binary'), $bootstrapper['artifact']['sha256'], 'Artifact hash must be calculated');

    $launcher = $catalog->discoverLauncher();
    assertSame('2.0.0', $launcher['version'], 'Newest launcher version must be selected');

    $outside = $temporaryRoot . DIRECTORY_SEPARATOR . 'outside.bin';
    file_put_contents($outside, 'outside');
    assertHttpError(
        static fn () => (new PublishedArtifactInspector($storage))->describe($outside),
        'bootstrap_artifact_unsafe',
    );
    assertHttpError(
        static fn () => (new RuntimeArchiveLocator())->resolve($storage, '../outside.bin'),
        'runtime_path_unsafe',
    );
} finally {
    removeTree($temporaryRoot);
}

fwrite(STDOUT, "FoxCMS API architecture smoke test passed.
");

function assertSame(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message . '; expected=' . var_export($expected, true) . '; actual=' . var_export($actual, true));
    }
}

/** @param callable(): mixed $operation */
function assertHttpError(callable $operation, string $expectedCode): void
{
    try {
        $operation();
    } catch (HttpException $error) {
        assertSame($expectedCode, $error->errorCode(), 'Unexpected HTTP error code');
        return;
    }
    throw new RuntimeException('Expected HttpException with code ' . $expectedCode);
}

function removeTree(string $path): void
{
    if (!is_dir($path)) {
        @unlink($path);
        return;
    }
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST,
    );
    foreach ($iterator as $entry) {
        if ($entry->isDir()) {
            rmdir($entry->getPathname());
        } else {
            unlink($entry->getPathname());
        }
    }
    rmdir($path);
}
