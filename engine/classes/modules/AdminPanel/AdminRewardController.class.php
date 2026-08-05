<?php

declare(strict_types=1);

/**
 * Administrative reward use-cases extracted from the AdminOptions facade.
 */
final class AdminRewardController
{
    private RewardClaimService $rewardClaims;

    public function __construct(
        private db $db,
        private array $request,
        private UserSession $session,
        Logger $logger,
        private AdminRequestPayload $payload,
        private AdminResponder $responder,
        private AdminBadgeOptionsProvider $badgeOptions,
    ) {
        $this->rewardClaims = new RewardClaimService($db, $logger);
    }

    public function rewards(): void
    {
        $this->assertRewardAdministrationSchema();
        $this->responder->send([
            'rewards' => $this->rewardClaims->listDefinitions(),
            'claimKeys' => $this->rewardClaims->listKeys(),
            'badges' => $this->badgeOptions->all(),
        ]);
    }

    public function saveReward(): void
    {
        $this->assertRewardAdministrationSchema();
        $payload = $this->payload->object('entry');
        $definition = $this->rewardClaims->saveDefinition($payload, $this->session->uuid());
        $this->responder->send([
            'message' => 'Награда сохранена. Ключи выдачи настраиваются отдельно.',
            'type' => 'success',
            'reward' => $definition,
        ]);
    }

    public function deleteReward(): void
    {
        $this->assertRewardAdministrationSchema();
        $rewardId = max(0, (int)($this->request['rewardId'] ?? 0));
        $this->rewardClaims->deleteDefinition($rewardId, $this->session->uuid());
        $this->responder->send([
            'message' => 'Неиспользованная награда и её ключи удалены.',
            'type' => 'success',
        ]);
    }

    public function issueRewardClaimKey(): void
    {
        $this->assertRewardAdministrationSchema();
        $rewardId = max(0, (int)($this->request['rewardId'] ?? 0));
        $usageMode = strtolower(trim((string)($this->request['usageMode'] ?? 'single')));
        $accessMode = strtolower(trim((string)($this->request['accessMode'] ?? 'code')));
        $publicPlacement = trim((string)($this->request['publicPlacement'] ?? ''));
        $result = $this->rewardClaims->issue(
            $rewardId,
            $usageMode,
            $this->session->uuid(),
            $accessMode,
            $publicPlacement,
        );
        $public = ($result['entry']['accessMode'] ?? '') === 'public';
        $this->responder->send([
            'message' => $public
                ? 'Скрытый криптографический placement-ключ создан. Открытое значение уничтожено.'
                : 'Криптографический код создан. Сохраните его сейчас: повторно он показан не будет.',
            'type' => 'success',
            'token' => $result['token'],
            'entry' => $result['entry'],
        ], 201);
    }

    public function revokeRewardClaimKey(): void
    {
        $this->assertRewardAdministrationSchema();
        $keyId = max(0, (int)($this->request['keyId'] ?? 0));
        $entry = $this->rewardClaims->revoke($keyId, $this->session->uuid());
        $this->responder->send([
            'message' => 'Ключ награды отозван.',
            'type' => 'success',
            'entry' => $entry,
        ]);
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

}
