<?php

declare(strict_types=1);

/**
 * Administrative maintenance boundary for the game-achievement subsystem.
 *
 * Destructive operations deliberately live outside the public game API. The
 * AdminPanel bootstrap supplies the admin-session and CSRF boundary before any
 * method in this controller can be invoked.
 */
final class AdminAchievementController
{
    private const SERVER_ID_PATTERN = '/^[a-z0-9][a-z0-9._-]{2,99}$/D';
    private const REQUIRED_TABLES = ['gameAchievements', 'playerAchievements', 'gameAchievementEvents'];

    public function __construct(
        private db $db,
        private array $request,
        private UserSession $session,
        private Logger $logger,
        private AdminResponder $responder,
    ) {
    }

    public function overview(): void
    {
        if (!$this->schemaReady()) {
            $this->responder->send([
                'available' => false,
                'servers' => [],
                'players' => [],
                'selectedServerId' => '',
                'message' => 'Схема игровых достижений недоступна. Примените миграцию 025_game_achievements.sql.',
            ]);
        }

        $servers = $this->serverStats();
        $requestedServerId = trim((string)($this->request['serverId'] ?? ''));
        $knownServerIds = array_map(static fn (array $row): string => (string)($row['serverId'] ?? ''), $servers);
        $normalizedRequestedServerId = $requestedServerId !== '' ? $this->serverId($requestedServerId) : '';
        $serverId = $normalizedRequestedServerId !== '' && in_array($normalizedRequestedServerId, $knownServerIds, true)
            ? $normalizedRequestedServerId
            : (string)($servers[0]['serverId'] ?? '');

        $players = [];
        if ($serverId !== '') {
            $players = $this->playerStats(
                $serverId,
                trim((string)($this->request['search'] ?? '')),
                max(1, min(200, (int)($this->request['limit'] ?? 100))),
            );
        }

        $this->responder->send([
            'available' => true,
            'servers' => $servers,
            'players' => $players,
            'selectedServerId' => $serverId,
        ]);
    }

    public function clearServer(): void
    {
        $this->requireSchema();
        $serverId = $this->serverId((string)($this->request['serverId'] ?? ''));
        $before = $this->singleServerStats($serverId);

        $deleted = $this->db->transactional(function () use ($serverId): array {
            $events = $this->deleteByServer('gameAchievementEvents', $serverId);
            $progress = $this->deleteByServer('playerAchievements', $serverId);
            $definitions = $this->deleteByServer('gameAchievements', $serverId);
            return [
                'definitions' => $definitions,
                'progressRows' => $progress,
                'events' => $events,
            ];
        });

        $this->logger->event(
            'admin.achievements.server_cleared',
            'Achievement data for a game server was cleared by an administrator.',
            [
                'component' => 'admin_achievements',
                'operation' => 'clear_server',
                'serverId' => $serverId,
                'actorUuid' => $this->session->uuid(),
                'before' => $before,
                'deleted' => $deleted,
            ],
            'WARNING',
        );

        $this->responder->send([
            'type' => 'success',
            'message' => sprintf(
                'Достижения сервера %s очищены: определений %d, строк прогресса %d, событий %d.',
                $serverId,
                $deleted['definitions'],
                $deleted['progressRows'],
                $deleted['events'],
            ),
            'serverId' => $serverId,
            'deleted' => $deleted,
        ]);
    }

    public function clearPlayer(): void
    {
        $this->requireSchema();
        $serverId = $this->serverId((string)($this->request['serverId'] ?? ''));
        $playerUuid = $this->resolveStoredPlayerUuid((string)($this->request['playerUuid'] ?? ''));
        $identity = $this->playerIdentity($playerUuid);

        $before = $this->playerCounts($serverId, $playerUuid);
        $deleted = $this->db->transactional(function () use ($serverId, $playerUuid): array {
            $events = $this->deletePlayerRows('gameAchievementEvents', $serverId, $playerUuid);
            $progress = $this->deletePlayerRows('playerAchievements', $serverId, $playerUuid);
            return [
                'progressRows' => $progress,
                'events' => $events,
            ];
        });

        $this->logger->event(
            'admin.achievements.player_cleared',
            'Achievement progress for a player was cleared by an administrator.',
            [
                'component' => 'admin_achievements',
                'operation' => 'clear_player',
                'serverId' => $serverId,
                'playerUuid' => $playerUuid,
                'playerLogin' => (string)($identity['login'] ?? ''),
                'actorUuid' => $this->session->uuid(),
                'before' => $before,
                'deleted' => $deleted,
            ],
            'WARNING',
        );

        $label = trim((string)($identity['login'] ?? ''));
        if ($label === '') $label = $playerUuid;
        $this->responder->send([
            'type' => 'success',
            'message' => sprintf(
                'Прогресс достижений игрока %s на сервере %s очищен: строк прогресса %d, событий %d.',
                $label,
                $serverId,
                $deleted['progressRows'],
                $deleted['events'],
            ),
            'serverId' => $serverId,
            'playerUuid' => $playerUuid,
            'deleted' => $deleted,
        ]);
    }

