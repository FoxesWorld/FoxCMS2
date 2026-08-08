<?php

declare(strict_types=1);

/**
 * Immutable achievement-point ledger and atomic Points -> Units conversion.
 * Achievement score itself is never decremented; only exchangeable ledger credit is consumed.
 */
final class AchievementPointExchangeService
{
    public const DEFAULT_POINTS_PER_UNIT = 10;
    public const DEFAULT_MINIMUM_POINTS = 10;
    private const MAX_RATE = 1_000_000;
    private const MAX_MINIMUM = 1_000_000_000;

    public function __construct(
        private db $db,
        private ?Logger $logger = null,
    ) {
    }

    public function recordAward(
        string $serverId,
        string $playerUuid,
        string $achievementKey,
        int $points,
        int $awardedAt,
    ): bool {
        $statement = $this->db->prepare(
            'INSERT IGNORE INTO `gameAchievementPointAwards` '
            . '(`serverId`, `playerUuid`, `achievementKey`, `pointsAwarded`, `awardedAt`, `createdAt`) '
            . 'VALUES (:serverId, :playerUuid, :achievementKey, :pointsAwarded, :awardedAt, :createdAt)'
        );
        $statement->execute([
            ':serverId' => $serverId,
            ':playerUuid' => $playerUuid,
            ':achievementKey' => $achievementKey,
            ':pointsAwarded' => max(0, $points),
            ':awardedAt' => max(1, $awardedAt),
            ':createdAt' => time(),
        ]);
        return $statement->rowCount() > 0;
    }

    /** @return array<string,mixed> */
    public function state(string $playerUuid): array
    {
        $playerUuid = $this->userUuid($playerUuid);
        $settings = $this->settings();
        $totals = $this->totals($playerUuid);
        $user = $this->db->getRow(
            'SELECT `balance` FROM `users` WHERE `uuid` = :uuid LIMIT 1',
            [':uuid' => $playerUuid],
        );
        if (!is_array($user)) {
            throw new HttpException('Пользователь не найден.', 404);
        }
        return $this->statePayload($settings, $totals, $user['balance'] ?? null);
    }

