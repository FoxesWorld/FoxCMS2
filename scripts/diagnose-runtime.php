<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$rootDirectory = dirname(__DIR__);
$skipDatabase = in_array('--no-db', $argv, true);
$failures = 0;
$warnings = 0;

$print = static function (string $status, string $message): void {
    fwrite(STDOUT, sprintf('[%s] %s%s', $status, $message, PHP_EOL));
};
$pass = static function (string $message) use ($print): void {
    $print('OK', $message);
};
$fail = static function (string $message) use ($print, &$failures): void {
    $failures++;
    $print('FAIL', $message);
};
$warn = static function (string $message) use ($print, &$warnings): void {
    $warnings++;
    $print('WARN', $message);
};

$print('INFO', 'FoxCMS runtime diagnostics');
$print('INFO', 'Root: ' . $rootDirectory);
$print('INFO', 'PHP: ' . PHP_VERSION . ' (' . PHP_SAPI . ')');

if (PHP_VERSION_ID >= 80100) {
    $pass('PHP version satisfies >= 8.1.');
} else {
    $fail('PHP 8.1 or newer is required.');
}

foreach (['json', 'pdo', 'pdo_mysql', 'fileinfo', 'mbstring', 'session', 'gd'] as $extension) {
    if (extension_loaded($extension)) {
        $pass('Extension loaded: ' . $extension);
    } else {
        $fail('Missing PHP extension: ' . $extension);
    }
}

$requiredFiles = [
    'index.php',
    'engine/bootstrap.php',
    'engine/Application.class.php',
    'engine/SystemRequests.class.php',
    'engine/classes/config/AppConfigFactory.class.php',
    'engine/classes/http/NetworkContext.class.php',
    'engine/classes/http/SecurityHeaders.class.php',
    'engine/classes/identity/UserIdentityException.class.php',
    'engine/classes/identity/Uuid.class.php',
    'engine/classes/security/RememberToken.class.php',
    'engine/classes/services/LauncherSessionService.class.php',
    'engine/classes/services/HealthCheckService.class.php',
    'engine/classes/database/MigrationRunner.class.php',
    'engine/classes/syslib/database.php',
    'engine/classes/support/RuntimeErrorHandler.class.php',
    'engine/data/environment.php',
    'engine/data/config.php',
    'engine/data/modules.json',
    'api/health.php',
    'scripts/migrate.php',
    'database/schema-000.sql',
    'database/migrations/001_create_anti_brute.sql',
    'database/migrations/002_harden_launcher_sessions.sql',
    'database/migrations/003_uuid_user_identity.sql',
    'database/migrations/004_repair_legacy_schema.sql',
    'database/migrations/005_enforce_profile_runtime_fields.sql',
    'database/migrations/010_badge_claim_keys.sql',
    'database/migrations/013_rules_expert_badge.sql',
    'database/migrations/014_rules_expert_claim_key.sql',
    'database/migrations/015_consolidate_user_badges.sql',
    'database/migrations/016_revoke_public_badge_claim_key.sql',
    'database/migrations/017_public_badge_claim_access.sql',
    'database/migrations/018_expand_server_image_column.sql',
    'database/repair-legacy-schema.sql',
    'scripts/migrate-user-storage.php',
];
foreach ($requiredFiles as $relativePath) {
    if (is_file($rootDirectory . DIRECTORY_SEPARATOR . $relativePath)) {
        $pass('File present: ' . $relativePath);
    } else {
        $fail('Required file missing: ' . $relativePath);
    }
}

foreach ([
    'frontend',
    'engine/init.php',
    'engine/initHelper.php',
    'engine/RequestHandler.class.php',
    'engine/data/frontend.json',
    'engine/classes/services/system-requests',
    'engine/classes/utils/inDirScanner',
    'engine/classes/utils/UserUpload',
] as $relativePath) {
    if (file_exists($rootDirectory . DIRECTORY_SEPARATOR . $relativePath)) {
        $fail('Removed legacy path still exists: ' . $relativePath);
    } else {
        $pass('Removed legacy path absent: ' . $relativePath);
    }
}

require_once $rootDirectory . '/engine/data/environment.php';
$envPath = $rootDirectory . '/.env';
if (is_file($envPath) && is_readable($envPath)) {
    $pass('.env exists and is readable.');
} elseif (is_file($envPath)) {
    $fail('.env exists but is not readable by the current user.');
} else {
    $warn('.env is missing; only process/PHP-FPM environment values will be used.');
}

