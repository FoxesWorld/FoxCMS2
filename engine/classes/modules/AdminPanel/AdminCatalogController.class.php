<?php

declare(strict_types=1);

/**
 * Owns InfoBox, badge and group catalog administration.
 */
final class AdminCatalogController
{
    private const CATALOGS = [
        'infobox' => [
            'table' => 'infobox',
            'key' => 'group_name',
            'fields' => ['group_name', 'start_timestamp', 'end_timestamp', 'title', 'text', 'image', 'button_text', 'button_url'],
        ],
        'badges' => [
            'table' => 'badgesList',
            'key' => 'badgeName',
            'fields' => ['badgeName', 'description', 'img'],
        ],
        'groups' => [
            'table' => 'groupAssociation',
            'key' => 'groupTag',
            'fields' => ['groupTag', 'groupName', 'groupColor'],
        ],
    ];

    public function __construct(
        private db $db,
        private array $request,
        private Logger $logger,
        private GroupRepository $groupRepository,
        private MaintenanceModeRepository $maintenanceRepository,
        private ThemeBadgePageRepository $badgePageRepository,
        private AdminBadgeCatalogSchema $badgeCatalogSchema,
        private AdminRequestPayload $payload,
        private AdminResponder $responder,
        private AdminGroupListNormalizer $groupNormalizer,
    ) {
    }

    public function catalog(): void {
        [$spec, $catalog] = $this->catalogSpec();
        if ($catalog === 'badges') {
            $this->badgeCatalogSchema->assertAvailable();
        }
        $stmt = $this->db->prepare('SELECT ' . $this->quotedFields($spec['fields']) . ' FROM `' . $spec['table'] . '` ORDER BY `' . $spec['key'] . '`');
        $stmt->execute();
        $this->responder->send([
            'items' => $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [],
            'fields' => array_values($spec['fields']),
        ]);
    }

    public function saveCatalogEntry(): void {
        [$spec, $catalog] = $this->catalogSpec();
        if ($catalog === 'groups') {
            $this->saveGroupCatalogEntry();
        }
        if ($catalog === 'badges') {
            $this->saveBadgeCatalogEntry();
        }

        $payload = $this->payload->object('entry');
        $originalKey = trim((string)($this->request['originalKey'] ?? ''));
        $keyField = $spec['key'];
        $keyValue = trim((string)($payload[$keyField] ?? ''));
        if ($keyValue === '') $this->responder->send(['message' => 'Ключ записи не указан.', 'type' => 'error'], 400);
        $data = [];
        foreach ($spec['fields'] as $field) if (array_key_exists($field, $payload)) $data[$field] = is_string($payload[$field]) ? trim($payload[$field]) : $payload[$field];
        $data[$keyField] = $keyValue;

        if ($originalKey !== '') {
            $parts = [];
            $params = [':originalKey' => $originalKey];
            foreach ($data as $field => $value) {
                $placeholder = ':field_' . $field;
                $parts[] = '`' . $field . '` = ' . $placeholder;
                $params[$placeholder] = $value;
            }
            $stmt = $this->db->prepare('UPDATE `' . $spec['table'] . '` SET ' . implode(', ', $parts) . ' WHERE `' . $keyField . '` = :originalKey');
            $stmt->execute($params);
        } else {
            $fields = array_keys($data);
            $placeholders = array_map(fn($field) => ':' . $field, $fields);
            $params = [];
            foreach ($data as $field => $value) $params[':' . $field] = $value;
            $stmt = $this->db->prepare('INSERT INTO `' . $spec['table'] . '` (' . $this->quotedFields($fields) . ') VALUES (' . implode(', ', $placeholders) . ')');
            $stmt->execute($params);
        }
        $this->responder->send(['message' => 'Запись сохранена.', 'type' => 'success']);
    }

