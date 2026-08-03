<?php

declare(strict_types=1);

final class BadgeClaimService
{
    private const TOKEN_PREFIX = 'fcb_';
    private const TOKEN_PATTERN = '/^fcb_[A-Za-z0-9_-]{43}$/D';

    public function __construct(
        private db $db,
        private Logger $logger,
    ) {
    }

    /** @return list<array<string, mixed>> */
    public function listKeys(): array
    {
        $statement = $this->db->query(
            'SELECT `key`.`id`, `key`.`badgeId`, `key`.`tokenHint`, `key`.`usageMode`, `key`.`accessMode`, `key`.`usesCount`, `key`.`enabled`, '
            . '`key`.`createdAt`, `key`.`updatedAt`, `key`.`createdByUuid`, '
            . '`badge`.`badgeName`, COUNT(`claim`.`id`) AS `claimsCount`, '
            . 'MAX(`claim`.`claimedAt`) AS `lastClaimedAt` '
            . 'FROM `badgeClaimKeys` AS `key` '
            . 'INNER JOIN `badgesList` AS `badge` ON `badge`.`id` = `key`.`badgeId` '
            . 'LEFT JOIN `badgeKeyClaims` AS `claim` ON `claim`.`keyId` = `key`.`id` '
            . 'GROUP BY `key`.`id`, `key`.`badgeId`, `key`.`tokenHint`, `key`.`usageMode`, `key`.`accessMode`, `key`.`usesCount`, `key`.`enabled`, '
            . '`key`.`createdAt`, `key`.`updatedAt`, `key`.`createdByUuid`, `badge`.`badgeName` '
            . 'ORDER BY `badge`.`badgeName`, `key`.`id`'
        );
        if (!$statement instanceof PDOStatement) {
            throw new RuntimeException('Database query returned no badge claim key statement.');
        }

        return array_values(array_map(
            fn (array $row): array => $this->normalizeKeyRow($row),
            $statement->fetchAll(PDO::FETCH_ASSOC) ?: [],
        ));
    }

    /**
     * Creates a new single-use or reusable key bound to one badge.
     * The plaintext token is returned exactly once and is never persisted.
     *
     * @return array{token:string, entry:array<string, mixed>}
     */
    public function issue(int $badgeId, string $usageMode, string $createdByUuid): array
    {
        $this->requireBadgeId($badgeId);
        $creator = $this->normalizeAdministratorUuid($createdByUuid);
        $usageMode = $this->normalizeUsageMode($usageMode);
        $badge = $this->badgeById($badgeId);
        $issued = $this->createKey($badgeId, $usageMode, $creator);
        $entry = $this->keyById($issued['keyId']);

        $this->logger->event(
            'badge.claim_key.issued',
            'Badge claim key created.',
            [
                'component' => 'badge_claims',
                'operation' => 'issue_key',
                'badgeId' => $badgeId,
                'badgeName' => (string)$badge['badgeName'],
                'keyId' => (int)$entry['id'],
                'usageMode' => $usageMode,
                'createdByUuid' => $creator,
            ],
            'INFO',
            'success',
        );

        return ['token' => $issued['token'], 'entry' => $entry];
    }

