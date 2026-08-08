<?php

declare(strict_types=1);

namespace FoxCMS\Engine\User;

use \AchievementPointExchangeService;
use \CsrfToken;
use \HttpException;

final class UserAchievementController
{
    public function __construct(
        private readonly \db $db,
        private readonly \Logger $logger,
        private readonly \HttpRequest $request,
        private readonly \UserSession $session,
        private readonly AuthenticatedUserGuard $guard,
        private readonly UserActionResponder $responder,
    ) {
    }

    public function getEconomy(): never
    {
        $userUuid = $this->guard->requireRewardAccess();
        try {
            $state = (new AchievementPointExchangeService($this->db, $this->logger))->state($userUuid);
        } catch (HttpException $error) {
            $this->responder->send(['message' => $error->getMessage(), 'type' => 'error'], $error->status());
        }
        $this->responder->send(['economy' => $state]);
    }

    public function exchangePoints(): never
    {
        $userUuid = $this->guard->requireRewardAccess();
        CsrfToken::requireValid($this->request->csrfToken());
        $pointsRaw = trim($this->request->string('points'));
        if (preg_match('/^[0-9]{1,16}$/D', $pointsRaw) !== 1) {
            $this->responder->send(['message' => 'Укажите корректное количество очков для обмена.', 'type' => 'error'], 400);
        }
        $points = (int)$pointsRaw;
        $requestUuid = trim($this->request->string('requestUuid'));
        try {
            $result = (new AchievementPointExchangeService($this->db, $this->logger))->exchange(
                $userUuid,
                $points,
                $requestUuid,
            );
        } catch (HttpException $error) {
            $this->responder->send(['message' => $error->getMessage(), 'type' => 'error'], $error->status());
        }
        if (isset($result['balanceJson'])) {
            $this->session->set('balance', (string)$result['balanceJson'], true);
        }
        $exchange = is_array($result['exchange'] ?? null) ? $result['exchange'] : [];
        $duplicate = ($result['duplicate'] ?? false) === true;
        $this->responder->send([
            'type' => $duplicate ? 'warning' : 'success',
            'message' => $duplicate
                ? 'Эта операция обмена уже была выполнена.'
                : sprintf(
                    'Обменено %d очков достижений на %d Units.',
                    (int)($exchange['pointsSpent'] ?? 0),
                    (int)($exchange['unitsGranted'] ?? 0),
                ),
            'duplicate' => $duplicate,
            'exchange' => $exchange,
            'economy' => $result['state'] ?? null,
            'balance' => isset($result['balanceJson'])
                ? json_decode((string)$result['balanceJson'], true)
                : null,
        ]);
    }
}