    /** @return array<string,mixed> */
    public function exchange(string $playerUuid, int $points, string $requestUuid): array
    {
        $playerUuid = $this->userUuid($playerUuid);
        $requestUuid = $this->requestUuid($requestUuid);
        if ($points <= 0 || $points > BalanceMatrix::MAX_AMOUNT) {
            throw new HttpException('Количество очков для обмена должно быть положительным целым числом.', 400);
        }

        $result = $this->db->transactional(function () use ($playerUuid, $points, $requestUuid): array {
            $settings = $this->lockedSettings();
            if (($settings['enabled'] ?? false) !== true) {
                throw new HttpException('Обмен очков достижений временно отключён.', 409);
            }

            $userStatement = $this->db->prepare(
                'SELECT `uuid`, `balance` FROM `users` WHERE `uuid` = :uuid LIMIT 1 FOR UPDATE'
            );
            $userStatement->execute([':uuid' => $playerUuid]);
            $user = $userStatement->fetch(PDO::FETCH_ASSOC);
            if (!is_array($user)) {
                throw new HttpException('Пользователь не найден.', 404);
            }

            $existingStatement = $this->db->prepare(
                'SELECT `id`, `playerUuid`, `pointsSpent`, `unitsGranted`, `pointsPerUnit`, `createdAt` '
                . 'FROM `gameAchievementPointExchanges` WHERE `requestUuid` = :requestUuid LIMIT 1 FOR UPDATE'
            );
            $existingStatement->execute([':requestUuid' => $requestUuid]);
            $existing = $existingStatement->fetch(PDO::FETCH_ASSOC);
            if (is_array($existing)) {
                if ((string)($existing['playerUuid'] ?? '') !== $playerUuid) {
                    throw new HttpException('Идентификатор операции уже использован.', 409);
                }
                return [
                    'duplicate' => true,
                    'exchange' => $this->normalizeExchange($existing, $requestUuid),
                    'state' => $this->statePayload($settings, $this->totals($playerUuid), $user['balance'] ?? null),
                    'balanceJson' => BalanceMatrix::encode($user['balance'] ?? null),
                ];
            }

            $rate = (int)$settings['pointsPerUnit'];
            $minimum = (int)$settings['minimumPoints'];
            if ($points < $minimum) {
                throw new HttpException('Минимальный обмен: ' . $minimum . ' очков.', 400);
            }
            if ($points % $rate !== 0) {
                throw new HttpException('Количество очков должно быть кратно текущему курсу: ' . $rate . ' очков за 1 Unit.', 400);
            }

            $totals = $this->totals($playerUuid);
            $available = max(0, (int)$totals['earnedPoints'] - (int)$totals['exchangedPoints']);
            if ($points > $available) {
                throw new HttpException('Недостаточно доступных очков достижений для обмена.', 409);
            }
            $units = intdiv($points, $rate);
            if ($units <= 0 || $units > BalanceMatrix::MAX_AMOUNT) {
                throw new HttpException('Результат обмена выходит за допустимые пределы баланса.', 409);
            }

            try {
                $balance = BalanceMatrix::increment($user['balance'] ?? null, 'units', $units);
                $balanceJson = BalanceMatrix::encode($balance);
            } catch (InvalidArgumentException $error) {
                throw new HttpException('Не удалось начислить Units: ' . $error->getMessage(), 409, [], $error);
            }

            $update = $this->db->prepare('UPDATE `users` SET `balance` = :balance WHERE `uuid` = :uuid');
            $update->execute([':balance' => $balanceJson, ':uuid' => $playerUuid]);

            $now = time();
            $insert = $this->db->prepare(
                'INSERT INTO `gameAchievementPointExchanges` '
                . '(`requestUuid`, `playerUuid`, `pointsSpent`, `unitsGranted`, `pointsPerUnit`, `createdAt`) '
                . 'VALUES (:requestUuid, :playerUuid, :pointsSpent, :unitsGranted, :pointsPerUnit, :createdAt)'
            );
            $insert->execute([
                ':requestUuid' => $requestUuid,
                ':playerUuid' => $playerUuid,
                ':pointsSpent' => $points,
                ':unitsGranted' => $units,
                ':pointsPerUnit' => $rate,
                ':createdAt' => $now,
            ]);
            $exchangeId = (int)$this->db->lastInsertId();

            $nextTotals = [
                'earnedPoints' => (int)$totals['earnedPoints'],
                'exchangedPoints' => (int)$totals['exchangedPoints'] + $points,
                'lifetimeUnits' => (int)$totals['lifetimeUnits'] + $units,
                'exchangeCount' => (int)$totals['exchangeCount'] + 1,
            ];
            return [
                'duplicate' => false,
                'exchange' => [
                    'id' => $exchangeId,
                    'requestUuid' => $requestUuid,
                    'pointsSpent' => $points,
                    'unitsGranted' => $units,
                    'pointsPerUnit' => $rate,
                    'createdAt' => $now,
                ],
                'state' => $this->statePayload($settings, $nextTotals, $balance),
                'balanceJson' => $balanceJson,
            ];
        });

        if (($result['duplicate'] ?? false) !== true && $this->logger instanceof Logger) {
            $exchange = is_array($result['exchange'] ?? null) ? $result['exchange'] : [];
            $this->logger->event(
                'achievement.points.exchanged',
                'Achievement points converted to Units.',
                [
                    'component' => 'achievement_economy',
                    'operation' => 'exchange_points',
                    'playerUuid' => $playerUuid,
                    'requestUuid' => $requestUuid,
                    'pointsSpent' => (int)($exchange['pointsSpent'] ?? 0),
                    'unitsGranted' => (int)($exchange['unitsGranted'] ?? 0),
                    'pointsPerUnit' => (int)($exchange['pointsPerUnit'] ?? 0),
                ],
                'INFO',
                'success',
            );
        }
        return $result;
    }

    /** @return array{enabled:bool,pointsPerUnit:int,minimumPoints:int,updatedAt:int,updatedByUuid:string} */
    public function settings(): array
    {
        $row = $this->db->getRow(
            'SELECT `enabled`, `pointsPerUnit`, `minimumPoints`, `updatedAt`, `updatedByUuid` '
            . 'FROM `gameAchievementEconomySettings` WHERE `id` = 1 LIMIT 1'
        );
        if (!is_array($row)) {
            throw new HttpException('Настройки экономики достижений отсутствуют. Примените миграцию 027.', 503);
        }
        return $this->normalizeSettings($row);
    }

