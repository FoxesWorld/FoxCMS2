<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$rootDirectory = dirname(__DIR__);
$dryRun = in_array('--dry-run', $argv, true);

require_once $rootDirectory . '/engine/data/environment.php';
$environmentFile = getenv('FOXESCRAFT_ENV_FILE');
if (!is_string($environmentFile) || trim($environmentFile) === '') {
    $environmentFile = $rootDirectory . '/.env';
}
foxLoadEnv($environmentFile);
if (!defined('FOXXEY')) {
    define('FOXXEY', true);
}
require_once $rootDirectory . '/engine/classes/http/NetworkContext.class.php';
$GLOBALS['foxNetworkContext'] = NetworkContext::fromGlobals([]);
require_once $rootDirectory . '/engine/data/const.php';
require_once $rootDirectory . '/engine/classes/identity/Uuid.class.php';
require_once $rootDirectory . '/engine/classes/syslib/database.php';
$config = require $rootDirectory . '/engine/data/config.php';
$databaseConfig = $config['database'] ?? [];

$database = new db(
    (string)($databaseConfig['dbUser'] ?? ''),
    (string)($databaseConfig['dbPass'] ?? ''),
    (string)($databaseConfig['dbName'] ?? ''),
    (string)($databaseConfig['dbHost'] ?? '127.0.0.1'),
    (int)($databaseConfig['dbPort'] ?? 3306),
    (string)($databaseConfig['dbCharset'] ?? 'utf8mb4'),
    (int)($databaseConfig['connectTimeout'] ?? 5),
);

$statement = $database->query('SELECT `uuid`, `login`, `profilePhoto` FROM `users` ORDER BY `user_id`');
if (!$statement instanceof PDOStatement) {
    throw new RuntimeException('Unable to read users.');
}

$moved = 0;
$profileUpdates = 0;
foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $user) {
    $storedUuid = Uuid::normalize((string)$user['uuid']);
    $userUuid = Uuid::canonical($storedUuid);
    $profileId = Uuid::compact($userUuid);
    $textureId = $userUuid;
    $login = (string)$user['login'];
    if (preg_match('/^[A-Za-z0-9_.-]{1,64}$/D', $login) !== 1) {
        fwrite(STDERR, '[skip] unsafe legacy login for ' . $userUuid . PHP_EOL);
        continue;
    }

    $uuidDirectory = $rootDirectory . '/uploads/users/' . $userUuid;
    if (!$dryRun && !is_dir($uuidDirectory) && !mkdir($uuidDirectory, 0750, true) && !is_dir($uuidDirectory)) {
        throw new RuntimeException('Unable to create ' . $uuidDirectory);
    }

    $legacyStem = md5($login);
    $sources = [
        $rootDirectory . '/uploads/users/' . $login => [
            $legacyStem . '-skin.png' => $textureId . '-skin.png',
            $legacyStem . '-cape.png' => $textureId . '-cape.png',
        ],
        $rootDirectory . '/uploads/users/' . $profileId => [
            $profileId . '-skin.png' => $textureId . '-skin.png',
            $profileId . '-cape.png' => $textureId . '-cape.png',
        ],
    ];
    $canonicalRenames = [
        $profileId . '-skin.png' => $textureId . '-skin.png',
        $profileId . '-cape.png' => $textureId . '-cape.png',
    ];
    foreach ($canonicalRenames as $sourceName => $targetName) {
        if ($sourceName === $targetName) {
            continue;
        }
        $source = $uuidDirectory . '/' . $sourceName;
        $target = $uuidDirectory . '/' . $targetName;
        if (!is_file($source) || is_file($target)) {
            continue;
        }
        fwrite(STDOUT, sprintf('[rename] %s -> %s%s', $source, $target, PHP_EOL));
        if (!$dryRun && !rename($source, $target)) {
            throw new RuntimeException('Unable to rename ' . $source);
        }
        $moved++;
    }

    foreach ($sources as $sourceDirectory => $renames) {
        if ($sourceDirectory === $uuidDirectory || !is_dir($sourceDirectory)) {
            continue;
        }
        foreach (new DirectoryIterator($sourceDirectory) as $entry) {
            if (!$entry->isFile() || $entry->isLink()) {
                continue;
            }
            $sourceName = $entry->getFilename();
            $targetName = $renames[$sourceName] ?? $sourceName;
            $source = $entry->getPathname();
            $target = $uuidDirectory . '/' . $targetName;
            if (is_file($target)) {
                fwrite(STDOUT, '[exists] ' . $target . PHP_EOL);
                continue;
            }
            fwrite(STDOUT, sprintf('[move] %s -> %s%s', $source, $target, PHP_EOL));
            if (!$dryRun && !rename($source, $target)) {
                throw new RuntimeException('Unable to move ' . $source);
            }
            $moved++;
        }
        if (!$dryRun) {
            @rmdir($sourceDirectory);
        }
    }

    $profilePhoto = str_replace('\\', '/', (string)($user['profilePhoto'] ?? ''));
    $profileName = basename($profilePhoto);
    $legacyPrefixes = [
        '/uploads/users/' . $login . '/',
        '/uploads/users/' . $profileId . '/',
        '/uploads/users/' . $storedUuid . '/',
    ];
    $usesLegacyProfilePath = false;
    foreach (array_unique($legacyPrefixes) as $legacyPrefix) {
        if ($legacyPrefix !== '/uploads/users/' . $userUuid . '/' && str_starts_with($profilePhoto, $legacyPrefix)) {
            $usesLegacyProfilePath = true;
            break;
        }
    }
    if ($usesLegacyProfilePath && $profileName !== '' && is_file($uuidDirectory . '/' . $profileName)) {
        $newProfilePhoto = '/uploads/users/' . $userUuid . '/' . $profileName;
        fwrite(STDOUT, sprintf('[profile] %s -> %s%s', $profilePhoto, $newProfilePhoto, PHP_EOL));
        if (!$dryRun) {
            $update = $database->prepare(
                'UPDATE `users` SET `profilePhoto` = :profilePhoto WHERE `uuid` = :userUuid'
            );
            $update->execute([
                ':profilePhoto' => $newProfilePhoto,
                ':userUuid' => $storedUuid,
            ]);
        }
        $profileUpdates++;
    }
}

fwrite(STDOUT, sprintf(
    '%s storage migration: %d file(s), %d profile path(s).%s',
    $dryRun ? 'Planned' : 'Completed',
    $moved,
    $profileUpdates,
    PHP_EOL,
));
