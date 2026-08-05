<?php

declare(strict_types=1);

/**
 * Owns administrative user directory, profile updates and direct badge mutations.
 */
final class AdminUserController
{
    private const USER_FIELDS = [
        'login', 'realname', 'email', 'userStatus', 'groupTag', 'balance', 'serversOnline',
    ];

    public function __construct(
        private db $db,
        private array $request,
        private UserSession $session,
        private Logger $logger,
        private GroupRepository $groupRepository,
        private AdminRequestPayload $payload,
        private AdminResponder $responder,
        private AdminBadgeOptionsProvider $badgeOptions,
    ) {
    }

    public function users(): void {
        $search = trim((string)($this->request['search'] ?? ''));
        $limit = max(1, min(200, (int)($this->request['limit'] ?? 100)));
        $offset = max(0, (int)($this->request['offset'] ?? 0));
        $badgeExpression = '`user`.`badges`';
        $where = '';
        if ($search !== '') {
            $searchSql = $this->db->safesql('%' . $search . '%');
            $where = " WHERE CONCAT_WS(' ', "
                . "COALESCE(`user`.`login`, ''), "
                . "COALESCE(`user`.`email`, ''), "
                . "COALESCE(`user`.`realname`, ''), "
                . "COALESCE(`user`.`uuid`, ''), "
                . "COALESCE(CAST(`user`.`user_id` AS CHAR), ''), "
                . "COALESCE(`user`.`groupTag`, ''), "
                . "COALESCE(`group`.`groupName`, ''), "
                . 'COALESCE(' . $badgeExpression . ", '')"
                . ') LIKE ' . $searchSql;
        }

        $sql = 'SELECT `user`.`uuid`, `user`.`user_id`, `user`.`login`, `user`.`email`, '
            . '`user`.`realname`, `user`.`groupTag`, `user`.`last_date`, `user`.`reg_date`, '
            . '`user`.`profilePhoto`, `user`.`userStatus`, `user`.`balance`, '
            . $badgeExpression . ' AS `badges`, `user`.`serversOnline`, '
            . '`group`.`groupName`, `group`.`groupColor` '
            . 'FROM `users` AS `user` '
            . 'LEFT JOIN `groupAssociation` AS `group` ON `group`.`groupTag` = `user`.`groupTag` '
            . $where . ' ORDER BY `user`.`last_date` DESC LIMIT ' . $limit . ' OFFSET ' . $offset;

        try {
            $statement = $this->db->query($sql);
            if (!$statement instanceof PDOStatement) {
                throw new RuntimeException('Database query returned no statement.');
            }
            $rows = $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $error) {
            throw new RuntimeException('users.directory query failed: ' . $error->getMessage(), 0, $error);
        }

        foreach ($rows as &$row) {
            if (!is_array($row)) continue;
            $row['balance'] = BalanceMatrix::normalize($row['balance'] ?? null);
            $row['badges'] = $this->decodeAdminJsonField($row['badges'] ?? null);
            $row['serversOnline'] = $this->decodeAdminJsonField($row['serversOnline'] ?? null);
        }
        unset($row);

        try {
            $groups = $this->adminUserGroups();
        } catch (Throwable $error) {
            throw new RuntimeException('users.groups query failed: ' . $error->getMessage(), 0, $error);
        }
        try {
            $badgeOptions = $this->badgeOptions->all();
        } catch (Throwable $error) {
            throw new RuntimeException('users.badges query failed: ' . $error->getMessage(), 0, $error);
        }

        $this->responder->send([
            'items' => $rows,
            'groups' => $groups,
            'badgeOptions' => $badgeOptions,
            'limit' => $limit,
            'offset' => $offset,
            'backendVersion' => 'users-directory-v4-direct-query',
        ]);
    }

