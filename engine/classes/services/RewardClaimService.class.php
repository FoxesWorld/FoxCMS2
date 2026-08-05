<?php

declare(strict_types=1);

final class RewardClaimService
{
    private const TOKEN_PREFIX = 'fcr_';
    private const TOKEN_PATTERN = '/^(?:fcr|fcb)_[A-Za-z0-9_-]{43}$/D';

    public function __construct(
        private db $db,
        private Logger $logger,
    ) {
    }

    /** @return list<array<string, mixed>> */
    public function listDefinitions(): array
    {
        $statement = $this->db->query(
            'SELECT `reward`.`id`, `reward`.`rewardName`, `reward`.`description`, `reward`.`badgeId`, '
            . '`reward`.`currencyCode`, `reward`.`currencyAmount`, `reward`.`enabled`, '
            . '`reward`.`createdAt`, `reward`.`updatedAt`, `reward`.`createdByUuid`, `reward`.`updatedByUuid`, '
            . '`badge`.`badgeName`, `badge`.`description` AS `badgeDescription`, `badge`.`img` AS `badgeImage`, '
            . 'COUNT(DISTINCT `key`.`id`) AS `keysCount`, COUNT(DISTINCT `claim`.`id`) AS `claimsCount` '
            . 'FROM `rewardDefinitions` AS `reward` '
            . 'LEFT JOIN `badgesList` AS `badge` ON `badge`.`id` = `reward`.`badgeId` '
            . 'LEFT JOIN `rewardClaimKeys` AS `key` ON `key`.`rewardId` = `reward`.`id` '
            . 'LEFT JOIN `rewardClaims` AS `claim` ON `claim`.`rewardId` = `reward`.`id` '
            . 'GROUP BY `reward`.`id`, `reward`.`rewardName`, `reward`.`description`, `reward`.`badgeId`, '
            . '`reward`.`currencyCode`, `reward`.`currencyAmount`, `reward`.`enabled`, '
            . '`reward`.`createdAt`, `reward`.`updatedAt`, `reward`.`createdByUuid`, `reward`.`updatedByUuid`, '
            . '`badge`.`badgeName`, `badge`.`description`, `badge`.`img` '
            . 'ORDER BY `reward`.`rewardName`, `reward`.`id`'
        );
        if (!$statement instanceof PDOStatement) {
            throw new RuntimeException('Database query returned no reward definition statement.');
        }
        return array_values(array_map(
            fn (array $row): array => $this->normalizeDefinitionRow($row),
            $statement->fetchAll(PDO::FETCH_ASSOC) ?: [],
        ));
    }

    /** @return array<string, mixed> */
    public function saveDefinition(array $payload, string $administratorUuid): array
    {
        $administratorUuid = $this->normalizeAdministratorUuid($administratorUuid);
        $id = max(0, (int)($payload['id'] ?? 0));
        $name = trim((string)($payload['rewardName'] ?? ''));
        $description = trim((string)($payload['description'] ?? ''));
        $badgeId = max(0, (int)($payload['badgeId'] ?? 0));
        $enabled = filter_var($payload['enabled'] ?? true, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        if ($enabled === null) {
            throw new HttpException('Состояние награды должно быть логическим значением.', 400);
        }
        if ($name === '' || mb_strlen($name, 'UTF-8') > 160
            || preg_match('/[\x00-\x1F\x7F]/u', $name) === 1) {
            throw new HttpException('Название награды должно содержать от 1 до 160 печатных Unicode-символов.', 400);
        }
        if (mb_strlen($description, 'UTF-8') > 4000 || str_contains($description, "\0")) {
            throw new HttpException('Описание награды не должно превышать 4000 символов.', 400);
        }

        $amountValue = $payload['currencyAmount'] ?? 0;
        if ($amountValue === '' || $amountValue === null) {
            $amountValue = 0;
        }
        $amount = filter_var($amountValue, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 0, 'max_range' => BalanceMatrix::MAX_AMOUNT],
        ]);
        if ($amount === false) {
            throw new HttpException('Количество валюты должно быть целым неотрицательным числом.', 400);
        }
        $amount = (int)$amount;
        $currencyCode = strtolower(trim((string)($payload['currencyCode'] ?? '')));
        if ($amount === 0) {
            $currencyCode = '';
        } else {
            if ($currencyCode === '') {
                throw new HttpException('Для положительного количества необходимо выбрать валюту.', 400);
            }
            try {
                $currencyCode = (string)BalanceMatrix::currencyDefinition($currencyCode)['code'];
            } catch (InvalidArgumentException $error) {
                throw new HttpException('Выбрана неизвестная валюта награды.', 400, [], $error);
            }
        }
        if ($badgeId === 0 && $amount === 0) {
            throw new HttpException('Награда должна содержать бейдж, валюту или оба компонента.', 400);
        }
        if ($badgeId > 0) {
            $this->badgeById($badgeId);
        }