    /** @return list<array<string,mixed>> */
    private function serverStats(): array
    {
        /** @var array<string,array{serverId:string,definitions:int,progressRows:int,players:int,events:int}> $stats */
        $stats = [];

        $ensure = static function (array &$target, string $serverId): void {
            if ($serverId === '' || isset($target[$serverId])) return;
            $target[$serverId] = [
                'serverId' => $serverId,
                'definitions' => 0,
                'progressRows' => 0,
                'players' => 0,
                'events' => 0,
            ];
        };

        foreach ($this->achievementRows(
            'server-definitions',
            'SELECT `serverId`, COUNT(*) AS `definitions` FROM `gameAchievements` GROUP BY `serverId`'
        ) as $row) {
            $serverId = (string)($row['serverId'] ?? '');
            $ensure($stats, $serverId);
            if ($serverId !== '') $stats[$serverId]['definitions'] = (int)($row['definitions'] ?? 0);
        }

        foreach ($this->achievementRows(
            'server-progress',
            'SELECT `serverId`, COUNT(*) AS `progressRows`, COUNT(DISTINCT `playerUuid`) AS `players` '
            . 'FROM `playerAchievements` GROUP BY `serverId`'
        ) as $row) {
            $serverId = (string)($row['serverId'] ?? '');
            $ensure($stats, $serverId);
            if ($serverId !== '') {
                $stats[$serverId]['progressRows'] = (int)($row['progressRows'] ?? 0);
                $stats[$serverId]['players'] = max($stats[$serverId]['players'], (int)($row['players'] ?? 0));
            }
        }

        foreach ($this->achievementRows(
            'server-events',
            'SELECT `serverId`, COUNT(*) AS `events`, COUNT(DISTINCT `playerUuid`) AS `players` '
            . 'FROM `gameAchievementEvents` GROUP BY `serverId`'
        ) as $row) {
            $serverId = (string)($row['serverId'] ?? '');
            $ensure($stats, $serverId);
            if ($serverId !== '') {
                $stats[$serverId]['events'] = (int)($row['events'] ?? 0);
            }
        }

        // Count distinct players across progress + events without relying on a derived UNION table.
        foreach (array_keys($stats) as $serverId) {
            $progressPlayers = $this->achievementColumn(
                'server-progress-players',
                'SELECT DISTINCT `playerUuid` FROM `playerAchievements` WHERE `serverId` = :serverId',
                [':serverId' => $serverId],
            );
            $eventPlayers = $this->achievementColumn(
                'server-event-players',
                'SELECT DISTINCT `playerUuid` FROM `gameAchievementEvents` WHERE `serverId` = :serverId',
                [':serverId' => $serverId],
            );
            $players = [];
            foreach (array_merge($progressPlayers, $eventPlayers) as $playerUuid) {
                $playerUuid = trim((string)$playerUuid);
                if ($playerUuid !== '') $players[$playerUuid] = true;
            }
            $stats[$serverId]['players'] = count($players);
        }

        ksort($stats, SORT_STRING);
        return array_values($stats);
    }

    /** @return array{serverId:string,definitions:int,progressRows:int,players:int,events:int} */
    private function singleServerStats(string $serverId): array
    {
        foreach ($this->serverStats() as $row) {
            if ((string)($row['serverId'] ?? '') !== $serverId) continue;
            return [
                'serverId' => $serverId,
                'definitions' => (int)($row['definitions'] ?? 0),
                'progressRows' => (int)($row['progressRows'] ?? 0),
                'players' => (int)($row['players'] ?? 0),
                'events' => (int)($row['events'] ?? 0),
            ];
        }
        return [
            'serverId' => $serverId,
            'definitions' => 0,
            'progressRows' => 0,
            'players' => 0,
            'events' => 0,
        ];
    }