    /** @return array{enabled:bool,pointsPerUnit:int,minimumPoints:int,updatedAt:int,updatedByUuid:string} */
    public function saveSettings(bool $enabled, int $pointsPerUnit, int $minimumPoints, string $administratorUuid): array
    {
        $administratorUuid = $this->userUuid($administratorUuid);
        if ($pointsPerUnit < 1 || $pointsPerUnit > self::MAX_RATE) {
            throw new HttpException('Курс должен быть от 1 до ' . self::MAX_RATE . ' очков за Unit.', 400);
        }
        if ($minimumPoints < $pointsPerUnit || $minimumPoints > self::MAX_MINIMUM || $minimumPoints % $pointsPerUnit !== 0) {
            throw new HttpException('Минимальный обмен должен быть не меньше курса и кратен ему.', 400);
        }
        $now = time();
        $statement = $this->db->prepare(
            'UPDATE `gameAchievementEconomySettings` SET `enabled` = :enabled, `pointsPerUnit` = :pointsPerUnit, '
            . '`minimumPoints` = :minimumPoints, `updatedAt` = :updatedAt, `updatedByUuid` = :updatedByUuid WHERE `id` = 1'
        );
        $statement->execute([
            ':enabled' => $enabled ? 1 : 0,
            ':pointsPerUnit' => $pointsPerUnit,
            ':minimumPoints' => $minimumPoints,
            ':updatedAt' => $now,
            ':updatedByUuid' => $administratorUuid,
        ]);
        if ($statement->rowCount() === 0) {
            // Row may be unchanged; assert it exists rather than treating an idempotent save as failure.
            $this->settings();
        }
        $settings = $this->settings();
        if ($this->logger instanceof Logger) {
            $this->logger->event(
                'admin.achievements.economy_updated',
                'Achievement point exchange settings updated.',
                [
                    'component' => 'admin_achievements',
                    'operation' => 'save_economy',
                    'actorUuid' => $administratorUuid,
                    'enabled' => $enabled,
                    'pointsPerUnit' => $pointsPerUnit,
                    'minimumPoints' => $minimumPoints,
                ],
                'WARNING',
                'success',
            );
        }
        return $settings;
    }

    /** @return array<string,int> */
    public function statistics(): array
    {
        $awards = $this->db->getRow(
            'SELECT COUNT(*) AS `awardCount`, COUNT(DISTINCT `playerUuid`) AS `awardedPlayers`, '
            . 'COALESCE(SUM(`pointsAwarded`), 0) AS `earnedPoints` FROM `gameAchievementPointAwards`'
        ) ?: [];
        $exchanges = $this->db->getRow(
            'SELECT COUNT(*) AS `exchangeCount`, COUNT(DISTINCT `playerUuid`) AS `exchangePlayers`, '
            . 'COALESCE(SUM(`pointsSpent`), 0) AS `exchangedPoints`, '
            . 'COALESCE(SUM(`unitsGranted`), 0) AS `unitsGranted` FROM `gameAchievementPointExchanges`'
        ) ?: [];
        $earned = max(0, (int)($awards['earnedPoints'] ?? 0));
        $spent = max(0, (int)($exchanges['exchangedPoints'] ?? 0));
        return [
            'awardCount' => max(0, (int)($awards['awardCount'] ?? 0)),
            'awardedPlayers' => max(0, (int)($awards['awardedPlayers'] ?? 0)),
            'earnedPoints' => $earned,
            'exchangeCount' => max(0, (int)($exchanges['exchangeCount'] ?? 0)),
            'exchangePlayers' => max(0, (int)($exchanges['exchangePlayers'] ?? 0)),
            'exchangedPoints' => $spent,
            'availablePoints' => max(0, $earned - $spent),
            'unitsGranted' => max(0, (int)($exchanges['unitsGranted'] ?? 0)),
        ];
    }

    /** @return array{earnedPoints:int,exchangedPoints:int,lifetimeUnits:int,exchangeCount:int} */
    private function totals(string $playerUuid): array
    {
        $row = $this->db->getRow(
            'SELECT '
            . '(SELECT COALESCE(SUM(`pointsAwarded`), 0) FROM `gameAchievementPointAwards` WHERE `playerUuid` = :awardUuid) AS `earnedPoints`, '
            . '(SELECT COALESCE(SUM(`pointsSpent`), 0) FROM `gameAchievementPointExchanges` WHERE `playerUuid` = :spentUuid) AS `exchangedPoints`, '
            . '(SELECT COALESCE(SUM(`unitsGranted`), 0) FROM `gameAchievementPointExchanges` WHERE `playerUuid` = :unitsUuid) AS `lifetimeUnits`, '
            . '(SELECT COUNT(*) FROM `gameAchievementPointExchanges` WHERE `playerUuid` = :countUuid) AS `exchangeCount`',
            [
                ':awardUuid' => $playerUuid,
                ':spentUuid' => $playerUuid,
                ':unitsUuid' => $playerUuid,
                ':countUuid' => $playerUuid,
            ],
        ) ?: [];
        return [
            'earnedPoints' => max(0, (int)($row['earnedPoints'] ?? 0)),
            'exchangedPoints' => max(0, (int)($row['exchangedPoints'] ?? 0)),
            'lifetimeUnits' => max(0, (int)($row['lifetimeUnits'] ?? 0)),
            'exchangeCount' => max(0, (int)($row['exchangeCount'] ?? 0)),
        ];
    }