    /**
     * Issues a one-time key and immediately applies that same key to the target
     * user. The plaintext token exists only inside this method and is never sent
     * to the administrative client.
     *
     * @return array{badge:array<string, mixed>, badgesJson:string, key:array<string, mixed>}
     */
    public function grantToUser(int $badgeId, string $userUuid, string $createdByUuid): array
    {
        $this->requireBadgeId($badgeId);
        if (!Uuid::isValid($userUuid)) {
            throw new HttpException('Некорректный UUID пользователя.', 400);
        }
        $targetUuid = Uuid::normalize($userUuid);
        $creator = $this->normalizeAdministratorUuid($createdByUuid);

        $result = $this->db->transactional(function () use ($badgeId, $targetUuid, $creator): array {
            $badge = $this->badgeById($badgeId);
            $user = $this->lockedUser($targetUuid);
            $assignments = $this->decodeAssignments($user['badges'] ?? null);
            $badgeName = trim((string)($badge['badgeName'] ?? ''));
            if ($badgeName === '') {
                throw new HttpException('Бейдж не содержит допустимого названия.', 409);
            }
            if ($this->containsBadge($assignments, $badgeName)) {
                throw new HttpException('Пользователь уже имеет этот бейдж.', 409);
            }

            $issued = $this->createKey($badgeId, 'single', $creator);
            $claim = $this->claimByHash(
                $issued['tokenHash'],
                $targetUuid,
                'admin-claim-key',
            );

            return [
                'badge' => $claim['badge'],
                'badgesJson' => $claim['badgesJson'],
                'key' => $this->keyById($issued['keyId']),
            ];
        });

        $this->logger->event(
            'badge.admin_grant.completed',
            'Administrator issued and consumed a one-time badge claim key for a user.',
            [
                'component' => 'badge_claims',
                'operation' => 'admin_grant',
                'badgeId' => $badgeId,
                'badgeName' => (string)($result['badge']['badgeName'] ?? ''),
                'keyId' => (int)($result['key']['id'] ?? 0),
                'userUuid' => $targetUuid,
                'createdByUuid' => $creator,
            ],
            'INFO',
            'success',
        );

        return $result;
    }


    /**
     * Claims any badge that has an enabled public claim key. The public key is
     * an authorization marker only; every successful grant is performed by a
     * newly generated single-use key whose plaintext never leaves the server.
     *
     * @return array{badge:array<string, mixed>, alreadyOwned:bool, badgesJson:string}
     */
    public function claimPublic(string $badgeName, string $userUuid): array
    {
        $badgeName = trim($badgeName);
        if ($badgeName === '' || mb_strlen($badgeName, 'UTF-8') > 64) {
            throw new HttpException('Некорректное название бейджа.', 400);
        }
        if (!Uuid::isValid($userUuid)) {
            throw new HttpException('Некорректный UUID пользователя.', 400);
        }
        $targetUuid = Uuid::normalize($userUuid);

        $result = $this->db->transactional(function () use ($badgeName, $targetUuid): array {
            $publicKey = $this->lockedPublicKeyByBadgeName($badgeName);
            $user = $this->lockedUser($targetUuid);
            $assignments = $this->decodeAssignments($user['badges'] ?? null);
            $badgesJson = json_encode(
                array_values($assignments),
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
            );

            if ($this->containsBadge($assignments, (string)$publicKey['badgeName'])) {
                return [
                    'badge' => $this->badgeResponse($publicKey, time()),
                    'alreadyOwned' => true,
                    'badgesJson' => $badgesJson,
                    'keyId' => 0,
                ];
            }

            $issued = $this->createKey((int)$publicKey['badgeId'], 'single', null);
            $claim = $this->claimByHash(
                $issued['tokenHash'],
                $targetUuid,
                'public-claim-key',
            );

            return [
                'badge' => $claim['badge'],
                'alreadyOwned' => false,
                'badgesJson' => $claim['badgesJson'],
                'keyId' => $issued['keyId'],
            ];
        });

        $this->logger->event(
            'badge.public_claim.completed',
            'Public badge was claimed through a generated one-time key.',
            [
                'component' => 'badge_claims',
                'operation' => 'claim_public_badge',
                'badgeId' => (int)($result['badge']['id'] ?? 0),
                'badgeName' => (string)($result['badge']['badgeName'] ?? ''),
                'keyId' => (int)($result['keyId'] ?? 0),
                'userUuid' => $targetUuid,
                'alreadyOwned' => (bool)$result['alreadyOwned'],
            ],
            'INFO',
            'success',
        );

        return [
            'badge' => $result['badge'],
            'alreadyOwned' => $result['alreadyOwned'],
            'badgesJson' => $result['badgesJson'],
        ];
    }