    public function updateUser(): void {
        $userUuid = (string)($this->request['userUuid'] ?? '');
        if (!Uuid::isValid($userUuid)) {
            $this->responder->send(['message' => 'Некорректный UUID пользователя.', 'type' => 'error'], 400);
        }
        $userUuid = $this->resolveStoredUserUuid($userUuid);

        $payload = $this->payload->object('entry');
        if (array_key_exists('badges', $payload)) {
            throw new HttpException(
                'Поле badges нельзя изменять вместе с общими данными пользователя. Используйте отдельные административные действия выдачи и отзыва бейджей.',
                409,
            );
        }
        $updates = [];
        foreach (self::USER_FIELDS as $field) {
            if (!array_key_exists($field, $payload)) continue;
            $value = $payload[$field];
            if ($field === 'login') {
                $value = trim((string)$value);
                if (preg_match('/^[A-Za-z0-9_.-]{3,64}$/D', $value) !== 1) {
                    $this->responder->send(['message' => 'Некорректный логин.', 'type' => 'error'], 400);
                }
                $duplicate = $this->db->prepare('SELECT `uuid` FROM `users` WHERE `login` = :login AND `uuid` <> :userUuid LIMIT 1');
                $duplicate->execute([':login' => $value, ':userUuid' => $userUuid]);
                if ($duplicate->fetchColumn() !== false) {
                    $this->responder->send(['message' => 'Логин уже используется.', 'type' => 'error'], 409);
                }
            }
            if ($field === 'email') {
                $value = mb_strtolower(trim((string)$value));
                if (filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
                    $this->responder->send(['message' => 'Некорректный email.', 'type' => 'error'], 400);
                }
                $duplicate = $this->db->prepare('SELECT `uuid` FROM `users` WHERE `email` = :email AND `uuid` <> :userUuid LIMIT 1');
                $duplicate->execute([':email' => $value, ':userUuid' => $userUuid]);
                if ($duplicate->fetchColumn() !== false) {
                    $this->responder->send(['message' => 'Email уже используется.', 'type' => 'error'], 409);
                }
            }
            if ($field === 'groupTag') {
                $value = GroupRepository::normalizeTag($value, '');
                if ($value === '' || !$this->groupRepository->exists($value)) {
                    $this->responder->send(['message' => 'Выбранная группа не существует.', 'type' => 'error'], 400);
                }
            }
            if ($field === 'balance') {
                try {
                    $value = BalanceMatrix::encode($value);
                } catch (InvalidArgumentException $error) {
                    $this->responder->send([
                        'message' => 'Матрица баланса должна содержать целые неотрицательные значения Units и Crystals.',
                        'type' => 'error',
                    ], 400);
                }
            } elseif ($field === 'serversOnline') {
                if (is_array($value) || is_object($value)) {
                    $value = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
                }
                $value = (string)$value;
                if ($value !== '' && in_array($value[0], ['{', '['], true)) {
                    json_decode($value, true, 32, JSON_THROW_ON_ERROR);
                }
            }
            $updates[$field] = is_string($value) ? trim($value) : $value;
        }
        if ($updates === []) {
            $this->responder->send(['message' => 'Нет данных для обновления.', 'type' => 'error'], 400);
        }

        $parts = [];
        $parameters = [':userUuid' => $userUuid];
        foreach ($updates as $field => $value) {
            $placeholder = ':field_' . $field;
            $parts[] = '`' . $field . '` = ' . $placeholder;
            $parameters[$placeholder] = $value;
        }
        $statement = $this->db->prepare(
            'UPDATE `users` SET ' . implode(', ', $parts) . ' WHERE `uuid` = :userUuid'
        );
        $statement->execute($parameters);
        $this->responder->send(['message' => 'Пользователь обновлён.', 'type' => 'success']);
    }

    public function grantUserBadge(): void
    {
        $this->mutateUserBadge(true);
    }

    public function revokeUserBadge(): void
    {
        $this->mutateUserBadge(false);
    }