        $now = time();
        $storedId = $this->db->transactional(function () use (
            $id, $name, $description, $badgeId, $currencyCode, $amount, $enabled, $administratorUuid, $now,
        ): int {
            $duplicate = $this->db->prepare(
                'SELECT `id` FROM `rewardDefinitions` WHERE `rewardName` = :rewardName AND `id` <> :id LIMIT 1 FOR UPDATE'
            );
            $duplicate->execute([':rewardName' => $name, ':id' => $id]);
            if (is_array($duplicate->fetch(PDO::FETCH_ASSOC))) {
                throw new HttpException('Награда с таким названием уже существует.', 409);
            }
            if ($id > 0) {
                $currentStatement = $this->db->prepare(
                    'SELECT `badgeId`, `currencyCode`, `currencyAmount`, `enabled` '
                    . 'FROM `rewardDefinitions` WHERE `id` = :id LIMIT 1 FOR UPDATE'
                );
                $currentStatement->execute([':id' => $id]);
                $current = $currentStatement->fetch(PDO::FETCH_ASSOC);
                if (!is_array($current)) {
                    throw new HttpException('Награда для обновления не найдена.', 404);
                }
                $payloadChanged = (int)($current['badgeId'] ?? 0) !== $badgeId
                    || trim((string)($current['currencyCode'] ?? '')) !== $currencyCode
                    || (int)($current['currencyAmount'] ?? 0) !== $amount;
                $disabledNow = (int)($current['enabled'] ?? 0) === 1 && !$enabled;

                $statement = $this->db->prepare(
                    'UPDATE `rewardDefinitions` SET `rewardName` = :rewardName, `description` = :description, '
                    . '`badgeId` = :badgeId, `currencyCode` = :currencyCode, `currencyAmount` = :currencyAmount, '
                    . '`enabled` = :enabled, `updatedAt` = :updatedAt, `updatedByUuid` = :updatedByUuid '
                    . 'WHERE `id` = :id'
                );
                $statement->execute([
                    ':rewardName' => $name,
                    ':description' => $description,
                    ':badgeId' => $badgeId > 0 ? $badgeId : null,
                    ':currencyCode' => $currencyCode !== '' ? $currencyCode : null,
                    ':currencyAmount' => $amount,
                    ':enabled' => $enabled ? 1 : 0,
                    ':updatedAt' => $now,
                    ':updatedByUuid' => $administratorUuid,
                    ':id' => $id,
                ]);
                if ($payloadChanged || $disabledNow) {
                    $revoke = $this->db->prepare(
                        'UPDATE `rewardClaimKeys` SET `enabled` = 0, `publicPlacement` = NULL, `updatedAt` = :updatedAt '
                        . 'WHERE `rewardId` = :rewardId AND `enabled` = 1'
                    );
                    $revoke->execute([':updatedAt' => $now, ':rewardId' => $id]);
                }
                return $id;
            }

            $statement = $this->db->prepare(
                'INSERT INTO `rewardDefinitions` '
                . '(`rewardName`, `description`, `badgeId`, `currencyCode`, `currencyAmount`, `enabled`, '
                . '`createdAt`, `updatedAt`, `createdByUuid`, `updatedByUuid`) '
                . 'VALUES (:rewardName, :description, :badgeId, :currencyCode, :currencyAmount, :enabled, '
                . ':createdAt, :updatedAt, :createdByUuid, :updatedByUuid)'
            );
            $statement->execute([
                ':rewardName' => $name,
                ':description' => $description,
                ':badgeId' => $badgeId > 0 ? $badgeId : null,
                ':currencyCode' => $currencyCode !== '' ? $currencyCode : null,
                ':currencyAmount' => $amount,
                ':enabled' => $enabled ? 1 : 0,
                ':createdAt' => $now,
                ':updatedAt' => $now,
                ':createdByUuid' => $administratorUuid,
                ':updatedByUuid' => $administratorUuid,
            ]);
            $createdId = (int)$this->db->lastInsertId();
            if ($createdId <= 0) {
                throw new RuntimeException('Database did not return the created reward identifier.');
            }
            return $createdId;
        });

