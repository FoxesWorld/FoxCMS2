<?php

declare(strict_types=1);

final class HealthCheckService
{
    private const REQUIRED_SCHEMA = [
        'users' => [
            'user_id', 'login', 'uuid', 'password', 'email', 'groupTag', 'realname',
            'reg_date', 'last_date', 'profilePhoto', 'logged_ip', 'reg_ip', 'userStatus',
            'land', 'colorScheme', 'token', 'units', 'balance', 'badges', 'serversOnline',
            'userPerms',
        ],
        'groupAssociation' => ['id', 'groupTag', 'groupName', 'groupColor'],
        'regCodes' => ['id', 'name', 'code', 'groupTag'],
        'servers' => [
            'id', 'serverName', 'host', 'port', 'ignoreDirs', 'enabled', 'checkLib',
            'serverGroups', 'serverDescription', 'serverVersion', 'jreVersion',
            'serverImage', 'modsInfo', 'mainClass', 'forgeVersion', 'client',
            'mcpVersion', 'forgeGroup',
        ],
        'infobox' => [
            'id', 'group_name', 'start_timestamp', 'end_timestamp', 'title', 'text',
            'image', 'button_text', 'button_url',
        ],
        'badgesList' => ['id', 'badgeName', 'description', 'img'],
        'rewardDefinitions' => ['id', 'rewardName', 'description', 'badgeId', 'currencyCode', 'currencyAmount', 'enabled', 'createdAt', 'updatedAt'],
        'rewardClaimKeys' => ['id', 'rewardId', 'tokenHash', 'tokenHint', 'usageMode', 'accessMode', 'publicPlacement', 'usesCount', 'enabled', 'createdAt', 'updatedAt', 'createdByUuid'],
        'rewardClaims' => ['id', 'rewardId', 'keyId', 'userUuid', 'badgeGranted', 'badgeId', 'badgeName', 'currencyCode', 'currencyAmount', 'claimedAt'],
        'userNotifications' => ['id', 'userUuid', 'notificationType', 'severity', 'title', 'message', 'actionUrl', 'payload', 'dedupeKey', 'createdAt', 'readAt'],
        'userBrowserSessions' => ['id', 'sessionUuid', 'userUuid', 'rememberDigest', 'sessionType', 'ipAddress', 'userAgent', 'browser', 'operatingSystem', 'deviceLabel', 'locationLabel', 'createdAt', 'lastSeenAt', 'expiresAt', 'idleExpiresAt', 'revokedAt'],
        'gameAchievements' => ['id', 'serverId', 'gameCode', 'achievementKey', 'achievementType', 'parentKey', 'title', 'description', 'frameType', 'category', 'iconBase64', 'iconMime', 'iconItem', 'iconComponents', 'criteria', 'requirements', 'points', 'hidden', 'announceToChat', 'showToast', 'enabled', 'definitionHash', 'catalogRevision', 'createdAt', 'updatedAt', 'lastSeenAt'],
        'playerAchievements' => ['id', 'serverId', 'playerUuid', 'playerName', 'achievementId', 'progress', 'target', 'completed', 'firstProgressAt', 'completedAt', 'updatedAt'],
        'gameAchievementEvents' => ['id', 'eventUuid', 'serverId', 'playerUuid', 'achievementKey', 'eventType', 'payload', 'occurredAt', 'receivedAt'],
        'antiBrute' => ['id', 'time', 'recordTime', 'ip', 'attempts'],
        'usersession' => ['id', 'userUuid', 'serverId', 'accessToken', 'expiresAt', 'updatedAt'],
        'password_reset_tokens' => ['userUuid', 'tokenHash', 'expiresAt', 'createdAt'],
        'user_hardware_reports' => ['userUuid', 'cpuIdHash', 'cpu', 'gpus', 'payload', 'updatedAt'],
    ];
    public function __construct(
        private db $database,
        private array $config,
        private string $rootDirectory,
    ) {
        $this->rootDirectory = rtrim($rootDirectory, '/\\');
    }