    /** @return array<string, mixed> */
    public function revoke(int $keyId, string $revokedByUuid): array
    {
        if ($keyId <= 0) {
            throw new HttpException('Некорректный идентификатор ключа.', 400);
        }
        $revoker = $this->normalizeAdministratorUuid($revokedByUuid);

        $statement = $this->db->prepare(
            'UPDATE `badgeClaimKeys` SET `enabled` = 0, `updatedAt` = :updatedAt WHERE `id` = :id'
        );
        $statement->execute([':updatedAt' => time(), ':id' => $keyId]);
        if ($statement->rowCount() === 0) {
            throw new HttpException('Ключ получения бейджа не найден.', 404);
        }

        $entry = $this->keyById($keyId);
        $this->logger->event(
            'badge.claim_key.revoked',
            'Badge claim key revoked.',
            [
                'component' => 'badge_claims',
                'operation' => 'revoke_key',
                'badgeId' => (int)$entry['badgeId'],
                'keyId' => $keyId,
                'revokedByUuid' => $revoker,
            ],
            'INFO',
            'success',
        );
        return $entry;
    }

    /** @return array{badge:array<string, mixed>, alreadyOwned:bool, badgesJson:string} */
    public function claim(string $token, string $userUuid): array
    {
        $token = trim($token);
        if (preg_match(self::TOKEN_PATTERN, $token) !== 1) {
            throw new HttpException('Некорректный формат ключа получения бейджа.', 400);
        }
        if (!Uuid::isValid($userUuid)) {
            throw new HttpException('Некорректный UUID пользователя.', 400);
        }

        $tokenHash = hash('sha256', $token);
        $normalizedUuid = Uuid::normalize($userUuid);
        $result = $this->db->transactional(
            fn (): array => $this->claimByHash($tokenHash, $normalizedUuid, 'claim-key'),
        );

        $this->logger->event(
            'badge.claim.completed',
            'Badge claim key accepted for an authenticated profile.',
            [
                'component' => 'badge_claims',
                'operation' => 'claim_badge',
                'badgeId' => (int)($result['badge']['id'] ?? 0),
                'keyId' => (int)($result['keyId'] ?? 0),
                'userUuid' => $normalizedUuid,
                'alreadyOwned' => (bool)($result['alreadyOwned'] ?? false),
                'usageMode' => (string)($result['usageMode'] ?? ''),
            ],
            'INFO',
            'success',
        );

        return [
            'badge' => $result['badge'],
            'alreadyOwned' => $result['alreadyOwned'],
            'badgesJson' => $result['badgesJson'],
        ];
    }