    public function deleteCatalogEntry(): void {
        [$spec, $catalog] = $this->catalogSpec();
        if ($catalog === 'groups') {
            $this->deleteGroupCatalogEntry();
        }
        if ($catalog === 'badges') {
            $this->deleteBadgeCatalogEntry();
        }
        $key = trim((string)($this->request['key'] ?? ''));
        if ($key === '') $this->responder->send(['message' => 'Ключ не указан.', 'type' => 'error'], 400);
        $stmt = $this->db->prepare('DELETE FROM `' . $spec['table'] . '` WHERE `' . $spec['key'] . '` = :key');
        $stmt->execute([':key' => $key]);
        $this->responder->send(['message' => 'Запись удалена.', 'type' => 'success']);
    }

    private function deleteBadgeCatalogEntry(): never {
        $this->assertRewardAdministrationSchema();
        $badgeName = trim((string)($this->request['key'] ?? ''));
        if ($badgeName === '') {
            $this->responder->send(['message' => 'Название бейджа не указано.', 'type' => 'error'], 400);
        }

        $lookup = $this->db->prepare(
            'SELECT `id`, `badgeName` FROM `badgesList` WHERE `badgeName` = :badgeName LIMIT 1'
        );
        $lookup->execute([':badgeName' => $badgeName]);
        $badge = $lookup->fetch(PDO::FETCH_ASSOC);
        if (!is_array($badge)) {
            $this->responder->send(['message' => 'Бейдж не найден.', 'type' => 'error'], 404);
        }

        $badgeId = (int)($badge['id'] ?? 0);
        $references = $this->db->prepare(
            'SELECT COUNT(*) FROM `rewardDefinitions` WHERE `badgeId` = :badgeId'
        );
        $references->execute([':badgeId' => $badgeId]);
        if ((int)$references->fetchColumn() > 0) {
            $this->responder->send([
                'message' => 'Бейдж используется в одной или нескольких наградах. Сначала измените конфигурацию этих наград.',
                'type' => 'error',
            ], 409);
        }
        $delete = $this->db->prepare('DELETE FROM `badgesList` WHERE `id` = :badgeId');
        $delete->execute([':badgeId' => $badgeId]);

        $this->logger->event(
            'catalog.badges.deleted',
            'Unreferenced badge catalog entry deleted.',
            [
                'component' => 'badge_catalog',
                'operation' => 'delete',
                'badgeId' => $badgeId,
                'badgeName' => $badgeName,
            ],
            'INFO',
            'success',
        );
        $this->responder->send([
            'message' => 'Бейдж удалён из каталога.',
            'type' => 'success',
        ]);
    }

    private function saveBadgeCatalogEntry(): never {
        $this->badgeCatalogSchema->assertAvailable();
        $payload = $this->payload->object('entry');
        $originalName = trim((string)($this->request['originalKey'] ?? ''));
        $badgeName = trim((string)($payload['badgeName'] ?? ''));
        $description = trim((string)($payload['description'] ?? ''));
        $image = trim(str_replace('\\', '/', (string)($payload['img'] ?? '')));

        if ($badgeName === '' || mb_strlen($badgeName) > 120
            || preg_match('/[\x00-\x1F\x7F]/u', $badgeName) === 1) {
            $this->responder->send([
                'message' => 'Название бейджа должно содержать от 1 до 120 печатных Unicode-символов.',
                'type' => 'error',
            ], 400);
        }
        if (mb_strlen($description) > 4000 || str_contains($description, "\0")) {
            $this->responder->send([
                'message' => 'Краткое описание бейджа не должно превышать 4000 символов.',
                'type' => 'error',
            ], 400);
        }
        $this->validateBadgeImageReference($image);

        $statement = $this->db->prepare(
            'SELECT `id`, `badgeName`, `description`, `img` FROM `badgesList` ORDER BY `badgeName`, `id`'
        );
        $statement->execute();
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $originalRow = null;
        foreach ($rows as $row) {
            if (is_array($row) && hash_equals((string)($row['badgeName'] ?? ''), $originalName)) {
                $originalRow = $row;
                break;
            }
        }
        if ($originalName !== '' && !is_array($originalRow)) {
            $this->responder->send(['message' => 'Бейдж для обновления не найден.', 'type' => 'error'], 404);
        }
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $rowName = (string)($row['badgeName'] ?? '');
            $sameRecord = is_array($originalRow)
                && (int)($row['id'] ?? 0) === (int)($originalRow['id'] ?? 0);
            if (!$sameRecord && mb_strtolower($rowName, 'UTF-8') === mb_strtolower($badgeName, 'UTF-8')) {
                $this->responder->send(['message' => 'Бейдж с таким названием уже существует.', 'type' => 'error'], 409);
            }
        }