    /**
     * @return array{ok:bool, service:string, version:string, timestamp:string, checks:array<string, mixed>}
     */
    public function inspect(): array
    {
        $checks = [
            'database' => $this->databaseCheck(),
            'schema' => $this->schemaCheck(),
            'identity' => $this->identityCheck(),
            'migrations' => $this->migrationCheck(),
            'filesystem' => $this->filesystemCheck(),
            'theme' => $this->themeCheck(),
        ];

        $ok = true;
        foreach ($checks as $check) {
            if (!is_array($check) || ($check['ok'] ?? false) !== true) {
                $ok = false;
                break;
            }
        }

        return [
            'ok' => $ok,
            'service' => (string)($this->config['other']['webserviceName'] ?? 'FoxesCraft'),
            'version' => (string)($this->config['siteSettings']['ServiceVersion'] ?? 'unknown'),
            'timestamp' => gmdate('c'),
            'checks' => $checks,
        ];
    }

    private function databaseCheck(): array
    {
        try {
            $value = $this->database->query('SELECT 1');
            return [
                'ok' => $value instanceof PDOStatement && (int)$value->fetchColumn() === 1,
            ];
        } catch (Throwable) {
            return ['ok' => false];
        }
    }

    private function schemaCheck(): array
    {
        $requiredTables = array_keys(self::REQUIRED_SCHEMA);
        $requiredColumnCount = array_sum(array_map('count', self::REQUIRED_SCHEMA));

        try {
            $placeholders = [];
            $parameters = [];
            foreach ($requiredTables as $index => $table) {
                $placeholder = ':table_' . $index;
                $placeholders[] = $placeholder;
                $parameters[$placeholder] = $table;
            }

            $statement = $this->database->prepare(
                'SELECT `TABLE_NAME`, `COLUMN_NAME` FROM information_schema.COLUMNS '
                . 'WHERE `TABLE_SCHEMA` = DATABASE() '
                . 'AND `TABLE_NAME` IN (' . implode(', ', $placeholders) . ')'
            );
            $statement->execute($parameters);

            $present = [];
            foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $table = (string)($row['TABLE_NAME'] ?? '');
                $column = (string)($row['COLUMN_NAME'] ?? '');
                if ($table !== '' && $column !== '') {
                    $present[$table][$column] = true;
                }
            }

            $missingTables = [];
            $missingColumns = [];
            foreach (self::REQUIRED_SCHEMA as $table => $columns) {
                if (!isset($present[$table])) {
                    $missingTables[] = $table;
                    continue;
                }
                foreach ($columns as $column) {
                    if (!isset($present[$table][$column])) {
                        $missingColumns[] = $table . '.' . $column;
                    }
                }
            }

            return [
                'ok' => $missingTables === [] && $missingColumns === [],
                'requiredTables' => count($requiredTables),
                'requiredColumns' => $requiredColumnCount,
                'missingTables' => $missingTables,
                'missingColumns' => $missingColumns,
            ];
        } catch (Throwable) {
            return [
                'ok' => false,
                'requiredTables' => count($requiredTables),
                'requiredColumns' => $requiredColumnCount,
                'missingTables' => $requiredTables,
                'missingColumns' => [],
            ];
        }
    }

    private function identityCheck(): array
    {
        try {
            $statement = $this->database->query(
                "SELECT `TABLE_NAME`, `COLUMN_NAME`, `COLUMN_TYPE`, `IS_NULLABLE` "
                . "FROM information_schema.COLUMNS "
                . "WHERE `TABLE_SCHEMA` = DATABASE() AND ("
                . "(`TABLE_NAME` = 'users' AND `COLUMN_NAME` IN ('uuid', 'login')) OR "
                . "(`TABLE_NAME` = 'usersession' AND `COLUMN_NAME` = 'userUuid') OR "
                . "(`TABLE_NAME` = 'password_reset_tokens' AND `COLUMN_NAME` = 'userUuid') OR "
                . "(`TABLE_NAME` = 'user_hardware_reports' AND `COLUMN_NAME` = 'userUuid'))"
            );
            $columns = [];
            if ($statement instanceof PDOStatement) {
                foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
                    $columns[(string)$row['TABLE_NAME'] . '.' . (string)$row['COLUMN_NAME']] = $row;
                }
            }
            $required = [
                'users.uuid',
                'users.login',
                'usersession.userUuid',
                'password_reset_tokens.userUuid',
                'user_hardware_reports.userUuid',
            ];
            $missing = array_values(array_diff($required, array_keys($columns)));
            $uuidColumn = $columns['users.uuid'] ?? [];
            $uuidStrict = str_starts_with(strtolower((string)($uuidColumn['COLUMN_TYPE'] ?? '')), 'char(36)')
                && ($uuidColumn['IS_NULLABLE'] ?? 'YES') === 'NO';
            $identityRowsStatement = $this->database->query(
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

            $legacyStorageStatement = $this->database->query(
                "SELECT COUNT(*) FROM `users` "
                . "WHERE `login` <> 'anonymous' "
                . "AND `profilePhoto` LIKE CONCAT('/uploads/users/', `login`, '/%')"
            );
            $legacyStorageRows = $legacyStorageStatement instanceof PDOStatement
                ? (int)$legacyStorageStatement->fetchColumn()
                : -1;
            return [
                'ok' => $missing === []
                    && $uuidStrict
                    && $invalidUuidRows === 0
                    && $legacyUuidRows === 0
                    && $legacyStorageRows === 0,
                'missing' => $missing,
                'usersUuidStrict' => $uuidStrict,
                'invalidUuidRows' => $invalidUuidRows,
                'legacyUuidRows' => $legacyUuidRows,
                'legacyStorageRows' => $legacyStorageRows,
            ];
        } catch (Throwable) {
            return [
                'ok' => false,
                'missing' => ['identity-schema'],
                'usersUuidStrict' => false,
                'invalidUuidRows' => -1,
                'legacyUuidRows' => -1,
                'legacyStorageRows' => -1,
            ];
        }
    }

    private function migrationCheck(): array
    {
        try {
            require_once ENGINE_DIR . 'classes/database/MigrationRunner.class.php';
            $runner = new MigrationRunner(
                $this->database,
                $this->rootDirectory . '/database/migrations',
            );
            $status = $runner->status();
            return [
                'ok' => $status['repository'] === true && $status['pending'] === [],
                'latestAvailable' => $status['latestAvailable'],
                'latestApplied' => $status['latestApplied'],
                'pending' => $status['pending'],
            ];
        } catch (Throwable) {
            return [
                'ok' => false,
                'latestAvailable' => null,
                'latestApplied' => null,
                'pending' => ['unknown'],
            ];
        }
    }

    private function filesystemCheck(): array
    {
        $paths = [
            'logs' => $this->rootDirectory . '/engine/cache/logs',
            'cache' => $this->rootDirectory . '/engine/cache/tmp',
            'uploads' => $this->rootDirectory . '/uploads',
        ];
        $result = [];
        $ok = true;
        foreach ($paths as $name => $path) {
            $writable = is_dir($path) && is_writable($path);
            $result[$name] = $writable;
            $ok = $ok && $writable;
        }
        return ['ok' => $ok, 'writable' => $result];
    }

    private function themeCheck(): array
    {
        $theme = (string)($this->config['siteSettings']['siteTpl'] ?? '');
        if (preg_match('/^[A-Za-z0-9][A-Za-z0-9_-]{0,63}$/', $theme) !== 1) {
            return ['ok' => false, 'name' => $theme];
        }

        $themeRoot = $this->rootDirectory . '/templates/' . $theme;
        $required = [
            'theme.json',
            'frontend.json',
            'index.html',
            'assets/runtime/theme.css',
            'assets/runtime/theme.js',
            'userOptions/ProfileSettings.tpl',
            'userOptions/AdminPanel.tpl',
        ];
        $missing = [];
        foreach ($required as $file) {
            if (!is_file($themeRoot . '/' . $file)) {
                $missing[] = $file;
            }
        }
        return [
            'ok' => $missing === [],
            'name' => $theme,
            'missing' => $missing,
        ];
    }
}