    /**
     * @return array{
     *   badge:array<string, mixed>,
     *   alreadyOwned:bool,
     *   badgesJson:string,
     *   keyId:int,
     *   usageMode:string
     * }
     */
    private function claimByHash(string $tokenHash, string $normalizedUuid, string $assignmentSource): array
    {
        $keyStatement = $this->db->prepare(
            'SELECT `key`.`id`, `key`.`badgeId`, `key`.`usageMode`, `key`.`usesCount`, `key`.`enabled`, '
            . '`badge`.`badgeName`, `badge`.`description`, `badge`.`img` '
            . 'FROM `badgeClaimKeys` AS `key` '
            . 'INNER JOIN `badgesList` AS `badge` ON `badge`.`id` = `key`.`badgeId` '
            . 'WHERE `key`.`tokenHash` = :tokenHash LIMIT 1 FOR UPDATE'
        );
        $keyStatement->execute([':tokenHash' => $tokenHash]);
        $key = $keyStatement->fetch(PDO::FETCH_ASSOC);
        if (!is_array($key)) {
            throw new HttpException('Ключ получения бейджа не найден.', 404);
        }
        if ((int)($key['enabled'] ?? 0) !== 1) {
            throw new HttpException('Этот код получения бейджа отозван.', 410);
        }

        $usageMode = (string)($key['usageMode'] ?? 'single');
        $usesCount = (int)($key['usesCount'] ?? 0);
        if (!in_array($usageMode, ['single', 'reusable'], true)) {
            throw new HttpException('Ключ имеет недопустимый режим использования.', 409);
        }
        if ($usageMode === 'single' && $usesCount >= 1) {
            throw new HttpException('Этот одноразовый код уже использован.', 409);
        }

        $user = $this->lockedUser($normalizedUuid);
        $assignments = $this->decodeAssignments($user['badges'] ?? null);
        $badgeName = trim((string)($key['badgeName'] ?? ''));
        if ($badgeName === '') {
            throw new HttpException('Ключ не привязан к допустимому бейджу.', 409);
        }
        $alreadyOwned = $this->containsBadge($assignments, $badgeName);
        $now = time();

        if (!$alreadyOwned) {
            $assignments[] = [
                'badgeName' => $badgeName,
                'acquiredAt' => $now,
                'source' => $assignmentSource,
            ];
        }
        $badgesJson = json_encode(
            array_values($assignments),
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
        );

        if (!$alreadyOwned) {
            $update = $this->db->prepare('UPDATE `users` SET `badges` = :badges WHERE `uuid` = :uuid');
            $update->execute([':badges' => $badgesJson, ':uuid' => (string)$user['uuid']]);

            $claim = $this->db->prepare(
                'INSERT INTO `badgeKeyClaims` (`badgeId`, `keyId`, `userUuid`, `claimedAt`) '
                . 'VALUES (:badgeId, :keyId, :userUuid, :claimedAt)'
            );
            $claim->execute([
                ':badgeId' => (int)$key['badgeId'],
                ':keyId' => (int)$key['id'],
                ':userUuid' => $normalizedUuid,
                ':claimedAt' => $now,
            ]);

            $consume = $this->db->prepare(
                'UPDATE `badgeClaimKeys` SET `usesCount` = `usesCount` + 1, `updatedAt` = :updatedAt '
                . 'WHERE `id` = :id'
            );
            $consume->execute([':updatedAt' => $now, ':id' => (int)$key['id']]);
        }

        return [
            'badge' => [
                'id' => (int)$key['badgeId'],
                'badgeName' => $badgeName,
                'title' => $badgeName,
                'description' => trim((string)($key['description'] ?? '')),
                'image' => trim((string)($key['img'] ?? '')) ?: null,
                'acquiredAt' => $now,
            ],
            'alreadyOwned' => $alreadyOwned,
            'badgesJson' => $badgesJson,
            'keyId' => (int)$key['id'],
            'usageMode' => $usageMode,
        ];
    }

    /** @return array{token:string, tokenHash:string, keyId:int} */
    private function createKey(int $badgeId, string $usageMode, ?string $createdByUuid, string $accessMode = 'code'): array
    {
        $token = self::TOKEN_PREFIX . $this->base64Url(random_bytes(32));
        $tokenHash = hash('sha256', $token);
        $now = time();

        $statement = $this->db->prepare(
            'INSERT INTO `badgeClaimKeys` '
            . '(`badgeId`, `tokenHash`, `tokenHint`, `usageMode`, `accessMode`, `usesCount`, `enabled`, `createdAt`, `updatedAt`, `createdByUuid`) '
            . 'VALUES (:badgeId, :tokenHash, :tokenHint, :usageMode, :accessMode, 0, 1, :createdAt, :updatedAt, :createdByUuid)'
        );
        $statement->execute([
            ':badgeId' => $badgeId,
            ':tokenHash' => $tokenHash,
            ':tokenHint' => substr($token, -10),
            ':usageMode' => $usageMode,
            ':accessMode' => $this->normalizeAccessMode($accessMode),
            ':createdAt' => $now,
            ':updatedAt' => $now,
            ':createdByUuid' => $createdByUuid,
        ]);

        $keyId = (int)$this->db->lastInsertId();
        if ($keyId <= 0) {
            throw new RuntimeException('Database did not return the created badge claim key identifier.');
        }
        return ['token' => $token, 'tokenHash' => $tokenHash, 'keyId' => $keyId];
    }

    private function requireBadgeId(int $badgeId): void
    {
        if ($badgeId <= 0) {
            throw new HttpException('Некорректный идентификатор бейджа.', 400);
        }
    }

    private function normalizeAdministratorUuid(string $uuid): string
    {
        if (!Uuid::isValid($uuid)) {
            throw new HttpException('Некорректный UUID администратора.', 400);
        }
        return Uuid::normalize($uuid);
    }

