<?php

declare(strict_types=1);

// Keep the ingestion service self-contained across rolling/mixed deployments.
// The Game API bootstrap also requires this dependency, but loading it here
// guarantees that a freshly deployed event service cannot execute without its
// point-ledger implementation. require_once remains idempotent.
require_once dirname(__DIR__) . '/services/AchievementPointExchangeService.class.php';

final class GameAchievementEventService
{
    public function __construct(private db $db)
    {
    }

    /**
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    public function ingest(string $serverId, array $payload): array
    {
        $serverId = $this->serverId($serverId);
        $eventUuid = $this->uuid($payload['eventId'] ?? null, 'eventId');
        $playerUuid = $this->uuid($payload['playerUuid'] ?? null, 'playerUuid');
        $playerName = trim((string)($payload['playerName'] ?? ''));
        if (preg_match('/^[A-Za-z0-9_.-]{1,64}$/D', $playerName) !== 1) {
            throw new GameApiException('achievement_player_invalid', 'Некорректное имя игрока.', 422);
        }
        $achievementKey = trim((string)($payload['achievementKey'] ?? ''));
        if (preg_match('~^[a-z0-9_.-]+:[a-z0-9_./-]+$~D', $achievementKey) !== 1 || strlen($achievementKey) > 190) {
            throw new GameApiException('achievement_identifier_invalid', 'Некорректный ключ достижения.', 422);
        }
        $eventType = strtolower(trim((string)($payload['eventType'] ?? 'advancement')));
        if (preg_match('/^[a-z][a-z0-9._-]{0,31}$/D', $eventType) !== 1) {
            throw new GameApiException('achievement_event_type_invalid', 'Некорректный тип события.', 422);
        }
        $progress = max(0, (int)($payload['progress'] ?? 0));
        $target = max(1, (int)($payload['target'] ?? 1));
        if ($progress > 1_000_000_000 || $target > 1_000_000_000) {
            throw new GameApiException('achievement_progress_invalid', 'Прогресс достижения выходит за допустимый диапазон.', 422);
        }
        $completed = ($payload['completed'] ?? false) === true || $progress >= $target;
        $occurredAt = max(1, (int)($payload['occurredAt'] ?? time()));
        if (abs(time() - $occurredAt) > 366 * 86400) {
            throw new GameApiException('achievement_timestamp_invalid', 'Время события выходит за допустимый диапазон.', 422);
        }
        $criterion = trim((string)($payload['criterion'] ?? ''));
        if (strlen($criterion) > 190) {
            throw new GameApiException('achievement_criterion_invalid', 'Название критерия слишком длинное.', 422);
        }
        $payloadJson = json_encode(
            $payload,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
        );
        if (strlen($payloadJson) > 262144) {
            throw new GameApiException('achievement_event_too_large', 'Событие достижения слишком велико.', 413);
        }

        $result = $this->db->transactional(function () use (
            $serverId,
            $eventUuid,
            $playerUuid,
            $playerName,
            $achievementKey,
            $eventType,
            $progress,
            $target,
            $completed,
            $occurredAt,
            $payloadJson,
        ): array {
            $user = $this->db->prepare('SELECT `uuid`, `login` FROM `users` WHERE `uuid` = :uuid LIMIT 1');
            $user->execute([':uuid' => $playerUuid]);
            $userRow = $user->fetch(PDO::FETCH_ASSOC);
            if (!is_array($userRow)) {
                throw new GameApiException(
                    'achievement_player_unlinked',
                    'Minecraft UUID игрока не связан с профилем FoxCMS.',
                    404,
                );
            }

            $definition = $this->db->prepare(
                'SELECT `id`, `title`, `description`, `iconBase64`, `iconMime`, `points` '
                . 'FROM `gameAchievements` WHERE `serverId` = :serverId '
                . 'AND `gameCode` = \'minecraft\' AND `achievementKey` = :achievementKey '
                . 'AND `enabled` = 1 LIMIT 1'
            );
            $definition->execute([
                ':serverId' => $serverId,
                ':achievementKey' => $achievementKey,
            ]);
            $achievement = $definition->fetch(PDO::FETCH_ASSOC);
            if (!is_array($achievement)) {
                throw new GameApiException(
                    'achievement_definition_missing',
                    'Достижение отсутствует в актуальном каталоге сервера. Сначала синхронизируйте каталог.',
                    409,
                );
            }

            $event = $this->db->prepare(
                'INSERT IGNORE INTO `gameAchievementEvents` '
                . '(`eventUuid`, `serverId`, `playerUuid`, `achievementKey`, `eventType`, `payload`, `occurredAt`, `receivedAt`) '
                . 'VALUES (:eventUuid, :serverId, :playerUuid, :achievementKey, :eventType, :payload, :occurredAt, :receivedAt)'
            );
            $event->execute([
                ':eventUuid' => $eventUuid,
                ':serverId' => $serverId,
                ':playerUuid' => $playerUuid,
                ':achievementKey' => $achievementKey,
                ':eventType' => $eventType,
                ':payload' => $payloadJson,
                ':occurredAt' => $occurredAt,
                ':receivedAt' => time(),
            ]);
            if ($event->rowCount() === 0) {
                return [
                    'accepted' => true,
                    'duplicate' => true,
                    'completedNow' => false,
                    'playerUuid' => $playerUuid,
                    'achievementKey' => $achievementKey,
                    'title' => (string)$achievement['title'],
                    'serverId' => $serverId,
                ];
            }

            $existing = $this->db->prepare(
                'SELECT `id`, `progress`, `target`, `completed`, `completedAt` '
                . 'FROM `playerAchievements` WHERE `serverId` = :serverId '
                . 'AND `playerUuid` = :playerUuid AND `achievementId` = :achievementId '
                . 'LIMIT 1 FOR UPDATE'
            );
            $existing->execute([
                ':serverId' => $serverId,
                ':playerUuid' => $playerUuid,
                ':achievementId' => (int)$achievement['id'],
            ]);
            $current = $existing->fetch(PDO::FETCH_ASSOC);
            $wasCompleted = is_array($current) && (int)($current['completed'] ?? 0) === 1;
            $nextProgress = max($progress, is_array($current) ? (int)($current['progress'] ?? 0) : 0);
            $nextTarget = max($target, is_array($current) ? (int)($current['target'] ?? 1) : 1);
            $nextCompleted = $wasCompleted || $completed || $nextProgress >= $nextTarget;
            $completedAt = $wasCompleted
                ? (isset($current['completedAt']) ? (int)$current['completedAt'] : $occurredAt)
                : ($nextCompleted ? $occurredAt : null);
            $now = time();

            $upsert = $this->db->prepare(
                'INSERT INTO `playerAchievements` '
                . '(`serverId`, `playerUuid`, `playerName`, `achievementId`, `progress`, `target`, '
                . '`completed`, `firstProgressAt`, `completedAt`, `updatedAt`) '
                . 'VALUES (:serverId, :playerUuid, :playerName, :achievementId, :progress, :target, '
                . ':completed, :firstProgressAt, :completedAt, :updatedAt) '
                . 'ON DUPLICATE KEY UPDATE `playerName` = VALUES(`playerName`), '
                . '`progress` = GREATEST(`progress`, VALUES(`progress`)), '
                . '`target` = GREATEST(`target`, VALUES(`target`)), '
                . '`completed` = GREATEST(`completed`, VALUES(`completed`)), '
                . '`completedAt` = COALESCE(`completedAt`, VALUES(`completedAt`)), '
                . '`updatedAt` = VALUES(`updatedAt`)'
            );
            $upsert->execute([
                ':serverId' => $serverId,
                ':playerUuid' => $playerUuid,
                ':playerName' => $playerName,
                ':achievementId' => (int)$achievement['id'],
                ':progress' => $nextProgress,
                ':target' => $nextTarget,
                ':completed' => $nextCompleted ? 1 : 0,
                ':firstProgressAt' => $occurredAt,
                ':completedAt' => $completedAt,
                ':updatedAt' => $now,
            ]);

            $pointAwarded = false;
            if ($nextCompleted) {
                $pointAwarded = (new AchievementPointExchangeService($this->db))->recordAward(
                    $serverId,
                    $playerUuid,
                    $achievementKey,
                    max(0, (int)$achievement['points']),
                    $completedAt ?? $occurredAt,
                );
            }

            return [
                'accepted' => true,
                'duplicate' => false,
                'completedNow' => !$wasCompleted && $nextCompleted,
                'pointAwarded' => $pointAwarded,
                'completed' => $nextCompleted,
                'progress' => $nextProgress,
                'target' => $nextTarget,
                'playerUuid' => $playerUuid,
                'playerLogin' => (string)($userRow['login'] ?? $playerName),
                'achievementKey' => $achievementKey,
                'title' => (string)$achievement['title'],
                'description' => (string)$achievement['description'],
                'iconBase64' => (string)$achievement['iconBase64'],
                'iconMime' => (string)$achievement['iconMime'],
                'points' => max(0, (int)$achievement['points']),
                'serverId' => $serverId,
                'completedAt' => $completedAt,
            ];
        });

        if (($result['completedNow'] ?? false) === true && class_exists(NotificationService::class)) {
            try {
                $points = max(0, (int)($result['points'] ?? 0));
                $message = 'Получено достижение «' . (string)$result['title'] . '» на сервере ' . $serverId . '.';
                if ($points > 0) {
                    $message .= ' +' . $points . ' очков доступны для обмена на Units в личных достижениях.';
                }
                (new NotificationService($this->db))->create(
                    (string)$result['playerUuid'],
                    'achievement.game_unlocked',
                    'success',
                    'Новое достижение',
                    $message,
                    [
                        'serverId' => $serverId,
                        'achievementKey' => $achievementKey,
                        'title' => (string)$result['title'],
                        'description' => (string)$result['description'],
                        'points' => $points,
                        'pointAwarded' => ($result['pointAwarded'] ?? false) === true,
                        'completedAt' => (int)$result['completedAt'],
                    ],
                    '/achievements/' . rawurlencode((string)$result['playerUuid']),
                    'game-achievement:' . $serverId . ':' . $achievementKey,
                    (int)$result['completedAt'],
                );
            } catch (Throwable $error) {
                error_log('[FoxCMS achievements] Notification creation failed: ' . $error->getMessage());
            }
        }

        $result['databaseName'] = (string)($this->db->getValue('SELECT DATABASE()') ?? '');
        $result['eventPersisted'] = true;
        unset($result['playerLogin'], $result['iconBase64'], $result['iconMime']);
        return $result;
    }

    /** @return array{items:list<array<string,mixed>>,summary:array<string,int>} */
    public function playerAchievements(string $playerUuid, ?string $serverId = null): array
    {
        $playerUuid = $this->uuid($playerUuid, 'playerUuid');
        $player = $this->db->prepare('SELECT 1 FROM `users` WHERE `uuid` = :uuid LIMIT 1');
        $player->execute([':uuid' => $playerUuid]);
        if ($player->fetchColumn() === false) {
            throw new GameApiException('player_not_found', 'Профиль игрока не найден.', 404);
        }
        $parameters = [':playerUuid' => $playerUuid];
        $where = "`achievement`.`enabled` = 1 AND `achievement`.`gameCode` = 'minecraft' AND `achievement`.`achievementKey` NOT LIKE '%:advancement/recipes/%'";
        if ($serverId !== null && trim($serverId) !== '') {
            $serverId = $this->serverId($serverId);
            $where .= ' AND `achievement`.`serverId` = :serverId';
            $parameters[':serverId'] = $serverId;
        }
        $statement = $this->db->prepare(
            'SELECT `achievement`.`serverId`, COALESCE(`player`.`playerName`, \'\') AS `playerName`, '
            . 'COALESCE(`player`.`progress`, 0) AS `progress`, '
            . 'COALESCE(`player`.`target`, 1) AS `target`, '
            . 'COALESCE(`player`.`completed`, 0) AS `completed`, '
            . '`player`.`completedAt`, COALESCE(`player`.`updatedAt`, `achievement`.`updatedAt`) AS `updatedAt`, '
            . '`achievement`.`achievementKey`, `achievement`.`achievementType`, `achievement`.`parentKey`, '
            . '`achievement`.`title`, `achievement`.`description`, `achievement`.`frameType`, '
            . '`achievement`.`category`, `achievement`.`categoryLabel`, `achievement`.`iconBase64`, `achievement`.`iconMime`, '
            . '`achievement`.`iconItem`, `achievement`.`points`, `achievement`.`hidden` '
            . 'FROM `gameAchievements` AS `achievement` '
            . 'LEFT JOIN `playerAchievements` AS `player` '
            . 'ON `player`.`achievementId` = `achievement`.`id` '
            . 'AND `player`.`playerUuid` = :playerUuid '
            . 'WHERE ' . $where . ' ORDER BY COALESCE(`player`.`completed`, 0) DESC, '
            . 'COALESCE(`player`.`completedAt`, `player`.`updatedAt`, `achievement`.`updatedAt`) DESC, '
            . '`achievement`.`serverId` ASC, `achievement`.`title` ASC'
        );
        $statement->execute($parameters);
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $categoryLabels = $this->categoryLabels($rows);
        $items = [];
        $completedCount = 0;
        $points = 0;
        foreach ($rows as $row) {
            $completed = (int)($row['completed'] ?? 0) === 1;
            if ((int)($row['hidden'] ?? 0) === 1 && !$completed) {
                continue;
            }
            $itemPoints = max(0, (int)($row['points'] ?? 0));
            if ($completed) {
                ++$completedCount;
                $points += $itemPoints;
            }
            $items[] = [
                'serverId' => (string)$row['serverId'],
                'playerName' => (string)$row['playerName'],
                'achievementKey' => (string)$row['achievementKey'],
                'achievementType' => (string)$row['achievementType'],
                'parentKey' => $row['parentKey'] !== null ? (string)$row['parentKey'] : null,
                'title' => (string)$row['title'],
                'description' => (string)$row['description'],
                'frameType' => (string)$row['frameType'],
                'category' => (string)$row['category'],
                'categoryLabel' => $this->resolvedCategoryLabel($row, $categoryLabels),
                'iconDataUrl' => 'data:' . (string)$row['iconMime'] . ';base64,' . (string)$row['iconBase64'],
                'iconItem' => (string)$row['iconItem'],
                'points' => $itemPoints,
                'progress' => max(0, (int)$row['progress']),
                'target' => max(1, (int)$row['target']),
                'completed' => $completed,
                'completedAt' => $row['completedAt'] !== null ? max(0, (int)$row['completedAt']) : null,
                'updatedAt' => max(0, (int)$row['updatedAt']),
            ];
        }

        return [
            'items' => $items,
            'summary' => [
                'trackedCount' => count($items),
                'completedCount' => $completedCount,
                'points' => $points,
            ],
        ];
    }