    /** @return list<array<string,mixed>> */
    private function playerStats(string $serverId, string $search, int $limit): array
    {
        /** @var array<string,array{progressRows:int,completedCount:int,events:int}> $metrics */
        $metrics = [];

        foreach ($this->achievementRows(
            'player-progress',
            'SELECT `playerUuid`, COUNT(*) AS `progressRows`, '
            . 'SUM(CASE WHEN `completed` = 1 THEN 1 ELSE 0 END) AS `completedCount` '
            . 'FROM `playerAchievements` WHERE `serverId` = :serverId GROUP BY `playerUuid`',
            [':serverId' => $serverId],
        ) as $row) {
            $uuid = trim((string)($row['playerUuid'] ?? ''));
            if ($uuid === '') continue;
            $metrics[$uuid] = [
                'progressRows' => (int)($row['progressRows'] ?? 0),
                'completedCount' => (int)($row['completedCount'] ?? 0),
                'events' => 0,
            ];
        }

        foreach ($this->achievementRows(
            'player-events',
            'SELECT `playerUuid`, COUNT(*) AS `events` '
            . 'FROM `gameAchievementEvents` WHERE `serverId` = :serverId GROUP BY `playerUuid`',
            [':serverId' => $serverId],
        ) as $row) {
            $uuid = trim((string)($row['playerUuid'] ?? ''));
            if ($uuid === '') continue;
            if (!isset($metrics[$uuid])) {
                $metrics[$uuid] = ['progressRows' => 0, 'completedCount' => 0, 'events' => 0];
            }
            $metrics[$uuid]['events'] = (int)($row['events'] ?? 0);
        }

        if ($metrics === []) return [];

        $identities = [];
        foreach (array_chunk(array_keys($metrics), 200) as $chunkIndex => $chunk) {
            $placeholders = [];
            $params = [];
            foreach ($chunk as $index => $uuid) {
                $placeholder = ':uuid' . $chunkIndex . '_' . $index;
                $placeholders[] = $placeholder;
                $params[$placeholder] = $uuid;
            }
            // $chunk is guaranteed non-empty by array_chunk() over a non-empty metric map.
            $rows = $this->achievementRows(
                'player-identities',
                'SELECT `uuid`, `login`, `realname` FROM `users` WHERE `uuid` IN ('
                . implode(', ', $placeholders)
                . ')',
                $params,
            );
            foreach ($rows as $row) {
                $uuid = trim((string)($row['uuid'] ?? ''));
                if ($uuid !== '') $identities[$uuid] = $row;
            }
        }

        $needle = mb_strtolower(trim($search), 'UTF-8');
        $players = [];
        foreach ($metrics as $uuid => $metric) {
            $identity = $identities[$uuid] ?? ['uuid' => $uuid, 'login' => '', 'realname' => ''];
            $login = (string)($identity['login'] ?? '');
            $realname = (string)($identity['realname'] ?? '');
            if ($needle !== '') {
                $haystack = mb_strtolower(trim($login . ' ' . $realname . ' ' . $uuid), 'UTF-8');
                if (!str_contains($haystack, $needle)) continue;
            }
            $players[] = [
                'uuid' => $uuid,
                'login' => $login,
                'realname' => $realname,
                'progressRows' => $metric['progressRows'],
                'completedCount' => $metric['completedCount'],
                'events' => $metric['events'],
            ];
        }

        usort($players, static function (array $left, array $right): int {
            $completed = (int)$right['completedCount'] <=> (int)$left['completedCount'];
            if ($completed !== 0) return $completed;
            $events = (int)$right['events'] <=> (int)$left['events'];
            if ($events !== 0) return $events;
            return mb_strtolower((string)$left['login'], 'UTF-8')
                <=> mb_strtolower((string)$right['login'], 'UTF-8');
        });
        return array_slice($players, 0, $limit);
    }

    /** @return array{progressRows:int,events:int} */
    private function playerCounts(string $serverId, string $playerUuid): array
    {
        $row = $this->db->getRow(
            'SELECT '
            . '(SELECT COUNT(*) FROM `playerAchievements` WHERE `serverId` = :progressServer AND `playerUuid` = :progressUuid) AS `progressRows`, '
            . '(SELECT COUNT(*) FROM `gameAchievementEvents` WHERE `serverId` = :eventServer AND `playerUuid` = :eventUuid) AS `events`',
            [
                ':progressServer' => $serverId,
                ':progressUuid' => $playerUuid,
                ':eventServer' => $serverId,
                ':eventUuid' => $playerUuid,
            ],
        );
        return [
            'progressRows' => (int)($row['progressRows'] ?? 0),
            'events' => (int)($row['events'] ?? 0),
        ];
    }