    private function normalizeUsageMode(string $usageMode): string
    {
        $usageMode = strtolower(trim($usageMode));
        if (!in_array($usageMode, ['single', 'reusable'], true)) {
            throw new HttpException('Неизвестный режим использования кода.', 400);
        }
        return $usageMode;
    }

    private function normalizeAccessMode(string $accessMode): string
    {
        $accessMode = strtolower(trim($accessMode));
        if (!in_array($accessMode, ['code', 'public'], true)) {
            throw new HttpException('Неизвестный режим доступа к коду.', 400);
        }
        return $accessMode;
    }

    /** @return array<string, mixed> */
    private function badgeById(int $badgeId): array
    {
        $statement = $this->db->prepare(
            'SELECT `id`, `badgeName`, `description`, `img` FROM `badgesList` WHERE `id` = :id LIMIT 1'
        );
        $statement->execute([':id' => $badgeId]);
        $badge = $statement->fetch(PDO::FETCH_ASSOC);
        if (!is_array($badge)) {
            throw new HttpException('Бейдж не найден.', 404);
        }
        return $badge;
    }


    /** @return array<string, mixed> */
    private function lockedPublicKeyByBadgeName(string $badgeName): array
    {
        $statement = $this->db->prepare(
            'SELECT `key`.`id` AS `publicKeyId`, `key`.`badgeId`, `key`.`tokenHash`, '
            . '`badge`.`id`, `badge`.`badgeName`, `badge`.`description`, `badge`.`img` '
            . 'FROM `badgeClaimKeys` AS `key` '
            . 'INNER JOIN `badgesList` AS `badge` ON `badge`.`id` = `key`.`badgeId` '
            . "WHERE `badge`.`badgeName` = :badgeName AND `key`.`accessMode` = 'public' "
            . "AND `key`.`enabled` = 1 AND `key`.`usageMode` = 'reusable' "
            . 'ORDER BY `key`.`id` DESC LIMIT 1 LOCK IN SHARE MODE'
        );
        $statement->execute([':badgeName' => $badgeName]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            throw new HttpException('Этот бейдж недоступен для публичного получения.', 404);
        }
        return $row;
    }

    /** @param array<string, mixed> $badge */
    private function badgeResponse(array $badge, int $acquiredAt): array
    {
        $badgeName = trim((string)($badge['badgeName'] ?? ''));
        return [
            'id' => (int)($badge['id'] ?? 0),
            'badgeName' => $badgeName,
            'title' => $badgeName,
            'description' => trim((string)($badge['description'] ?? '')),
            'image' => trim((string)($badge['img'] ?? '')) ?: null,
            'acquiredAt' => $acquiredAt,
        ];
    }