    private function mutateUserBadge(bool $grant): void
    {
        $requestedUuid = trim((string)($this->request['userUuid'] ?? ''));
        if (!Uuid::isValid($requestedUuid)) {
            throw new HttpException('Некорректный UUID пользователя.', 400);
        }
        $userUuid = $this->resolveStoredUserUuid($requestedUuid);
        $reason = preg_replace('/\s+/u', ' ', trim((string)($this->request['reason'] ?? '')));
        $reason = is_string($reason) ? $reason : '';
        $reasonLength = function_exists('mb_strlen') ? mb_strlen($reason, 'UTF-8') : strlen($reason);
        if ($reasonLength < 3 || $reasonLength > 500) {
            throw new HttpException('Укажите причину административной выдачи или отзыва: от 3 до 500 символов.', 400);
        }

        $badgeId = max(0, (int)($this->request['badgeId'] ?? 0));
        $badgeName = trim((string)($this->request['badgeName'] ?? ''));
        $badge = null;
        if ($badgeId > 0) {
            $statement = $this->db->prepare(
                'SELECT `id`, `badgeName`, `description`, `img` FROM `badgesList` WHERE `id` = :id LIMIT 1'
            );
            $statement->execute([':id' => $badgeId]);
            $row = $statement->fetch(PDO::FETCH_ASSOC);
            if (is_array($row)) {
                $badge = [
                    'id' => (int)($row['id'] ?? 0),
                    'badgeName' => trim((string)($row['badgeName'] ?? '')),
                    'title' => trim((string)($row['badgeName'] ?? '')),
                    'description' => trim((string)($row['description'] ?? '')),
                    'image' => trim((string)($row['img'] ?? '')) ?: null,
                ];
                $badgeName = (string)$badge['badgeName'];
            }
        }
        if (!$grant && $badge === null && $badgeName !== '') {
            $statement = $this->db->prepare(
                'SELECT `id`, `badgeName`, `description`, `img` FROM `badgesList` WHERE `badgeName` = :badgeName LIMIT 1'
            );
            $statement->execute([':badgeName' => $badgeName]);
            $row = $statement->fetch(PDO::FETCH_ASSOC);
            if (is_array($row)) {
                $badge = [
                    'id' => (int)($row['id'] ?? 0),
                    'badgeName' => trim((string)($row['badgeName'] ?? '')),
                    'title' => trim((string)($row['badgeName'] ?? '')),
                    'description' => trim((string)($row['description'] ?? '')),
                    'image' => trim((string)($row['img'] ?? '')) ?: null,
                ];
                $badgeName = (string)$badge['badgeName'];
            }
        }
        if ($grant && $badge === null) {
            throw new HttpException('Выбранный бейдж отсутствует в каталоге.', 404);
        }
        if ($badgeName === '') {
            throw new HttpException('Бейдж для административной операции не указан.', 400);
        }
        if ((function_exists('mb_strlen') ? mb_strlen($badgeName, 'UTF-8') : strlen($badgeName)) > 160) {
            throw new HttpException('Название бейджа превышает допустимую длину.', 400);
        }

        $actorUuid = $this->session->uuid();
        $result = $this->db->transactional(function () use ($grant, $userUuid, $badgeName, $badge, $reason): array {
            $statement = $this->db->prepare(
                'SELECT `uuid`, `login`, `badges` FROM `users` WHERE `uuid` = :uuid LIMIT 1 FOR UPDATE'
            );
            $statement->execute([':uuid' => $userUuid]);
            $user = $statement->fetch(PDO::FETCH_ASSOC);
            if (!is_array($user)) {
                throw new HttpException('Пользователь не найден.', 404);
            }

            $assignments = $this->decodeBadgeAssignmentsForMutation($user['badges'] ?? null);
            $needle = $this->normalizeBadgeAssignmentKey($badgeName);
            $exists = false;
            foreach ($assignments as $assignment) {
                if ($this->normalizeBadgeAssignmentKey($this->badgeAssignmentName($assignment)) === $needle) {
                    $exists = true;
                    break;
                }
            }

            $changed = false;
            if ($grant && !$exists) {
                $assignments[] = [
                    'badgeName' => $badgeName,
                    'acquiredAt' => time(),
                    'source' => 'admin',
                ];
                $changed = true;
            } elseif (!$grant && $exists) {
                $assignments = array_values(array_filter(
                    $assignments,
                    fn (mixed $assignment): bool => $this->normalizeBadgeAssignmentKey(
                        $this->badgeAssignmentName($assignment)
                    ) !== $needle,
                ));
                $changed = true;
            }

            $badgesJson = json_encode(
                array_values($assignments),
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
            );
            if ($changed) {
                $update = $this->db->prepare('UPDATE `users` SET `badges` = :badges WHERE `uuid` = :uuid');
                $update->execute([':badges' => $badgesJson, ':uuid' => $userUuid]);
                $notificationService = new NotificationService($this->db);
                if ($grant && $badge !== null) {
                    $notificationService->notifyBadgeAwarded($userUuid, $badge, 'administrator');
                } elseif (!$grant) {
                    $notificationService->notifyBadgeRevoked(
                        $userUuid,
                        $badge ?? [
                            'id' => 0,
                            'badgeName' => $badgeName,
                            'title' => $badgeName,
                            'description' => '',
                            'image' => null,
                        ],
                        $reason,
                        'administrator',
                    );
                }
            }

            return [
                'changed' => $changed,
                'badges' => array_values($assignments),
                'login' => trim((string)($user['login'] ?? '')),
            ];
        });

        $operation = $grant ? 'grant' : 'revoke';
        $this->logger->event(
            'admin.user_badge.' . $operation,
            $grant ? 'Administrator granted a profile badge.' : 'Administrator revoked a profile badge.',
            [
                'component' => 'admin_users',
                'operation' => $operation,
                'actorUuid' => $actorUuid,
                'targetUserUuid' => $userUuid,
                'targetLogin' => (string)$result['login'],
                'badgeId' => $badge !== null ? (int)$badge['id'] : null,
                'badgeName' => $badgeName,
                'reason' => $reason,
                'changed' => (bool)$result['changed'],
                'rewardClaimChanged' => false,
                'balanceChanged' => false,
            ],
            (bool)$result['changed'] ? 'INFO' : 'NOTICE',
            (bool)$result['changed'] ? 'success' : 'noop',
        );

        $message = $grant
            ? ((bool)$result['changed'] ? 'Бейдж выдан пользователю.' : 'У пользователя уже есть этот бейдж.')
            : ((bool)$result['changed'] ? 'Бейдж отозван у пользователя.' : 'У пользователя уже нет этого бейджа.');
        $this->responder->send([
            'message' => $message,
            'type' => (bool)$result['changed'] ? 'success' : 'warning',
            'changed' => (bool)$result['changed'],
            'badges' => $result['badges'],
            'badge' => $badge ?? ['id' => 0, 'badgeName' => $badgeName, 'title' => $badgeName, 'description' => '', 'image' => null],
        ]);
    }