        $oldSlug = null;
        $newSlug = null;
        if (is_array($originalRow)) {
            foreach (BadgeSlug::assign($rows) as $assigned) {
                if ((int)($assigned['id'] ?? 0) === (int)($originalRow['id'] ?? 0)) {
                    $oldSlug = (string)($assigned['pageSlug'] ?? '');
                    break;
                }
            }
            $prospectiveRows = $rows;
            foreach ($prospectiveRows as &$row) {
                if (is_array($row) && (int)($row['id'] ?? 0) === (int)($originalRow['id'] ?? 0)) {
                    $row['badgeName'] = $badgeName;
                }
            }
            unset($row);
            foreach (BadgeSlug::assign($prospectiveRows) as $assigned) {
                if ((int)($assigned['id'] ?? 0) === (int)($originalRow['id'] ?? 0)) {
                    $newSlug = (string)($assigned['pageSlug'] ?? '');
                    break;
                }
            }
        }

        $pageMoved = false;
        try {
            $this->db->beginTransaction();
            if (is_array($originalRow)) {
                $update = $this->db->prepare(
                    'UPDATE `badgesList` SET `badgeName` = :badgeName, `description` = :description, `img` = :image WHERE `id` = :id'
                );
                $update->execute([
                    ':badgeName' => $badgeName,
                    ':description' => $description,
                    ':image' => $image,
                    ':id' => (int)$originalRow['id'],
                ]);
                if ($originalName !== $badgeName) {
                    $this->renameBadgeAssignments($originalName, $badgeName);
                }
                if (is_string($oldSlug) && is_string($newSlug) && $oldSlug !== $newSlug
                    && $this->badgePageRepository->exists($oldSlug)) {
                    $this->badgePageRepository->move($oldSlug, $newSlug, $badgeName);
                    $pageMoved = true;
                }
            } else {
                $insert = $this->db->prepare(
                    'INSERT INTO `badgesList` (`badgeName`, `description`, `img`) '
                    . 'VALUES (:badgeName, :description, :image)'
                );
                $insert->execute([
                    ':badgeName' => $badgeName,
                    ':description' => $description,
                    ':image' => $image,
                ]);
                $newId = (int)$this->db->lastInsertId();
                if ($newId <= 0) {
                    $lookup = $this->db->prepare(
                        'SELECT `id` FROM `badgesList` WHERE `badgeName` = :badgeName ORDER BY `id` DESC LIMIT 1'
                    );
                    $lookup->execute([':badgeName' => $badgeName]);
                    $newId = max(0, (int)$lookup->fetchColumn());
                }
                $prospectiveRows = $rows;
                $prospectiveRows[] = [
                    'id' => $newId,
                    'badgeName' => $badgeName,
                    'description' => $description,
                    'img' => $image,
                ];
                foreach (BadgeSlug::assign($prospectiveRows) as $assigned) {
                    if ((int)($assigned['id'] ?? 0) === $newId) {
                        $newSlug = (string)($assigned['pageSlug'] ?? '');
                        break;
                    }
                }
            }
            $this->db->commit();
        } catch (Throwable $error) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            if ($pageMoved && is_string($oldSlug) && is_string($newSlug)) {
                try {
                    $this->badgePageRepository->move($newSlug, $oldSlug, $originalName);
                } catch (Throwable $rollbackError) {
                    $this->logger->exception(
                        'catalog.badges.rename_rollback_failed',
                        $rollbackError,
                        'Badge HTML page rename rollback failed.',
                        [
                            'component' => 'badge_catalog',
                            'from' => $newSlug,
                            'to' => $oldSlug,
                        ],
                    );
                }
            }
            throw $error;
        }

        $this->logger->event(
            'catalog.badges.saved',
            'Badge catalog entry saved.',
            [
                'component' => 'badge_catalog',
                'operation' => 'save',
                'originalBadgeName' => $originalName,
                'badgeName' => $badgeName,
                'pageSlug' => $newSlug,
                'created' => $originalName === '',
            ],
            'INFO',
            'success',
        );
        $this->responder->send([
            'message' => 'Бейдж сохранён. URL страницы: /#/badges/' . (string)$newSlug,
            'type' => 'success',
            'pageSlug' => $newSlug,
        ]);
    }

    private function validateBadgeImageReference(string $image): void {
        if ($image === '') {
            return;
        }
        if (strlen($image) > 1024 || str_contains($image, "\0")
            || preg_match('/[\x00-\x1F\x7F]/u', $image) === 1) {
            $this->responder->send(['message' => 'Некорректный путь к изображению бейджа.', 'type' => 'error'], 400);
        }
        $decodedPath = rawurldecode((string)(parse_url($image, PHP_URL_PATH) ?? $image));
        foreach (explode('/', str_replace('\\', '/', $decodedPath)) as $segment) {
            if ($segment === '..') {
                $this->responder->send(['message' => 'Переходы .. в пути изображения запрещены.', 'type' => 'error'], 400);
            }
        }
        $scheme = parse_url($image, PHP_URL_SCHEME);
        if (is_string($scheme) && $scheme !== '' && !in_array(strtolower($scheme), ['http', 'https'], true)) {
            $this->responder->send(['message' => 'Разрешены только локальные изображения и HTTP(S) URL.', 'type' => 'error'], 400);
        }
        if (is_string($scheme) && $scheme !== '' && filter_var($image, FILTER_VALIDATE_URL) === false) {
            $this->responder->send(['message' => 'Некорректный URL изображения бейджа.', 'type' => 'error'], 400);
        }
        if (str_starts_with($image, '//')) {
            $this->responder->send(['message' => 'Protocol-relative URL изображения запрещён.', 'type' => 'error'], 400);
        }
    }

    private function renameBadgeAssignments(string $oldName, string $newName): void {
        if ($oldName === $newName) {
            return;
        }
        $statement = $this->db->prepare(
            'SELECT `uuid`, `badges` FROM `users` WHERE `badges` IS NOT NULL AND `badges` <> :empty'
        );
        $statement->execute([':empty' => '']);
        $update = $this->db->prepare('UPDATE `users` SET `badges` = :badges WHERE `uuid` = :uuid');
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            if (!is_array($row)) {
                continue;
            }
            $raw = (string)($row['badges'] ?? '');
            $changed = false;
            $decoded = json_decode($raw, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $decoded = $this->replaceBadgeAssignmentValue($decoded, $oldName, $newName, $changed);
                $encoded = json_encode(
                    $decoded,
                    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
                );
            } elseif (trim($raw) === $oldName) {
                $encoded = $newName;
                $changed = true;
            } else {
                continue;
            }
            if ($changed) {
                $update->execute([
                    ':badges' => $encoded,
                    ':uuid' => (string)$row['uuid'],
                ]);
            }
        }
    }

    private function replaceBadgeAssignmentValue(
        mixed $value,
        string $oldName,
        string $newName,
        bool &$changed,
    ): mixed {
        if (is_string($value)) {
            if ($value === $oldName) {
                $changed = true;
                return $newName;
            }
            return $value;
        }
        if (!is_array($value)) {
            return $value;
        }
        $result = [];
        foreach ($value as $key => $entry) {
            $newKey = is_string($key) && $key === $oldName ? $newName : $key;
            if ($newKey !== $key) {
                $changed = true;
            }
            $result[$newKey] = $this->replaceBadgeAssignmentValue($entry, $oldName, $newName, $changed);
        }
        return $result;
    }

    private function saveGroupCatalogEntry(): never {
        $payload = $this->payload->object('entry');
        $originalTag = GroupRepository::normalizeTag($this->request['originalKey'] ?? '', '');
        $groupTag = GroupRepository::normalizeTag($payload['groupTag'] ?? '', '');
        $groupName = trim((string)($payload['groupName'] ?? ''));
        $groupColor = strtolower(trim((string)($payload['groupColor'] ?? '#ffffff')));

        if ($groupTag === '') {
            $this->responder->send(['message' => 'Тег группы должен начинаться с латинской буквы и содержать только a-z, 0-9, _ или -.', 'type' => 'error'], 400);
        }
        if ($groupName === '' || mb_strlen($groupName) > 64) {
            $this->responder->send(['message' => 'Название группы должно содержать от 1 до 64 символов.', 'type' => 'error'], 400);
        }
        if (preg_match('/^#[0-9a-f]{6}$/D', $groupColor) !== 1) {
            $this->responder->send(['message' => 'Цвет группы должен быть записан в формате #RRGGBB.', 'type' => 'error'], 400);
        }

        $duplicateName = $this->db->prepare(
            'SELECT `groupTag` FROM `groupAssociation` WHERE `groupName` = :groupName AND `groupTag` <> :groupTag LIMIT 1'
        );
        $duplicateName->execute([':groupName' => $groupName, ':groupTag' => $originalTag !== '' ? $originalTag : $groupTag]);
        if ($duplicateName->fetchColumn() !== false) {
            $this->responder->send(['message' => 'Группа с таким названием уже существует.', 'type' => 'error'], 409);
        }

        if ($originalTag !== '') {
            if ($groupTag !== $originalTag) {
                $this->responder->send(['message' => 'Тег группы является стабильным идентификатором и не может быть изменён.', 'type' => 'error'], 409);
            }
            $statement = $this->db->prepare(
                'UPDATE `groupAssociation` SET `groupName` = :groupName, `groupColor` = :groupColor, `groupType` = :groupTag '
                . 'WHERE `groupTag` = :groupTag'
            );
            $statement->execute([
                ':groupName' => $groupName,
                ':groupColor' => $groupColor,
                ':groupTag' => $groupTag,
            ]);
        } else {
            if ($this->groupRepository->exists($groupTag)) {
                $this->responder->send(['message' => 'Группа с таким тегом уже существует.', 'type' => 'error'], 409);
            }
            $legacyNumber = max(1, (int)$this->scalar('SELECT COALESCE(MAX(`groupNum`), 0) + 1 FROM `groupAssociation`'));
            $statement = $this->db->prepare(
                'INSERT INTO `groupAssociation` (`groupTag`, `groupName`, `groupColor`, `groupNum`, `groupType`) '
                . 'VALUES (:groupTag, :groupName, :groupColor, :groupNum, :groupType)'
            );
            $statement->execute([
                ':groupTag' => $groupTag,
                ':groupName' => $groupName,
                ':groupColor' => $groupColor,
                ':groupNum' => $legacyNumber,
                ':groupType' => $groupTag,
            ]);
        }
        $this->responder->send(['message' => 'Группа сохранена.', 'type' => 'success']);
    }

    private function deleteGroupCatalogEntry(): never {
        $groupTag = GroupRepository::normalizeTag($this->request['key'] ?? '', '');
        if ($groupTag === '') {
            $this->responder->send(['message' => 'Тег группы не указан.', 'type' => 'error'], 400);
        }
        if (in_array($groupTag, ['admin', 'user', 'guest'], true)) {
            $this->responder->send(['message' => 'Системную группу удалить нельзя.', 'type' => 'error'], 409);
        }
        $assignedUsers = (int)$this->scalar('SELECT COUNT(*) FROM `users` WHERE `groupTag` = :groupTag', [':groupTag' => $groupTag]);
        $registrationCodes = (int)$this->scalar('SELECT COUNT(*) FROM `regCodes` WHERE `groupTag` = :groupTag', [':groupTag' => $groupTag]);
        if ($assignedUsers > 0 || $registrationCodes > 0) {
            $this->responder->send(['message' => 'Группа используется пользователями или регистрационными кодами.', 'type' => 'error'], 409);
        }
        $maintenance = $this->maintenanceRepository->current();
        if (in_array($groupTag, $maintenance['allowedGroups'] ?? [], true)) {
            $this->responder->send(['message' => 'Сначала удалите группу из доступа во время техработ.', 'type' => 'error'], 409);
        }
        $statement = $this->db->prepare('SELECT `serverGroups` FROM `servers`');
        $statement->execute();
        foreach ($statement->fetchAll(PDO::FETCH_COLUMN) ?: [] as $serverGroups) {
            if (in_array($groupTag, $this->groupNormalizer->normalize($serverGroups), true)) {
                $this->responder->send(['message' => 'Сначала удалите группу из списков доступа серверов.', 'type' => 'error'], 409);
            }
        }
        $statement = $this->db->prepare('DELETE FROM `groupAssociation` WHERE `groupTag` = :groupTag');
        $statement->execute([':groupTag' => $groupTag]);
        $this->responder->send(['message' => 'Группа удалена.', 'type' => 'success']);
    }

    private function catalogSpec(): array {
        $catalog = (string)($this->request['catalog'] ?? '');
        if (!isset(self::CATALOGS[$catalog])) {
            $this->responder->send(['message' => 'Неизвестный каталог.', 'type' => 'error'], 400);
        }
        return [self::CATALOGS[$catalog], $catalog];
    }

    private function assertRewardAdministrationSchema(): void
    {
        $required = [
            'badgesList' => ['id', 'badgeName', 'description', 'img'],
            'rewardDefinitions' => [
                'id', 'rewardName', 'description', 'badgeId', 'currencyCode', 'currencyAmount',
                'enabled', 'createdAt', 'updatedAt', 'createdByUuid', 'updatedByUuid',
            ],
            'rewardClaimKeys' => [
                'id', 'rewardId', 'tokenHash', 'tokenHint', 'usageMode', 'accessMode', 'publicPlacement',
                'usesCount', 'enabled', 'createdAt', 'updatedAt', 'createdByUuid',
            ],
            'rewardClaims' => [
                'id', 'rewardId', 'keyId', 'userUuid', 'badgeGranted', 'badgeId', 'badgeName',
                'currencyCode', 'currencyAmount', 'claimedAt',
            ],
        ];
        $placeholders = [];
        $parameters = [];
        foreach (array_keys($required) as $index => $table) {
            $placeholder = ':table_' . $index;
            $placeholders[] = $placeholder;
            $parameters[$placeholder] = $table;
        }
        $statement = $this->db->prepare(
            'SELECT `TABLE_NAME`, `COLUMN_NAME` FROM information_schema.COLUMNS '
            . 'WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` IN (' . implode(', ', $placeholders) . ')'
        );
        $statement->execute($parameters);
        $actual = [];
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) ?: [] as $column) {
            $table = (string)($column['TABLE_NAME'] ?? '');
            $name = (string)($column['COLUMN_NAME'] ?? '');
            if ($table !== '' && $name !== '') {
                $actual[$table][$name] = true;
            }
        }
        $missing = [];
        foreach ($required as $table => $columns) {
            if (!isset($actual[$table])) {
                $missing[] = $table . '.*';
                continue;
            }
            foreach ($columns as $column) {
                if (!isset($actual[$table][$column])) {
                    $missing[] = $table . '.' . $column;
                }
            }
        }
        $indexStatement = $this->db->prepare(
            "SELECT COUNT(*) FROM information_schema.STATISTICS "
            . "WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rewardClaims' "
            . "AND INDEX_NAME = 'uq_reward_claim_reward_user'"
        );
        $indexStatement->execute();
        if ((int)$indexStatement->fetchColumn() < 1) {
            $missing[] = 'rewardClaims.uq_reward_claim_reward_user';
        }
        if ($missing !== []) {
            throw new HttpException(
                'Не удалось загрузить награды: схема базы данных не обновлена. Отсутствуют: '
                . implode(', ', $missing) . '. Выполните `php scripts/migrate.php`; необходима миграция 021.',
                503,
            );
        }
    }

    private function scalar(string $sql, array $params = []) {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchColumn();
    }

    private function quotedFields(array $fields): string {
        return implode(', ', array_map(fn($field) => '`' . $field . '`', $fields));
    }
}