try {
    foxLoadEnv($envPath);
} catch (Throwable $exception) {
    $fail('.env parsing failed: ' . $exception->getMessage());
}

if (!defined('FOXXEY')) {
    define('FOXXEY', true);
}
require_once $rootDirectory . '/engine/classes/http/NetworkContext.class.php';
$GLOBALS['foxNetworkContext'] = NetworkContext::fromGlobals([]);
require_once $rootDirectory . '/engine/data/const.php';

try {
    $config = require $rootDirectory . '/engine/data/config.php';
    if (!is_array($config)) {
        throw new RuntimeException('Configuration did not return an array.');
    }
    $pass('Configuration loaded and validated.');
} catch (Throwable $exception) {
    $fail('Configuration validation failed: ' . $exception->getMessage());
    $config = [];
}

$environmentName = (string)($config['environment']['name'] ?? '');
if ($environmentName === 'production' && ($config['environment']['debug'] ?? false) === true) {
    $warn('FOXESCRAFT_DEBUG is enabled in production.');
}
$publicBaseUrl = (string)($config['environment']['publicBaseUrl'] ?? '');
if ($publicBaseUrl === '') {
    $warn('FOXESCRAFT_PUBLIC_BASE_URL is empty; authlib will derive its origin from HTTP headers.');
} else {
    $pass('Public base URL configured.');
}

$theme = (string)($config['siteSettings']['siteTpl'] ?? '');
$themeRoot = $rootDirectory . '/templates/' . $theme;
foreach (['theme.json', 'frontend.json', 'index.html', 'assets/runtime/theme.css', 'assets/runtime/theme.js'] as $relativePath) {
    if ($theme !== '' && is_file($themeRoot . '/' . $relativePath)) {
        $pass('Theme file present: templates/' . $theme . '/' . $relativePath);
    } else {
        $fail('Theme file missing: templates/' . $theme . '/' . $relativePath);
    }
}

foreach ([
    'logs' => $rootDirectory . '/engine/cache/logs',
    'cache' => $rootDirectory . '/engine/cache/tmp',
    'uploads' => $rootDirectory . '/uploads',
] as $label => $directory) {
    if (!is_dir($directory) && !@mkdir($directory, 0775, true)) {
        $fail('Cannot create runtime directory: ' . $directory);
        continue;
    }
    if (!is_writable($directory)) {
        $fail('Runtime directory is not writable: ' . $directory);
        continue;
    }
    $probe = $directory . '/.write-probe-' . getmypid();
    if (@file_put_contents($probe, 'ok', LOCK_EX) === false) {
        $fail('Runtime directory rejected a write test: ' . $directory);
        continue;
    }
    @unlink($probe);
    $pass('Runtime directory is writable: ' . $label);
}