    /** @return list<mixed> */

    private function decodeBadgeAssignmentsForMutation(mixed $value): array
    {
        $decoded = $value;
        if (!is_array($decoded)) {
            $raw = trim((string)$value);
            if ($raw === '') {
                return [];
            }
            $decoded = json_decode($raw, true);
            if (!is_array($decoded)) {
                return [['badgeName' => $raw]];
            }
        }
        if ($decoded === []) {
            return [];
        }
        if (array_is_list($decoded)) {
            return array_values($decoded);
        }
        if (array_key_exists('badgeName', $decoded) || array_key_exists('id', $decoded)
            || array_key_exists('name', $decoded) || array_key_exists('title', $decoded)) {
            return [$decoded];
        }
        $assignments = [];
        foreach ($decoded as $name => $acquiredAt) {
            $badgeName = trim((string)$name);
            if ($badgeName !== '') {
                $assignments[] = ['badgeName' => $badgeName, 'acquiredAt' => $acquiredAt];
            }
        }
        return $assignments;
    }

    private function badgeAssignmentName(mixed $assignment): string
    {
        if (is_string($assignment) || is_numeric($assignment)) {
            return trim((string)$assignment);
        }
        if (!is_array($assignment)) {
            return '';
        }
        $candidate = $assignment['badgeName'] ?? $assignment['id'] ?? $assignment['name'] ?? $assignment['title'] ?? '';
        return is_string($candidate) || is_numeric($candidate) ? trim((string)$candidate) : '';
    }

    private function normalizeBadgeAssignmentKey(string $badgeName): string
    {
        $badgeName = trim($badgeName);
        return function_exists('mb_strtolower')
            ? mb_strtolower($badgeName, 'UTF-8')
            : strtolower($badgeName);
    }

    private function resolveStoredUserUuid(string $userUuid): string {
        $placeholders = [];
        $parameters = [];
        foreach (Uuid::databaseCandidates($userUuid) as $index => $candidate) {
            $placeholder = ':userUuid_' . $index;
            $placeholders[] = $placeholder;
            $parameters[$placeholder] = $candidate;
        }
        $statement = $this->db->prepare(
            'SELECT `uuid` FROM `users` WHERE `uuid` IN (' . implode(', ', $placeholders) . ') LIMIT 1'
        );
        $statement->execute($parameters);
        $storedUuid = $statement->fetchColumn();
        if (!is_string($storedUuid) || !Uuid::isValid($storedUuid)) {
            $this->responder->send(['message' => 'Пользователь не найден.', 'type' => 'error'], 404);
        }
        return $storedUuid;
    }

    private function adminUserGroups(): array {
        $statement = $this->db->query(
            'SELECT `groupTag`, `groupName`, `groupColor` FROM `groupAssociation` ORDER BY `groupName`, `groupTag`'
        );
        if (!$statement instanceof PDOStatement) {
            throw new RuntimeException('Database query returned no group statement.');
        }
        $groups = [];
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            if (!is_array($row)) continue;
            $tag = GroupRepository::normalizeTag($row['groupTag'] ?? 'guest');
            $color = strtolower(trim((string)($row['groupColor'] ?? '#ffffff')));
            if (preg_match('/^#[0-9a-f]{6}$/D', $color) !== 1) $color = '#ffffff';
            $groups[] = [
                'groupTag' => $tag,
                'groupName' => trim((string)($row['groupName'] ?? '')) ?: $tag,
                'groupColor' => $color,
            ];
        }
        return $groups;
    }

    private function decodeAdminJsonField(mixed $value): array {
        if (is_array($value)) return $value;
        if (is_object($value)) return (array)$value;
        $raw = trim((string)$value);
        if ($raw === '') return [];
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }
}