    /** @return array{enabled:bool,pointsPerUnit:int,minimumPoints:int,updatedAt:int,updatedByUuid:string} */
    private function lockedSettings(): array
    {
        $statement = $this->db->prepare(
            'SELECT `enabled`, `pointsPerUnit`, `minimumPoints`, `updatedAt`, `updatedByUuid` '
            . 'FROM `gameAchievementEconomySettings` WHERE `id` = 1 LIMIT 1 FOR UPDATE'
        );
        $statement->execute();
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            throw new HttpException('Настройки экономики достижений отсутствуют. Примените миграцию 027.', 503);
        }
        return $this->normalizeSettings($row);
    }

    /** @param array<string,mixed> $settings @param array<string,mixed> $totals @return array<string,mixed> */
    private function statePayload(array $settings, array $totals, mixed $balance): array
    {
        $earned = max(0, (int)($totals['earnedPoints'] ?? 0));
        $spent = max(0, (int)($totals['exchangedPoints'] ?? 0));
        $available = max(0, $earned - $spent);
        $rate = max(1, (int)($settings['pointsPerUnit'] ?? self::DEFAULT_POINTS_PER_UNIT));
        $maxExchangeable = intdiv($available, $rate) * $rate;
        $matrix = BalanceMatrix::normalize($balance);
        $unitBalance = 0;
        foreach ($matrix['currencies'] ?? [] as $currency) {
            if (is_array($currency) && ($currency['code'] ?? '') === 'units') {
                $unitBalance = max(0, (int)($currency['amount'] ?? 0));
                break;
            }
        }
        return [
            'enabled' => ($settings['enabled'] ?? false) === true,
            'pointsPerUnit' => $rate,
            'minimumPoints' => max($rate, (int)($settings['minimumPoints'] ?? self::DEFAULT_MINIMUM_POINTS)),
            'earnedPoints' => $earned,
            'exchangedPoints' => $spent,
            'availablePoints' => $available,
            'maxExchangeablePoints' => $maxExchangeable,
            'exchangeableUnits' => intdiv($maxExchangeable, $rate),
            'lifetimeUnits' => max(0, (int)($totals['lifetimeUnits'] ?? 0)),
            'exchangeCount' => max(0, (int)($totals['exchangeCount'] ?? 0)),
            'unitBalance' => $unitBalance,
            'currencyCode' => 'units',
            'currencyName' => 'Units',
            'currencySymbol' => 'U',
        ];
    }

    /** @return array{enabled:bool,pointsPerUnit:int,minimumPoints:int,updatedAt:int,updatedByUuid:string} */
    private function normalizeSettings(array $row): array
    {
        return [
            'enabled' => (int)($row['enabled'] ?? 0) === 1,
            'pointsPerUnit' => max(1, (int)($row['pointsPerUnit'] ?? self::DEFAULT_POINTS_PER_UNIT)),
            'minimumPoints' => max(1, (int)($row['minimumPoints'] ?? self::DEFAULT_MINIMUM_POINTS)),
            'updatedAt' => max(0, (int)($row['updatedAt'] ?? 0)),
            'updatedByUuid' => trim((string)($row['updatedByUuid'] ?? '')),
        ];
    }

    /** @return array<string,mixed> */
    private function normalizeExchange(array $row, string $requestUuid): array
    {
        return [
            'id' => max(0, (int)($row['id'] ?? 0)),
            'requestUuid' => $requestUuid,
            'pointsSpent' => max(0, (int)($row['pointsSpent'] ?? 0)),
            'unitsGranted' => max(0, (int)($row['unitsGranted'] ?? 0)),
            'pointsPerUnit' => max(1, (int)($row['pointsPerUnit'] ?? 1)),
            'createdAt' => max(0, (int)($row['createdAt'] ?? 0)),
        ];
    }

    private function userUuid(string $value): string
    {
        if (!Uuid::isValid($value)) {
            throw new HttpException('Некорректный UUID пользователя.', 400);
        }
        return Uuid::normalize($value);
    }

    private function requestUuid(string $value): string
    {
        if (!Uuid::isValid($value)) {
            throw new HttpException('Некорректный идентификатор операции обмена.', 400);
        }
        return Uuid::normalize($value);
    }
}