    /**
     * @return array{
     *   items:list<array<string,mixed>>,
     *   summary:array{achievementCount:int,earnedAchievementCount:int,playerCount:int,unlockCount:int}
     * }
     */
    public function achievementStatistics(?string $serverId = null): array
    {
        $parameters = [];
        $where = "`achievement`.`enabled` = 1 AND `achievement`.`gameCode` = 'minecraft' AND `achievement`.`achievementKey` NOT LIKE '%:advancement/recipes/%'";
        if ($serverId !== null && trim($serverId) !== '') {
            $serverId = $this->serverId($serverId);
            $where .= ' AND `achievement`.`serverId` = :serverId';
            $parameters[':serverId'] = $serverId;
        }

        $definitions = $this->db->prepare(
            'SELECT `achievement`.`serverId`, `achievement`.`achievementKey`, `achievement`.`parentKey`, '
            . '`achievement`.`title`, `achievement`.`description`, `achievement`.`frameType`, '
            . '`achievement`.`category`, `achievement`.`categoryLabel`, `achievement`.`iconBase64`, `achievement`.`iconMime`, '
            . '`achievement`.`iconItem`, `achievement`.`points` '
            . 'FROM `gameAchievements` AS `achievement` '
            . 'WHERE ' . $where . ' ORDER BY `achievement`.`serverId` ASC, '
            . '`achievement`.`category` ASC, `achievement`.`title` ASC'
        );
        $definitions->execute($parameters);
        $definitionRows = $definitions->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $categoryLabels = $this->categoryLabels($definitionRows);

        $items = [];
        $indexes = [];
        foreach ($definitionRows as $row) {
            $key = (string)$row['serverId'] . "\0" . (string)$row['achievementKey'];
            $indexes[$key] = count($items);
            $items[] = [
                'serverId' => (string)$row['serverId'],
                'achievementKey' => (string)$row['achievementKey'],
                'parentKey' => $row['parentKey'] !== null ? (string)$row['parentKey'] : null,
                'title' => (string)$row['title'],
                'description' => (string)$row['description'],
                'frameType' => (string)$row['frameType'],
                'category' => (string)$row['category'],
                'categoryLabel' => $this->resolvedCategoryLabel($row, $categoryLabels),
                'iconDataUrl' => 'data:' . (string)$row['iconMime'] . ';base64,' . (string)$row['iconBase64'],
                'iconItem' => (string)$row['iconItem'],
                'points' => max(0, (int)$row['points']),
                'earnedCount' => 0,
                'playersTruncated' => false,
                'players' => [],
            ];
        }

        if ($items === []) {
            return [
                'items' => [],
                'summary' => [
                    'achievementCount' => 0,
                    'earnedAchievementCount' => 0,
                    'playerCount' => 0,
                    'unlockCount' => 0,
                ],
            ];
        }

        $completionParameters = [];
        $completionWhere = "`achievement`.`enabled` = 1 AND `achievement`.`gameCode` = 'minecraft' AND `achievement`.`achievementKey` NOT LIKE '%:advancement/recipes/%' "
            . 'AND `player`.`completed` = 1';
        if ($serverId !== null && $serverId !== '') {
            $completionWhere .= ' AND `player`.`serverId` = :serverId';
            $completionParameters[':serverId'] = $serverId;
        }
        $completions = $this->db->prepare(
            'SELECT `player`.`serverId`, `achievement`.`achievementKey`, `player`.`playerUuid`, '
            . "COALESCE(NULLIF(`player`.`playerName`, ''), 'Игрок') AS `playerName`, "
            . '`player`.`completedAt` '
            . 'FROM `playerAchievements` AS `player` '
            . 'INNER JOIN `gameAchievements` AS `achievement` ON `achievement`.`id` = `player`.`achievementId` '
            . 'WHERE ' . $completionWhere . ' ORDER BY `player`.`completedAt` DESC, `player`.`updatedAt` DESC'
        );
        $completions->execute($completionParameters);

        $playerUuids = [];
        $earnedAchievementKeys = [];
        $unlockCount = 0;
        $playerLimitPerAchievement = 24;
        foreach ($completions->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $key = (string)$row['serverId'] . "\0" . (string)$row['achievementKey'];
            if (!array_key_exists($key, $indexes)) {
                continue;
            }
            $index = $indexes[$key];
            ++$items[$index]['earnedCount'];
            ++$unlockCount;
            $earnedAchievementKeys[$key] = true;
            $playerUuid = (string)$row['playerUuid'];
            if ($playerUuid !== '') {
                $playerUuids[$playerUuid] = true;
            }
            if (count($items[$index]['players']) < $playerLimitPerAchievement) {
                $items[$index]['players'][] = [
                    'uuid' => $playerUuid,
                    'login' => '',
                    'playerName' => (string)$row['playerName'],
                    'completedAt' => $row['completedAt'] !== null ? max(0, (int)$row['completedAt']) : null,
                ];
            } else {
                $items[$index]['playersTruncated'] = true;
            }
        }

        return [
            'items' => $items,
            'summary' => [
                'achievementCount' => count($items),
                'earnedAchievementCount' => count($earnedAchievementKeys),
                'playerCount' => count($playerUuids),
                'unlockCount' => $unlockCount,
            ],
        ];
    }