if ($skipDatabase) {
    $warn('Database and migration checks skipped by --no-db.');
} elseif (!extension_loaded('pdo_mysql')) {
    $fail('Database checks cannot run because pdo_mysql is missing.');
} else {
    require_once $rootDirectory . '/engine/classes/syslib/database.php';
    require_once $rootDirectory . '/engine/classes/database/MigrationRunner.class.php';
    $databaseConfig = is_array($config['database'] ?? null) ? $config['database'] : [];

    try {
        $database = new db(
            (string)($databaseConfig['dbUser'] ?? ''),
            (string)($databaseConfig['dbPass'] ?? ''),
            (string)($databaseConfig['dbName'] ?? ''),
            (string)($databaseConfig['dbHost'] ?? '127.0.0.1'),
            (int)($databaseConfig['dbPort'] ?? 3306),
            (string)($databaseConfig['dbCharset'] ?? 'utf8mb4'),
            (int)($databaseConfig['connectTimeout'] ?? 5),
        );
        $statement = $database->query('SELECT 1');
        if (!$statement instanceof PDOStatement || (int)$statement->fetchColumn() !== 1) {
            throw new RuntimeException('SELECT 1 returned an unexpected result.');
        }
        $pass('MariaDB/MySQL connection and SELECT 1 succeeded.');

        $requiredSchema = [
            'users' => ['user_id', 'login', 'uuid', 'password', 'email', 'groupTag', 'realname', 'reg_date', 'last_date', 'profilePhoto', 'logged_ip', 'reg_ip', 'userStatus', 'land', 'colorScheme', 'token', 'units', 'balance', 'badges', 'serversOnline', 'userPerms'],
            'groupAssociation' => ['id', 'groupTag', 'groupName', 'groupColor'],
            'regCodes' => ['id', 'name', 'code', 'groupTag'],
            'servers' => ['id', 'serverName', 'host', 'port', 'ignoreDirs', 'enabled', 'checkLib', 'serverGroups', 'serverDescription', 'serverVersion', 'jreVersion', 'serverImage', 'modsInfo', 'mainClass', 'forgeVersion', 'client', 'mcpVersion', 'forgeGroup'],
            'infobox' => ['id', 'group_name', 'start_timestamp', 'end_timestamp', 'title', 'text', 'image', 'button_text', 'button_url'],
            'badgesList' => ['id', 'badgeName', 'description', 'img'],
            'rewardDefinitions' => ['id', 'rewardName', 'description', 'badgeId', 'currencyCode', 'currencyAmount', 'enabled', 'createdAt', 'updatedAt'],
            'rewardClaimKeys' => ['id', 'rewardId', 'tokenHash', 'tokenHint', 'usageMode', 'accessMode', 'publicPlacement', 'usesCount', 'enabled', 'createdAt', 'updatedAt', 'createdByUuid'],
            'rewardClaims' => ['id', 'rewardId', 'keyId', 'userUuid', 'badgeGranted', 'badgeId', 'badgeName', 'currencyCode', 'currencyAmount', 'claimedAt'],
            'antiBrute' => ['id', 'time', 'recordTime', 'ip', 'attempts'],
            'usersession' => ['id', 'userUuid', 'serverId', 'accessToken', 'expiresAt', 'updatedAt'],
            'password_reset_tokens' => ['userUuid', 'tokenHash', 'expiresAt', 'createdAt'],
            'user_hardware_reports' => ['userUuid', 'cpuIdHash', 'cpu', 'gpus', 'payload', 'updatedAt'],
        ];
        foreach ($requiredSchema as $table => $requiredColumns) {
            $columnQuery = $database->prepare(
                'SELECT `COLUMN_NAME` FROM information_schema.COLUMNS '
                . 'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table'
            );
            $columnQuery->execute([':table' => $table]);
            $presentColumns = array_map('strval', $columnQuery->fetchAll(PDO::FETCH_COLUMN));
            if ($presentColumns === []) {
                $fail('Required table is missing: ' . $table);
                continue;
            }
            $pass('Required table exists: ' . $table);
            foreach (array_diff($requiredColumns, $presentColumns) as $missingColumn) {
                $fail('Required column is missing: ' . $table . '.' . $missingColumn);
            }
        }

        $serverImageColumn = $database->getRow(
            "SELECT `DATA_TYPE`, `CHARACTER_MAXIMUM_LENGTH` FROM information_schema.COLUMNS "
            . "WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'servers' "
            . "AND COLUMN_NAME = 'serverImage' LIMIT 1"
        );
        $serverImageType = strtolower((string)($serverImageColumn['DATA_TYPE'] ?? ''));
        $serverImageLength = (int)($serverImageColumn['CHARACTER_MAXIMUM_LENGTH'] ?? 0);
        if (in_array($serverImageType, ['text', 'mediumtext', 'longtext'], true)
            || $serverImageLength >= 512) {
            $pass('servers.serverImage accepts canonical upload paths.');
        } else {
            $fail('servers.serverImage is too short; migration 018 is pending or incomplete.');
        }

        $columnStatement = $database->query(
            "SELECT `COLUMN_NAME`, `CHARACTER_MAXIMUM_LENGTH`, `IS_NULLABLE` "
            . "FROM information_schema.COLUMNS "
            . "WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'usersession'"
        );
        $columns = [];
        if ($columnStatement instanceof PDOStatement) {
            foreach ($columnStatement->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $columns[(string)$row['COLUMN_NAME']] = $row;
            }
        }
        $userColumnsStatement = $database->query(
            "SELECT `COLUMN_NAME`, `COLUMN_TYPE`, `IS_NULLABLE` FROM information_schema.COLUMNS "
            . "WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME IN ('uuid', 'login')"
        );
        $userColumns = [];
        if ($userColumnsStatement instanceof PDOStatement) {
            foreach ($userColumnsStatement->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $userColumns[(string)$row['COLUMN_NAME']] = $row;
            }
        }
        $uuidColumn = $userColumns['uuid'] ?? [];
        if (str_starts_with(strtolower((string)($uuidColumn['COLUMN_TYPE'] ?? '')), 'char(36)')
            && ($uuidColumn['IS_NULLABLE'] ?? 'YES') === 'NO') {
            $pass('users.uuid is a strict canonical UUID identity column.');
        } else {
            $fail('users.uuid must be non-null CHAR(36); migration 003 is pending or incomplete.');
        }

        $identityRowsStatement = $database->query(
            "SELECT "
            . "SUM(CASE WHEN `uuid` IS NULL OR ("
            . "`uuid` NOT REGEXP '^[0-9a-fA-F]{32}$' AND "
            . "`uuid` NOT REGEXP '^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}$'"
            . ") THEN 1 ELSE 0 END) AS `invalidRows`, "
            . "SUM(CASE WHEN `uuid` REGEXP '^[0-9a-fA-F]{32}$' OR ("
            . "`uuid` REGEXP '^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}$' AND "
            . "`uuid` NOT REGEXP '^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[1-8][0-9a-fA-F]{3}-[89aAbB][0-9a-fA-F]{3}-[0-9a-fA-F]{12}$'"
            . ") THEN 1 ELSE 0 END) AS `legacyRows` FROM `users`"
        );
        $identityRows = $identityRowsStatement instanceof PDOStatement
            ? $identityRowsStatement->fetch(PDO::FETCH_ASSOC)
            : false;
        $invalidUuidRows = is_array($identityRows) ? (int)($identityRows['invalidRows'] ?? 0) : -1;
        $legacyUuidRows = is_array($identityRows) ? (int)($identityRows['legacyRows'] ?? 0) : -1;
        if ($invalidUuidRows === 0) {
            $pass('All users contain parseable 128-bit identities.');
        } else {
            $fail('Invalid users.uuid rows: ' . $invalidUuidRows . '. Run database repair before authentication.');
        }
        if ($legacyUuidRows === 0) {
            $pass('All user identities are RFC-compatible UUIDs.');
        } else {
            $fail('Legacy users.uuid rows remain: ' . $legacyUuidRows . '. Run migrations 003/004.');
        }
        if (isset($columns['userUuid'])) {
            $pass('Launcher sessions are keyed by userUuid.');
        } else {
            $fail('usersession.userUuid is missing; migration 003 is pending or incomplete.');
        }

        if ((int)($columns['accessToken']['CHARACTER_MAXIMUM_LENGTH'] ?? 0) === 64) {
            $pass('Launcher accessToken column stores SHA-256 digests.');
        } else {
            $fail('usersession.accessToken must be CHAR(64); migration 002 is pending or incomplete.');
        }
        foreach (['expiresAt', 'updatedAt'] as $column) {
            if (isset($columns[$column])) {
                $pass('Launcher session column exists: ' . $column);
            } else {
                $fail('Launcher session column missing: ' . $column);
            }
        }

        $runner = new MigrationRunner($database, $rootDirectory . '/database/migrations');
        $migrationStatus = $runner->status();
        if ($migrationStatus['repository'] !== true) {
            $fail('Migration repository is missing. Run scripts/migrate.php as the schema owner.');
        } elseif ($migrationStatus['pending'] !== []) {
            $fail('Pending migrations: ' . implode(', ', $migrationStatus['pending']));
        } else {
            $pass('Database migrations are current: ' . ($migrationStatus['latestApplied'] ?? 'none'));
        }

        try {
            $grantsStatement = $database->query('SHOW GRANTS');
            $grants = $grantsStatement instanceof PDOStatement
                ? implode("\n", array_map(
                    static fn (array $row): string => implode(' ', array_map('strval', $row)),
                    $grantsStatement->fetchAll(PDO::FETCH_ASSOC),
                ))
                : '';
            if (preg_match('/GRANT\s+ALL PRIVILEGES|\b(?:CREATE|ALTER|DROP)\b/i', $grants) === 1) {
                $warn('Runtime database user appears to have schema-changing privileges. Use a separate migration account.');
            } else {
                $pass('Runtime database grants do not advertise schema-changing privileges.');
            }
        } catch (Throwable) {
            $warn('Unable to inspect database grants.');
        }
    } catch (Throwable $exception) {
        $fail('Database diagnostics failed: ' . $exception->getMessage());
    }
}

$print('INFO', sprintf('Completed with %d failure(s) and %d warning(s).', $failures, $warnings));
exit($failures === 0 ? 0 : 1);