        $definition = $this->definitionById($storedId);
        $this->logger->event(
            'reward.definition.saved',
            'Reward definition saved.',
            [
                'component' => 'rewards',
                'operation' => $id > 0 ? 'update_definition' : 'create_definition',
                'rewardId' => $storedId,
                'rewardName' => (string)$definition['rewardName'],
                'badgeId' => (int)$definition['badgeId'],
                'currencyCode' => (string)$definition['currencyCode'],
                'currencyAmount' => (int)$definition['currencyAmount'],
                'enabled' => (bool)$definition['enabled'],
                'administratorUuid' => $administratorUuid,
            ],
            'INFO',
            'success',
        );
        return $definition;
    }

    public function deleteDefinition(int $rewardId, string $administratorUuid): void
    {
        if ($rewardId <= 0) {
            throw new HttpException('Некорректный идентификатор награды.', 400);
        }
        $administratorUuid = $this->normalizeAdministratorUuid($administratorUuid);
        $definition = $this->db->transactional(function () use ($rewardId): array {
            $lock = $this->db->prepare(
                'SELECT `id`, `rewardName` FROM `rewardDefinitions` WHERE `id` = :rewardId LIMIT 1 FOR UPDATE'
            );
            $lock->execute([':rewardId' => $rewardId]);
            $definition = $lock->fetch(PDO::FETCH_ASSOC);
            if (!is_array($definition)) {
                throw new HttpException('Награда не найдена.', 404);
            }
            $claim = $this->db->prepare(
                'SELECT `id` FROM `rewardClaims` WHERE `rewardId` = :rewardId LIMIT 1 FOR UPDATE'
            );
            $claim->execute([':rewardId' => $rewardId]);
            if (is_array($claim->fetch(PDO::FETCH_ASSOC))) {
                throw new HttpException('Награду с историей выдач нельзя удалить. Отключите её, чтобы сохранить аудит.', 409);
            }
            $delete = $this->db->prepare('DELETE FROM `rewardDefinitions` WHERE `id` = :rewardId');
            $delete->execute([':rewardId' => $rewardId]);
            return $definition;
        });
        $this->logger->event(
            'reward.definition.deleted',
            'Unused reward definition deleted.',
            [
                'component' => 'rewards',
                'operation' => 'delete_definition',
                'rewardId' => $rewardId,
                'rewardName' => (string)$definition['rewardName'],
                'administratorUuid' => $administratorUuid,
            ],
            'INFO',
            'success',
        );
    }

    /** @return list<array<string, mixed>> */
    public function listKeys(): array
    {
        $statement = $this->db->query(
            'SELECT `key`.`id`, `key`.`rewardId`, `key`.`tokenHint`, `key`.`usageMode`, `key`.`accessMode`, '
            . '`key`.`publicPlacement`, `key`.`usesCount`, `key`.`enabled`, `key`.`createdAt`, `key`.`updatedAt`, '
            . '`key`.`createdByUuid`, `reward`.`rewardName`, `reward`.`badgeId`, `reward`.`currencyCode`, '
            . '`reward`.`currencyAmount`, `badge`.`badgeName`, '
            . 'COUNT(`claim`.`id`) AS `claimsCount`, MAX(`claim`.`claimedAt`) AS `lastClaimedAt` '
            . 'FROM `rewardClaimKeys` AS `key` '
            . 'INNER JOIN `rewardDefinitions` AS `reward` ON `reward`.`id` = `key`.`rewardId` '
            . 'LEFT JOIN `badgesList` AS `badge` ON `badge`.`id` = `reward`.`badgeId` '
            . 'LEFT JOIN `rewardClaims` AS `claim` ON `claim`.`keyId` = `key`.`id` '
            . 'GROUP BY `key`.`id`, `key`.`rewardId`, `key`.`tokenHint`, `key`.`usageMode`, `key`.`accessMode`, '
            . '`key`.`publicPlacement`, `key`.`usesCount`, `key`.`enabled`, `key`.`createdAt`, `key`.`updatedAt`, '
            . '`key`.`createdByUuid`, `reward`.`rewardName`, `reward`.`badgeId`, `reward`.`currencyCode`, '
            . '`reward`.`currencyAmount`, `badge`.`badgeName` '
            . 'ORDER BY `reward`.`rewardName`, `key`.`id`'
        );
        if (!$statement instanceof PDOStatement) {
            throw new RuntimeException('Database query returned no reward claim key statement.');
        }
        return array_values(array_map(
            fn (array $row): array => $this->normalizeKeyRow($row),
            $statement->fetchAll(PDO::FETCH_ASSOC) ?: [],
        ));
    }

    /** @return array{token:?string, entry:array<string, mixed>} */
    public function issue(
        int $rewardId,
        string $usageMode,
        string $createdByUuid,
        string $accessMode = 'code',
        string $publicPlacement = '',
    ): array {
        if ($rewardId <= 0) {
            throw new HttpException('Некорректный идентификатор награды.', 400);
        }
        $creator = $this->normalizeAdministratorUuid($createdByUuid);
        $definition = $this->definitionById($rewardId);
        if (($definition['enabled'] ?? false) !== true) {
            throw new HttpException('Нельзя выпустить ключ для отключённой награды.', 409);
        }
        $accessMode = $this->normalizeAccessMode($accessMode);
        $usageMode = $accessMode === 'public' ? 'reusable' : $this->normalizeUsageMode($usageMode);
        $placement = $accessMode === 'public' ? $this->normalizePlacement($publicPlacement) : null;

        $issued = $this->db->transactional(function () use (
            $rewardId, $usageMode, $creator, $accessMode, $placement,
        ): array {
            if ($placement !== null) {
                $release = $this->db->prepare(
                    'UPDATE `rewardClaimKeys` SET `enabled` = 0, `publicPlacement` = NULL, `updatedAt` = :updatedAt '
                    . 'WHERE `publicPlacement` = :publicPlacement'
                );
                $release->execute([':updatedAt' => time(), ':publicPlacement' => $placement]);
            }
            return $this->createKey($rewardId, $usageMode, $creator, $accessMode, $placement);
        });
        $entry = $this->keyById((int)$issued['keyId']);
        $this->logger->event(
            'reward.claim_key.issued',
            'Reward claim key created.',
            [
                'component' => 'rewards',
                'operation' => 'issue_key',
                'rewardId' => $rewardId,
                'rewardName' => (string)$definition['rewardName'],
                'keyId' => (int)$entry['id'],
                'usageMode' => $usageMode,
                'accessMode' => $accessMode,
                'publicPlacement' => $placement ?? '',
                'createdByUuid' => $creator,
            ],
            'INFO',
            'success',
        );
        return [
            'token' => $accessMode === 'code' ? (string)$issued['token'] : null,
            'entry' => $entry,
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
            'UPDATE `rewardClaimKeys` SET `enabled` = 0, `updatedAt` = :updatedAt WHERE `id` = :id'
        );
        $statement->execute([':updatedAt' => time(), ':id' => $keyId]);
        if ($statement->rowCount() === 0) {
            throw new HttpException('Ключ награды не найден или уже отозван.', 404);
        }
        $entry = $this->keyById($keyId);
        $this->logger->event(
            'reward.claim_key.revoked',
            'Reward claim key revoked.',
            [
                'component' => 'rewards',
                'operation' => 'revoke_key',
                'rewardId' => (int)$entry['rewardId'],
                'keyId' => $keyId,
                'revokedByUuid' => $revoker,
            ],
            'INFO',
            'success',
        );
        return $entry;
    }

    /** @return array<string, mixed> */
    public function publicOffer(string $placement, string $userUuid): array
    {
        $placement = $this->normalizePlacement($placement);
        $targetUuid = $this->normalizeUserUuid($userUuid);
        $offer = $this->publicOfferByPlacement($placement);
        return $this->offerResponse($offer, $this->hasClaim((int)$offer['rewardId'], $targetUuid));
    }

    /** @return array<string, mixed> */
    public function claimPublicOffer(string $placement, string $userUuid): array
    {
        $placement = $this->normalizePlacement($placement);
        $targetUuid = $this->normalizeUserUuid($userUuid);
        $offer = $this->publicOfferByPlacement($placement);
        $result = $this->db->transactional(fn (): array => $this->claimByHash(
            (string)$offer['tokenHash'],
            $targetUuid,
            'public-reward:' . $placement,
        ));
        $result['offer'] = $this->offerResponse($offer, true);
        $this->logClaimResult($result, $targetUuid, 'public', $placement);
        return $this->publicClaimResult($result);
    }

    /** @return array<string, mixed> */
    public function claim(string $token, string $userUuid): array
    {
        $token = trim($token);
        if (preg_match(self::TOKEN_PATTERN, $token) !== 1) {
            throw new HttpException('Некорректный формат криптографического ключа награды.', 400);
        }
        $targetUuid = $this->normalizeUserUuid($userUuid);
        $result = $this->db->transactional(fn (): array => $this->claimByHash(
            hash('sha256', $token),
            $targetUuid,
            'reward-key',
        ));
        $this->logClaimResult($result, $targetUuid, 'code', '');
        return $this->publicClaimResult($result);
    }

    /** @param array<string, mixed> $result */
    private function logClaimResult(array $result, string $userUuid, string $accessMode, string $placement): void
    {
        $reward = is_array($result['reward'] ?? null) ? $result['reward'] : [];
        $badge = is_array($result['badge'] ?? null) ? $result['badge'] : null;
        $currency = is_array($result['currency'] ?? null) ? $result['currency'] : null;
        $this->logger->event(
            'reward.claim.completed',
            'Reward claim completed through a cryptographic key.',
            [
                'component' => 'rewards',
                'operation' => 'claim_reward',
                'rewardId' => (int)($reward['id'] ?? 0),
                'rewardName' => (string)($reward['rewardName'] ?? ''),
                'keyId' => (int)($result['keyId'] ?? 0),
                'userUuid' => $userUuid,
                'accessMode' => $accessMode,
                'publicPlacement' => $placement,
                'alreadyClaimed' => (bool)($result['alreadyClaimed'] ?? false),
                'badgeApplied' => (bool)($result['badgeApplied'] ?? false),
                'badgeId' => (int)($badge['id'] ?? 0),
                'badgeName' => (string)($badge['badgeName'] ?? ''),
                'currencyApplied' => (bool)($result['currencyApplied'] ?? false),
                'currencyCode' => (string)($currency['currencyCode'] ?? ''),
                'currencyAmount' => (int)($currency['amount'] ?? 0),
            ],
            'INFO',
            'success',
        );
    }

    /** @return array<string, mixed> */
    private function claimByHash(string $tokenHash, string $userUuid, string $assignmentSource): array
    {
        $statement = $this->db->prepare(
            'SELECT `key`.`id`, `key`.`rewardId`, `key`.`usageMode`, `key`.`usesCount`, `key`.`enabled` AS `keyEnabled`, '
            . '`reward`.`rewardName`, `reward`.`description` AS `rewardDescription`, `reward`.`badgeId`, '
            . '`reward`.`currencyCode`, `reward`.`currencyAmount`, `reward`.`enabled` AS `rewardEnabled`, '
            . '`badge`.`badgeName`, `badge`.`description` AS `badgeDescription`, `badge`.`img` AS `badgeImage` '
            . 'FROM `rewardClaimKeys` AS `key` '
            . 'INNER JOIN `rewardDefinitions` AS `reward` ON `reward`.`id` = `key`.`rewardId` '
            . 'LEFT JOIN `badgesList` AS `badge` ON `badge`.`id` = `reward`.`badgeId` '
            . 'WHERE `key`.`tokenHash` = :tokenHash LIMIT 1 FOR UPDATE'
        );
        $statement->execute([':tokenHash' => $tokenHash]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            throw new HttpException('Криптографический ключ награды не найден.', 404);
        }
        if ((int)($row['keyEnabled'] ?? 0) !== 1) {
            throw new HttpException('Ключ награды отозван.', 410);
        }
        if ((int)($row['rewardEnabled'] ?? 0) !== 1) {
            throw new HttpException('Награда отключена администратором.', 410);
        }

        $user = $this->lockedUser($userUuid);
        $existing = $this->db->prepare(
            'SELECT `id`, `claimedAt` FROM `rewardClaims` '
            . 'WHERE `rewardId` = :rewardId AND `userUuid` = :userUuid LIMIT 1 FOR UPDATE'
        );
        $existing->execute([':rewardId' => (int)$row['rewardId'], ':userUuid' => $userUuid]);
        if (is_array($existing->fetch(PDO::FETCH_ASSOC))) {
            return $this->alreadyClaimedResult($row, $user);
        }

        $usageMode = (string)($row['usageMode'] ?? 'single');
        $usesCount = (int)($row['usesCount'] ?? 0);
        if (!in_array($usageMode, ['single', 'reusable'], true)) {
            throw new HttpException('Режим использования ключа повреждён.', 409);
        }
        if ($usageMode === 'single' && $usesCount >= 1) {
            throw new HttpException('Одноразовый ключ уже использован другим профилем.', 409);
        }

        $assignments = $this->decodeAssignments($user['badges'] ?? null);
        $badgeApplied = false;
        $badge = $this->badgeResponse($row, time());
        if ($badge !== null && !$this->containsBadge($assignments, (string)$badge['badgeName'])) {
            $assignments[] = [
                'badgeName' => (string)$badge['badgeName'],
                'acquiredAt' => time(),
                'source' => $assignmentSource,
            ];
            $badgeApplied = true;
        }
        $badgesJson = json_encode(
            array_values($assignments),
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
        );

        $currency = $this->currencyResponse($row);
        $currencyApplied = $currency !== null;
        $balance = $currencyApplied
            ? BalanceMatrix::increment(
                $user['balance'] ?? null,
                (string)$currency['currencyCode'],
                (int)$currency['amount'],
            )
            : BalanceMatrix::normalize($user['balance'] ?? null);
        $balanceJson = BalanceMatrix::encode($balance);

        $update = $this->db->prepare(
            'UPDATE `users` SET `badges` = :badges, `balance` = :balance WHERE `uuid` = :uuid'
        );
        $update->execute([
            ':badges' => $badgesJson,
            ':balance' => $balanceJson,
            ':uuid' => (string)$user['uuid'],
        ]);

        $now = time();
        $claim = $this->db->prepare(
            'INSERT INTO `rewardClaims` '
            . '(`rewardId`, `keyId`, `userUuid`, `badgeGranted`, `badgeId`, `badgeName`, '
            . '`currencyCode`, `currencyAmount`, `claimedAt`) '
            . 'VALUES (:rewardId, :keyId, :userUuid, :badgeGranted, :badgeId, :badgeName, '
            . ':currencyCode, :currencyAmount, :claimedAt)'
        );
        $claim->execute([
            ':rewardId' => (int)$row['rewardId'],
            ':keyId' => (int)$row['id'],
            ':userUuid' => $userUuid,
            ':badgeGranted' => $badgeApplied ? 1 : 0,
            ':badgeId' => $badge !== null ? (int)$badge['id'] : null,
            ':badgeName' => $badge !== null ? (string)$badge['badgeName'] : null,
            ':currencyCode' => $currency !== null ? (string)$currency['currencyCode'] : null,
            ':currencyAmount' => $currency !== null ? (int)$currency['amount'] : 0,
            ':claimedAt' => $now,
        ]);
        $consume = $this->db->prepare(
            'UPDATE `rewardClaimKeys` SET `usesCount` = `usesCount` + 1, `updatedAt` = :updatedAt WHERE `id` = :id'
        );
        $consume->execute([':updatedAt' => $now, ':id' => (int)$row['id']]);

        $reward = $this->rewardResponse($row);
        $notifications = new NotificationService($this->db);
        $notifications->notifyRewardClaimed(
            $userUuid,
            $reward,
            $badgeApplied ? $badge : null,
            $currencyApplied ? $currency : null,
        );
        if ($badgeApplied && $badge !== null) {
            $notifications->notifyBadgeAwarded($userUuid, $badge, $assignmentSource);
        }

        return [
            'reward' => $reward,
            'badge' => $badge,
            'currency' => $currency,
            'alreadyClaimed' => false,
            'badgeApplied' => $badgeApplied,
            'currencyApplied' => $currencyApplied,
            'badgesJson' => $badgesJson,
            'balance' => $balance,
            'balanceJson' => $balanceJson,
            'keyId' => (int)$row['id'],
            'usageMode' => $usageMode,
        ];
    }

    /** @return array<string, mixed> */
    private function alreadyClaimedResult(array $row, array $user): array
    {
        $assignments = $this->decodeAssignments($user['badges'] ?? null);
        $badgesJson = json_encode(
            array_values($assignments),
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
        );
        $balance = BalanceMatrix::normalize($user['balance'] ?? null);
        return [
            'reward' => $this->rewardResponse($row),
            'badge' => $this->badgeResponse($row, time()),
            'currency' => $this->currencyResponse($row),
            'alreadyClaimed' => true,
            'badgeApplied' => false,
            'currencyApplied' => false,
            'badgesJson' => $badgesJson,
            'balance' => $balance,
            'balanceJson' => BalanceMatrix::encode($balance),
            'keyId' => (int)($row['id'] ?? 0),
            'usageMode' => (string)($row['usageMode'] ?? 'reusable'),
        ];
    }

    /** @return array{token:string, keyId:int} */
    private function createKey(
        int $rewardId,
        string $usageMode,
        string $createdByUuid,
        string $accessMode,
        ?string $publicPlacement,
    ): array {
        $token = self::TOKEN_PREFIX . $this->base64Url(random_bytes(32));
        $statement = $this->db->prepare(
            'INSERT INTO `rewardClaimKeys` '
            . '(`rewardId`, `tokenHash`, `tokenHint`, `usageMode`, `accessMode`, `publicPlacement`, '
            . '`usesCount`, `enabled`, `createdAt`, `updatedAt`, `createdByUuid`) '
            . 'VALUES (:rewardId, :tokenHash, :tokenHint, :usageMode, :accessMode, :publicPlacement, '
            . '0, 1, :createdAt, :updatedAt, :createdByUuid)'
        );
        $now = time();
        $statement->execute([
            ':rewardId' => $rewardId,
            ':tokenHash' => hash('sha256', $token),
            ':tokenHint' => substr($token, -10),
            ':usageMode' => $usageMode,
            ':accessMode' => $accessMode,
            ':publicPlacement' => $publicPlacement,
            ':createdAt' => $now,
            ':updatedAt' => $now,
            ':createdByUuid' => $createdByUuid,
        ]);
        $keyId = (int)$this->db->lastInsertId();
        if ($keyId <= 0) {
            throw new RuntimeException('Database did not return the created reward claim key identifier.');
        }
        return ['token' => $token, 'keyId' => $keyId];
    }

    /** @return array<string, mixed> */
    private function publicOfferByPlacement(string $placement): array
    {
        $statement = $this->db->prepare(
            'SELECT `key`.`id`, `key`.`rewardId`, `key`.`tokenHash`, `key`.`publicPlacement`, '
            . '`reward`.`rewardName`, `reward`.`description` AS `rewardDescription`, `reward`.`badgeId`, '
            . '`reward`.`currencyCode`, `reward`.`currencyAmount`, '
            . '`badge`.`badgeName`, `badge`.`description` AS `badgeDescription`, `badge`.`img` AS `badgeImage` '
            . 'FROM `rewardClaimKeys` AS `key` '
            . 'INNER JOIN `rewardDefinitions` AS `reward` ON `reward`.`id` = `key`.`rewardId` '
            . 'LEFT JOIN `badgesList` AS `badge` ON `badge`.`id` = `reward`.`badgeId` '
            . "WHERE `key`.`publicPlacement` = :placement AND `key`.`accessMode` = 'public' "
            . "AND `key`.`enabled` = 1 AND `key`.`usageMode` = 'reusable' AND `reward`.`enabled` = 1 "
            . 'ORDER BY `key`.`id` DESC LIMIT 1'
        );
        $statement->execute([':placement' => $placement]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            throw new HttpException('Для этого размещения не выпущена активная награда.', 404);
        }
        return $row;
    }

    /** @return array<string, mixed> */
    private function offerResponse(array $row, bool $acquired): array
    {
        return [
            'placement' => (string)($row['publicPlacement'] ?? ''),
            'reward' => $this->rewardResponse($row),
            'acquired' => $acquired,
            'claimable' => !$acquired,
        ];
    }

    /** @return array<string, mixed> */
    private function publicClaimResult(array $result): array
    {
        return [
            'reward' => $result['reward'],
            'badge' => $result['badge'],
            'currency' => $result['currency'],
            'alreadyClaimed' => (bool)$result['alreadyClaimed'],
            'badgeApplied' => (bool)$result['badgeApplied'],
            'currencyApplied' => (bool)$result['currencyApplied'],
            'badgesJson' => (string)$result['badgesJson'],
            'balance' => $result['balance'],
            'balanceJson' => (string)$result['balanceJson'],
            'offer' => $result['offer'] ?? null,
        ];
    }

    /** @return array<string, mixed> */
    private function rewardResponse(array $row): array
    {
        return [
            'id' => (int)($row['rewardId'] ?? $row['id'] ?? 0),
            'rewardName' => trim((string)($row['rewardName'] ?? '')),
            'title' => trim((string)($row['rewardName'] ?? '')),
            'description' => trim((string)($row['rewardDescription'] ?? $row['description'] ?? '')),
            'badge' => $this->badgeResponse($row, 0),
            'currency' => $this->currencyResponse($row),
        ];
    }

    /** @return array<string, mixed>|null */
    private function badgeResponse(array $row, int $acquiredAt): ?array
    {
        $badgeId = (int)($row['badgeId'] ?? 0);
        $badgeName = trim((string)($row['badgeName'] ?? ''));
        if ($badgeId <= 0 || $badgeName === '') {
            return null;
        }
        return [
            'id' => $badgeId,
            'badgeName' => $badgeName,
            'title' => $badgeName,
            'description' => trim((string)($row['badgeDescription'] ?? '')),
            'image' => trim((string)($row['badgeImage'] ?? '')) ?: null,
            'acquiredAt' => $acquiredAt,
        ];
    }

    /** @return array<string, mixed>|null */
    private function currencyResponse(array $row): ?array
    {
        $amount = (int)($row['currencyAmount'] ?? 0);
        $code = strtolower(trim((string)($row['currencyCode'] ?? '')));
        if ($amount <= 0 || $code === '') {
            return null;
        }
        try {
            $currency = BalanceMatrix::currencyDefinition($code);
        } catch (InvalidArgumentException $error) {
            throw new HttpException('Конфигурация валютной части награды повреждена.', 409, [], $error);
        }
        if ($amount > BalanceMatrix::MAX_AMOUNT) {
            throw new HttpException('Количество валюты в награде превышает допустимый предел.', 409);
        }
        return [
            'currencyCode' => (string)$currency['code'],
            'currencyName' => (string)$currency['name'],
            'currencySymbol' => (string)$currency['symbol'],
            'amount' => $amount,
        ];
    }

    /** @return array<string, mixed> */
    private function definitionById(int $rewardId): array
    {
        $statement = $this->db->prepare(
            'SELECT `reward`.`id`, `reward`.`rewardName`, `reward`.`description`, `reward`.`badgeId`, '
            . '`reward`.`currencyCode`, `reward`.`currencyAmount`, `reward`.`enabled`, '
            . '`reward`.`createdAt`, `reward`.`updatedAt`, `reward`.`createdByUuid`, `reward`.`updatedByUuid`, '
            . '`badge`.`badgeName`, `badge`.`description` AS `badgeDescription`, `badge`.`img` AS `badgeImage`, '
            . '(SELECT COUNT(*) FROM `rewardClaimKeys` AS `key` WHERE `key`.`rewardId` = `reward`.`id`) AS `keysCount`, '
            . '(SELECT COUNT(*) FROM `rewardClaims` AS `claim` WHERE `claim`.`rewardId` = `reward`.`id`) AS `claimsCount` '
            . 'FROM `rewardDefinitions` AS `reward` '
            . 'LEFT JOIN `badgesList` AS `badge` ON `badge`.`id` = `reward`.`badgeId` '
            . 'WHERE `reward`.`id` = :rewardId LIMIT 1'
        );
        $statement->execute([':rewardId' => $rewardId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            throw new HttpException('Награда не найдена.', 404);
        }
        return $this->normalizeDefinitionRow($row);
    }

    /** @return array<string, mixed> */
    private function badgeById(int $badgeId): array
    {
        $statement = $this->db->prepare(
            'SELECT `id`, `badgeName`, `description`, `img` FROM `badgesList` WHERE `id` = :badgeId LIMIT 1'
        );
        $statement->execute([':badgeId' => $badgeId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            throw new HttpException('Выбранный бейдж не найден.', 404);
        }
        return $row;
    }

    /** @return array<string, mixed> */
    private function keyById(int $keyId): array
    {
        $statement = $this->db->prepare(
            'SELECT `key`.`id`, `key`.`rewardId`, `key`.`tokenHint`, `key`.`usageMode`, `key`.`accessMode`, '
            . '`key`.`publicPlacement`, `key`.`usesCount`, `key`.`enabled`, `key`.`createdAt`, `key`.`updatedAt`, '
            . '`key`.`createdByUuid`, `reward`.`rewardName`, `reward`.`badgeId`, `reward`.`currencyCode`, '
            . '`reward`.`currencyAmount`, `badge`.`badgeName`, '
            . 'COUNT(`claim`.`id`) AS `claimsCount`, MAX(`claim`.`claimedAt`) AS `lastClaimedAt` '
            . 'FROM `rewardClaimKeys` AS `key` '
            . 'INNER JOIN `rewardDefinitions` AS `reward` ON `reward`.`id` = `key`.`rewardId` '
            . 'LEFT JOIN `badgesList` AS `badge` ON `badge`.`id` = `reward`.`badgeId` '
            . 'LEFT JOIN `rewardClaims` AS `claim` ON `claim`.`keyId` = `key`.`id` '
            . 'WHERE `key`.`id` = :keyId '
            . 'GROUP BY `key`.`id`, `key`.`rewardId`, `key`.`tokenHint`, `key`.`usageMode`, `key`.`accessMode`, '
            . '`key`.`publicPlacement`, `key`.`usesCount`, `key`.`enabled`, `key`.`createdAt`, `key`.`updatedAt`, '
            . '`key`.`createdByUuid`, `reward`.`rewardName`, `reward`.`badgeId`, `reward`.`currencyCode`, '
            . '`reward`.`currencyAmount`, `badge`.`badgeName` LIMIT 1'
        );
        $statement->execute([':keyId' => $keyId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            throw new HttpException('Ключ награды не найден.', 404);
        }
        return $this->normalizeKeyRow($row);
    }

    private function hasClaim(int $rewardId, string $userUuid): bool
    {
        $statement = $this->db->prepare(
            'SELECT COUNT(*) FROM `rewardClaims` WHERE `rewardId` = :rewardId AND `userUuid` = :userUuid'
        );
        $statement->execute([':rewardId' => $rewardId, ':userUuid' => $userUuid]);
        return (int)$statement->fetchColumn() > 0;
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
            'SELECT `uuid`, `badges`, `balance` FROM `users` '
            . 'WHERE `uuid` IN (' . implode(', ', $placeholders) . ') LIMIT 1 FOR UPDATE'
        );
        $statement->execute($parameters);
        $user = $statement->fetch(PDO::FETCH_ASSOC);
        if (!is_array($user)) {
            throw new HttpException('Пользователь не найден.', 404);
        }
        return $user;
    }

    private function normalizeAdministratorUuid(string $uuid): string
    {
        if (!Uuid::isValid($uuid)) {
            throw new HttpException('Некорректный UUID администратора.', 400);
        }
        return Uuid::normalize($uuid);
    }

    private function normalizeUserUuid(string $uuid): string
    {
        if (!Uuid::isValid($uuid)) {
            throw new HttpException('Некорректный UUID пользователя.', 400);
        }
        return Uuid::normalize($uuid);
    }

    private function normalizeUsageMode(string $usageMode): string
    {
        $usageMode = strtolower(trim($usageMode));
        if (!in_array($usageMode, ['single', 'reusable'], true)) {
            throw new HttpException('Недопустимый режим использования ключа.', 400);
        }
        return $usageMode;
    }

    private function normalizeAccessMode(string $accessMode): string
    {
        $accessMode = strtolower(trim($accessMode));
        if (!in_array($accessMode, ['code', 'public'], true)) {
            throw new HttpException('Недопустимый режим доступа к награде.', 400);
        }
        return $accessMode;
    }

    private function normalizePlacement(string $placement): string
    {
        $placement = strtolower(trim($placement));
        if (preg_match('/^[a-z][a-z0-9._-]{0,63}$/D', $placement) !== 1) {
            throw new HttpException('Некорректный идентификатор публичного размещения.', 400);
        }
        return $placement;
    }

    /** @return array<string, mixed> */
    private function normalizeDefinitionRow(array $row): array
    {
        return [
            'id' => (int)($row['id'] ?? 0),
            'rewardName' => trim((string)($row['rewardName'] ?? '')),
            'description' => trim((string)($row['description'] ?? '')),
            'badgeId' => (int)($row['badgeId'] ?? 0),
            'badgeName' => trim((string)($row['badgeName'] ?? '')),
            'badgeDescription' => trim((string)($row['badgeDescription'] ?? '')),
            'badgeImage' => trim((string)($row['badgeImage'] ?? '')),
            'currencyCode' => trim((string)($row['currencyCode'] ?? '')),
            'currencyAmount' => (int)($row['currencyAmount'] ?? 0),
            'enabled' => (int)($row['enabled'] ?? 0) === 1,
            'createdAt' => (int)($row['createdAt'] ?? 0),
            'updatedAt' => (int)($row['updatedAt'] ?? 0),
            'createdByUuid' => trim((string)($row['createdByUuid'] ?? '')),
            'updatedByUuid' => trim((string)($row['updatedByUuid'] ?? '')),
            'keysCount' => (int)($row['keysCount'] ?? 0),
            'claimsCount' => (int)($row['claimsCount'] ?? 0),
        ];
    }

    /** @return array<string, mixed> */
    private function normalizeKeyRow(array $row): array
    {
        return [
            'id' => (int)($row['id'] ?? 0),
            'rewardId' => (int)($row['rewardId'] ?? 0),
            'rewardName' => trim((string)($row['rewardName'] ?? '')),
            'badgeId' => (int)($row['badgeId'] ?? 0),
            'badgeName' => trim((string)($row['badgeName'] ?? '')),
            'currencyCode' => trim((string)($row['currencyCode'] ?? '')),
            'currencyAmount' => (int)($row['currencyAmount'] ?? 0),
            'tokenHint' => trim((string)($row['tokenHint'] ?? '')),
            'usageMode' => in_array((string)($row['usageMode'] ?? ''), ['single', 'reusable'], true)
                ? (string)$row['usageMode'] : 'single',
            'accessMode' => in_array((string)($row['accessMode'] ?? ''), ['code', 'public'], true)
                ? (string)$row['accessMode'] : 'code',
            'publicPlacement' => trim((string)($row['publicPlacement'] ?? '')),
            'usesCount' => (int)($row['usesCount'] ?? 0),
            'enabled' => (int)($row['enabled'] ?? 0) === 1,
            'createdAt' => (int)($row['createdAt'] ?? 0),
            'updatedAt' => (int)($row['updatedAt'] ?? 0),
            'createdByUuid' => trim((string)($row['createdByUuid'] ?? '')),
            'claimsCount' => (int)($row['claimsCount'] ?? 0),
            'lastClaimedAt' => isset($row['lastClaimedAt']) ? (int)$row['lastClaimedAt'] : null,
        ];
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
        if (array_key_exists('badgeName', $decoded) || array_key_exists('id', $decoded)
            || array_key_exists('name', $decoded) || array_key_exists('title', $decoded)) {
            return [$decoded];
        }
        $assignments = [];
        foreach ($decoded as $badgeName => $acquiredAt) {
            $name = trim((string)$badgeName);
            if ($name !== '') {
                $assignments[] = ['badgeName' => $name, 'acquiredAt' => $acquiredAt];
            }
        }
        return $assignments;
    }

    /** @param list<mixed> $assignments */
    private function containsBadge(array $assignments, string $badgeName): bool
    {
        $needle = mb_strtolower(trim($badgeName), 'UTF-8');
        foreach ($assignments as $assignment) {
            $name = '';
            if (is_string($assignment) || is_numeric($assignment)) {
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

    private function base64Url(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }
}