    /**
     * @param array<string,mixed> $row
     * @param array<string,string> $categoryLabels
     */
    private function resolvedCategoryLabel(array $row, array $categoryLabels): string
    {
        $category = trim((string)($row['category'] ?? ''));
        $stored = trim((string)($row['categoryLabel'] ?? ''));
        // Migration 026 historically backfilled categoryLabel with the technical
        // registry id. Treat that value as an empty placeholder, not localization.
        if ($stored !== '' && $stored !== $category && !$this->looksTechnicalCategory($stored)) {
            return $stored;
        }

        $serverId = trim((string)($row['serverId'] ?? ''));
        $fallback = trim((string)($categoryLabels[$serverId . "\0" . $category] ?? ''));
        if ($fallback !== '' && $fallback !== $category) {
            return $fallback;
        }

        return $category;
    }

    private function looksTechnicalCategory(string $value): bool
    {
        return preg_match('~^[a-z0-9_.-]+:[a-z0-9_./-]+$~D', $value) === 1;
    }

    /**
     * Build localized labels for technical category identifiers using the
     * already-localized advancement titles stored in the catalog.
     *
     * @param list<array<string,mixed>> $rows
     * @return array<string,string>
     */
    private function categoryLabels(array $rows): array
    {
        $titlesByKey = [];
        $categories = [];
        $labels = [];
        foreach ($rows as $row) {
            $serverId = trim((string)($row['serverId'] ?? ''));
            $achievementKey = trim((string)($row['achievementKey'] ?? ''));
            $category = trim((string)($row['category'] ?? ''));
            $title = trim((string)($row['title'] ?? ''));
            if ($serverId === '' || $achievementKey === '' || $category === '') {
                continue;
            }
            $titlesByKey[$serverId . "\0" . $achievementKey] = $title;
            $categories[$serverId . "\0" . $category] = [$serverId, $category];
            if ($title !== '' && ($row['parentKey'] ?? null) === null) {
                $labels[$serverId . "\0" . $category] ??= $title;
            }
        }

        foreach ($categories as $mapKey => [$serverId, $category]) {
            [$namespace, $path] = array_pad(explode(':', $category, 2), 2, 'root');
            $candidates = $path === 'root'
                ? [$namespace . ':advancement/root']
                : [
                    $namespace . ':advancement/' . $path . '/root',
                    $path . ':advancement/' . $path . '/root',
                    $path . ':advancement/root',
                ];
            foreach ($candidates as $candidate) {
                $title = trim((string)($titlesByKey[$serverId . "\0" . $candidate] ?? ''));
                if ($title !== '') {
                    $labels[$mapKey] = $title;
                    break;
                }
            }
        }

        // Reuse another localized root from the same namespace before falling
        // back to a humanized technical token. This covers compatibility
        // branches such as irons_spellbooks:root.
        foreach ($categories as $mapKey => [$serverId, $category]) {
            if (isset($labels[$mapKey]) && $labels[$mapKey] !== '') {
                continue;
            }
            [$namespace] = array_pad(explode(':', $category, 2), 2, 'root');
            foreach ($categories as $otherKey => [$otherServerId, $otherCategory]) {
                if ($otherServerId !== $serverId || !isset($labels[$otherKey])) {
                    continue;
                }
                [$otherNamespace] = array_pad(explode(':', $otherCategory, 2), 2, 'root');
                if ($otherNamespace === $namespace) {
                    $labels[$mapKey] = $labels[$otherKey];
                    break;
                }
            }
        }

        foreach ($categories as $mapKey => [$serverId, $category]) {
            if (isset($labels[$mapKey]) && trim($labels[$mapKey]) !== '') {
                continue;
            }
            [, $path] = array_pad(explode(':', $category, 2), 2, 'root');
            $token = $path === 'root' ? explode(':', $category, 2)[0] : $path;
            $labels[$mapKey] = $this->humanizeCategoryToken($token);
        }
        return $labels;
    }

    private function humanizeCategoryToken(string $token): string
    {
        $value = trim(str_replace(['_', '-', '.'], ' ', $token));
        $value = preg_replace('/(?<=[a-z])(?=[A-Z])/u', ' ', $value) ?? $value;
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;
        return $value === '' ? 'Достижения' : mb_convert_case($value, MB_CASE_TITLE, 'UTF-8');
    }

    private function serverId(string $serverId): string
    {
        $serverId = trim($serverId);
        if (preg_match('/^[a-z0-9][a-z0-9._-]{2,99}$/D', $serverId) !== 1) {
            throw new GameApiException('game_server_invalid', 'Некорректный идентификатор игрового сервера.', 400);
        }
        return $serverId;
    }

    private function uuid(mixed $value, string $field): string
    {
        $uuid = trim((string)$value);
        if (!Uuid::isValid($uuid)) {
            throw new GameApiException('achievement_uuid_invalid', 'Некорректное поле ' . $field . '.', 422);
        }
        return Uuid::normalize($uuid);
    }
}