    /** @return array<string, mixed> */
    private function keyById(int $keyId): array
    {
        $statement = $this->db->prepare(
            'SELECT `key`.`id`, `key`.`badgeId`, `key`.`tokenHint`, `key`.`usageMode`, `key`.`accessMode`, `key`.`usesCount`, `key`.`enabled`, '
            . '`key`.`createdAt`, `key`.`updatedAt`, `key`.`createdByUuid`, '
            . '`badge`.`badgeName`, COUNT(`claim`.`id`) AS `claimsCount`, '
            . 'MAX(`claim`.`claimedAt`) AS `lastClaimedAt` '
            . 'FROM `badgeClaimKeys` AS `key` '
            . 'INNER JOIN `badgesList` AS `badge` ON `badge`.`id` = `key`.`badgeId` '
            . 'LEFT JOIN `badgeKeyClaims` AS `claim` ON `claim`.`keyId` = `key`.`id` '
            . 'WHERE `key`.`id` = :id '
            . 'GROUP BY `key`.`id`, `key`.`badgeId`, `key`.`tokenHint`, `key`.`usageMode`, `key`.`accessMode`, `key`.`usesCount`, `key`.`enabled`, '
            . '`key`.`createdAt`, `key`.`updatedAt`, `key`.`createdByUuid`, `badge`.`badgeName` LIMIT 1'
        );
        $statement->execute([':id' => $keyId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            throw new HttpException('Ключ получения бейджа не найден.', 404);
        }
        return $this->normalizeKeyRow($row);
    }

    /** @return array<string, mixed> */
    private function lockedUser(string $uuid): array
    {
        $placeholders = [];
        $parameters = [];
        foreach (Uuid::databaseCandidates($uuid) as $index => $candidate) {
            $placeholder = ':uuid_' . $index;
            $placeholders[] = $placeholder;
            $parameters[$placeholder] = $candidate;
        }
        $statement = $this->db->prepare(
            'SELECT `uuid`, `badges` FROM `users` '
            . 'WHERE `uuid` IN (' . implode(', ', $placeholders) . ') LIMIT 1 FOR UPDATE'
        );
        $statement->execute($parameters);
        $user = $statement->fetch(PDO::FETCH_ASSOC);
        if (!is_array($user)) {
            throw new HttpException('Пользователь не найден.', 404);
        }
        return $user;
    }

    /** @return list<mixed> */
    private function decodeAssignments(mixed $value): array
    {
        $decoded = $value;
        if (!is_array($decoded)) {
            $raw = trim((string)$value);
            if ($raw === '') {
                return [];
            }
            $decoded = json_decode($raw, true);
        }
        if (!is_array($decoded) || $decoded === []) {
            return [];
        }
        if (array_is_list($decoded)) {
            return array_values($decoded);
        }
        if (array_key_exists('badgeName', $decoded)
            || array_key_exists('id', $decoded)
            || array_key_exists('name', $decoded)
            || array_key_exists('title', $decoded)
        ) {
            return [$decoded];
        }

        $assignments = [];
        foreach ($decoded as $badgeName => $acquiredAt) {
            $name = trim((string)$badgeName);
            if ($name === '') {
                continue;
            }
            $assignments[] = [
                'badgeName' => $name,
                'acquiredAt' => $acquiredAt,
            ];
        }
        return $assignments;
    }

    /** @param list<mixed> $assignments */
    private function containsBadge(array $assignments, string $badgeName): bool
    {
        $needle = mb_strtolower(trim($badgeName), 'UTF-8');
        foreach ($assignments as $assignment) {
            $name = '';
            if (is_string($assignment) || is_int($assignment) || is_float($assignment)) {
                $name = trim((string)$assignment);
            } elseif (is_array($assignment)) {
                $candidate = $assignment['badgeName'] ?? $assignment['id'] ?? $assignment['name'] ?? $assignment['title'] ?? '';
                $name = is_string($candidate) || is_numeric($candidate) ? trim((string)$candidate) : '';
            }
            if ($name !== '' && mb_strtolower($name, 'UTF-8') === $needle) {
                return true;
            }
        }
        return false;
    }

    /** @return array<string, mixed> */
    private function normalizeKeyRow(array $row): array
    {
        return [
            'id' => (int)($row['id'] ?? 0),
            'badgeId' => (int)($row['badgeId'] ?? 0),
            'badgeName' => (string)($row['badgeName'] ?? ''),
            'tokenHint' => (string)($row['tokenHint'] ?? ''),
            'usageMode' => in_array((string)($row['usageMode'] ?? ''), ['single', 'reusable'], true)
                ? (string)$row['usageMode']
                : 'single',
            'accessMode' => in_array((string)($row['accessMode'] ?? ''), ['code', 'public'], true)
                ? (string)$row['accessMode']
                : 'code',
            'usesCount' => (int)($row['usesCount'] ?? 0),
            'enabled' => (int)($row['enabled'] ?? 0) === 1,
            'createdAt' => (int)($row['createdAt'] ?? 0),
            'updatedAt' => (int)($row['updatedAt'] ?? 0),
            'createdByUuid' => (string)($row['createdByUuid'] ?? ''),
            'claimsCount' => (int)($row['claimsCount'] ?? 0),
            'lastClaimedAt' => isset($row['lastClaimedAt']) ? (int)$row['lastClaimedAt'] : null,
        ];
    }

    private function base64Url(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }
}
