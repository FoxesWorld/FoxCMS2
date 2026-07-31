<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$rootDirectory = dirname(__DIR__);
require_once $rootDirectory . '/engine/data/environment.php';
$environmentFile = getenv('FOXESCRAFT_ENV_FILE');
if (!is_string($environmentFile) || trim($environmentFile) === '') {
    $environmentFile = $rootDirectory . '/.env';
}
foxLoadEnv($environmentFile);

require_once $rootDirectory . '/engine/classes/http/NetworkContext.class.php';
$GLOBALS['foxNetworkContext'] = NetworkContext::fromGlobals([]);

if (!defined('FOXXEY')) {
    define('FOXXEY', true);
}
require_once $rootDirectory . '/engine/data/const.php';
$config = require $rootDirectory . '/engine/data/config.php';
require_once $rootDirectory . '/engine/classes/syslib/database.php';
require_once $rootDirectory . '/engine/classes/database/MigrationRunner.class.php';

$arguments = array_slice($argv, 1);
$dryRun = in_array('--dry-run', $arguments, true);
$statusOnly = in_array('--status', $arguments, true);
$databaseConfig = is_array($config['database'] ?? null) ? $config['database'] : [];

$migrationHost = foxEnv('FOXESCRAFT_MIGRATION_DB_HOST', (string)($databaseConfig['dbHost'] ?? '127.0.0.1'));
$migrationPort = foxEnvInt('FOXESCRAFT_MIGRATION_DB_PORT', (int)($databaseConfig['dbPort'] ?? 3306));
$migrationName = foxEnv('FOXESCRAFT_MIGRATION_DB_NAME', (string)($databaseConfig['dbName'] ?? ''));
$migrationUser = foxEnv('FOXESCRAFT_MIGRATION_DB_USER', (string)($databaseConfig['dbUser'] ?? ''));
$migrationPassword = foxEnv('FOXESCRAFT_MIGRATION_DB_PASSWORD', (string)($databaseConfig['dbPass'] ?? ''));

try {
    $database = new db(
        (string)$migrationUser,
        (string)$migrationPassword,
        (string)$migrationName,
        (string)$migrationHost,
        $migrationPort,
        (string)($databaseConfig['dbCharset'] ?? 'utf8mb4'),
        (int)($databaseConfig['connectTimeout'] ?? 5),
    );
    $runner = new MigrationRunner($database, $rootDirectory . '/database/migrations');

    if ($statusOnly) {
        $status = $runner->status();
        fwrite(
            STDOUT,
            json_encode(
                $status,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
            ) . PHP_EOL,
        );
        exit($status['repository'] === true && $status['pending'] === [] ? 0 : 2);
    }

    $result = $runner->migrate($dryRun);
    $verb = $dryRun ? 'Pending' : 'Applied';
    fwrite(STDOUT, $verb . ' migrations: ' . ($result['applied'] === [] ? 'none' : implode(', ', $result['applied'])) . PHP_EOL);
    fwrite(STDOUT, 'Already current: ' . ($result['skipped'] === [] ? 'none' : implode(', ', $result['skipped'])) . PHP_EOL);
    fwrite(STDOUT, 'Latest available: ' . ($result['latest'] ?? 'none') . PHP_EOL);
    exit(0);
} catch (Throwable $exception) {
    fwrite(STDERR, '[migration-error] ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}