    /** @return array<string,mixed> */
    private function playerIdentity(string $playerUuid): array
    {
        $row = $this->db->getRow(
            'SELECT `uuid`, `login`, `realname` FROM `users` WHERE `uuid` = :uuid LIMIT 1',
            [':uuid' => $playerUuid],
        );
        return is_array($row) ? $row : ['uuid' => $playerUuid, 'login' => '', 'realname' => ''];
    }

    private function resolveStoredPlayerUuid(string $value): string
    {
        if (!Uuid::isValid($value)) {
            throw new HttpException('Некорректный UUID игрока.', 400);
        }
        $candidates = Uuid::databaseCandidates($value);
        $placeholders = [];
        $params = [];
        foreach ($candidates as $index => $candidate) {
            $placeholder = ':uuid' . $index;
            $placeholders[] = $placeholder;
            $params[$placeholder] = $candidate;
        }
        $statement = $this->db->prepare(
            'SELECT `uuid` FROM `users` WHERE `uuid` IN (' . implode(', ', $placeholders) . ') LIMIT 1'
        );
        $statement->execute($params);
        $stored = $statement->fetchColumn();
        if (!is_string($stored) || $stored === '') {
            throw new HttpException('Игрок не найден.', 404);
        }
        return $stored;
    }

    /** @return list<array<string,mixed>> */
    private function achievementRows(string $stage, string $sql, array $params = []): array
    {
        try {
            return $this->db->getRows($sql, $params);
        } catch (DatabaseException $exception) {
            throw new DatabaseException(
                'Achievement admin SQL stage "' . $stage . '" failed: ' . $exception->getMessage(),
                0,
                $exception,
            );
        }
    }

    /** @return list<mixed> */
    private function achievementColumn(string $stage, string $sql, array $params = []): array
    {
        try {
            return $this->db->getColumn($sql, $params);
        } catch (DatabaseException $exception) {
            throw new DatabaseException(
                'Achievement admin SQL stage "' . $stage . '" failed: ' . $exception->getMessage(),
                0,
                $exception,
            );
        }
    }

    private function serverId(string $value): string
    {
        $value = strtolower(trim($value));
        if (preg_match(self::SERVER_ID_PATTERN, $value) !== 1) {
            throw new HttpException('Некорректный идентификатор игрового сервера.', 400);
        }
        return $value;
    }

    private function deleteByServer(string $table, string $serverId): int
    {
        if (!in_array($table, self::REQUIRED_TABLES, true)) {
            throw new LogicException('Unsupported achievement table.');
        }
        $statement = $this->db->prepare('DELETE FROM `' . $table . '` WHERE `serverId` = :serverId');
        $statement->execute([':serverId' => $serverId]);
        return $statement->rowCount();
    }

    private function deletePlayerRows(string $table, string $serverId, string $playerUuid): int
    {
        if (!in_array($table, ['playerAchievements', 'gameAchievementEvents'], true)) {
            throw new LogicException('Unsupported player achievement table.');
        }
        $statement = $this->db->prepare(
            'DELETE FROM `' . $table . '` WHERE `serverId` = :serverId AND `playerUuid` = :playerUuid'
        );
        $statement->execute([':serverId' => $serverId, ':playerUuid' => $playerUuid]);
        return $statement->rowCount();
    }

    private function schemaReady(): bool
    {
        foreach (self::REQUIRED_TABLES as $table) {
            try {
                $statement = $this->db->prepare(
                    'SELECT COUNT(*) FROM information_schema.TABLES '
                    . 'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :tableName'
                );
                $statement->execute([':tableName' => $table]);
            } catch (DatabaseException|PDOException $exception) {
                throw new DatabaseException(
                    'Achievement admin SQL stage "schema-' . $table . '" failed: ' . $exception->getMessage(),
                    0,
                    $exception,
                );
            }
            if ((int)$statement->fetchColumn() !== 1) return false;
        }
        return true;
    }

    private function requireSchema(): void
    {
        if (!$this->schemaReady()) {
            throw new HttpException(
                'Схема игровых достижений недоступна. Примените миграцию 025_game_achievements.sql.',
                503,
            );
        }
    }
}
