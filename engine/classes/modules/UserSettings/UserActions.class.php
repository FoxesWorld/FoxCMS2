<?php

declare(strict_types=1);

if (!defined('profile')) {
    http_response_code(403);
    exit('{"message":"Profile module is unavailable","type":"error"}');
}

final class UserActions
{
    private const PUBLIC_PROFILE_FIELDS = [
        'uuid',
        'login',
        'groupTag',
        'realname',
        'reg_date',
        'last_date',
        'profilePhoto',
        'userStatus',
        'land',
        'colorScheme',
        'badges',
        'serversOnline',
    ];

    public function __construct(
        private db $db,
        private Logger $logger,
        private HttpRequest $request,
        private UserSession $session,
        private array $config = [],
    ) {
        $this->dispatch($request->string('user_doaction'));
    }

    private function dispatch(string $action): void
    {
        $handler = match ($action) {
            'EditUser' => 'editUser',
            'updateProfilePhoto' => 'updateProfilePhoto',
            'getUserData' => 'getUserData',
            'getUserSettings' => 'getUserSettings',
            'getRewardOffer' => 'getRewardOffer',
            'getBadgeOffer' => 'getRewardOffer',
            'claimReward' => 'claimReward',
            'claimBadge' => 'claimReward',
            'lostpassword' => 'lostPassword',
            'resetpassword' => 'resetPassword',
            default => 'unresolved',
        };
        RequestTelemetry::identify('user_settings.' . $action, [
            'component' => 'user_settings',
            'action' => $action,
            'handler' => $handler,
            'moduleName' => 'UserSettings',
        ]);
        match ($action) {
            'EditUser' => $this->editUser(),
            'updateProfilePhoto' => $this->updateProfilePhoto(),
            'getUserData' => $this->getUserData(),
            'getUserSettings' => $this->getUserSettings(),
            'getRewardOffer' => $this->getRewardOffer(),
            'getBadgeOffer' => $this->getRewardOffer(),
            'claimReward' => $this->claimReward(),
            'claimBadge' => $this->claimReward(),
            'lostpassword' => $this->lostPassword(),
            'resetpassword' => $this->resetPassword(),
            default => $this->respond(['message' => 'Unknown user request.', 'type' => 'error'], 400),
        };
    }

    private function editUser(): never
    {
        require_once __DIR__ . '/actions/EditUser.class.php';
        (new EditUser(
            $this->request,
            $this->db,
            $this->logger,
            $this->session,
        ))->update();
    }

    private function updateProfilePhoto(): never
    {
        require_once __DIR__ . '/actions/updateProfilePhoto.class.php';
        (new UpdateProfilePhoto($this->db, $this->request, $this->session, $this->logger))->upload();
    }

    private function lostPassword(): never
    {
        require_once __DIR__ . '/actions/lostpassword.class.php';
        (new LostPassword($this->db, $this->logger, $this->config))->resetPass($this->request->string('email'));
    }

    private function resetPassword(): never
    {
        require_once __DIR__ . '/actions/resetpassword.class.php';
        (new ResetPassword($this->db, $this->logger))->reset(
            $this->request->string('token'),
            $this->request->string('new_password'),
            $this->request->string('confirm_password'),
        );
    }


    private function getRewardOffer(): never
    {
        if (!$this->session->isLogged()) {
            $this->respond(['message' => 'Нужно войти в аккаунт.', 'type' => 'error'], 401);
        }
        if ($this->session->group() === 'guest') {
            $this->respond(['message' => 'Гостевой профиль не может получать награды.', 'type' => 'error'], 403);
        }

        $placement = trim($this->request->string('placement'));
        $service = new RewardClaimService($this->db, $this->logger);
        try {
            $offer = $service->publicOffer($placement, $this->session->uuid());
        } catch (HttpException $error) {
            $this->respond([
                'message' => $error->getMessage(),
                'type' => 'error',
            ], $error->status());
        }

        $this->respond(['offer' => $offer]);
    }


    private function claimReward(): never
    {
        if (!$this->session->isLogged()) {
            $this->respond(['message' => 'Нужно войти в аккаунт.', 'type' => 'error'], 401);
        }
        if ($this->session->group() === 'guest') {
            $this->respond(['message' => 'Гостевой профиль не может получать награды.', 'type' => 'error'], 403);
        }
        CsrfToken::requireValid($this->request->csrfToken());

        $claimCode = trim($this->request->string('claimCode'));
        $offerPlacement = trim($this->request->string('offerPlacement'));
        $provided = (int)($claimCode !== '') + (int)($offerPlacement !== '');
        if ($provided !== 1) {
            $this->respond([
                'message' => 'Передайте криптографический код или расположение предложения с выпущенным placement-ключом.',
                'type' => 'error',
            ], 400);
        }

        $service = new RewardClaimService($this->db, $this->logger);
        try {
            $result = $claimCode !== ''
                ? $service->claim($claimCode, $this->session->uuid())
                : $service->claimPublicOffer($offerPlacement, $this->session->uuid());
        } catch (HttpException $error) {
            $this->respond([
                'message' => $error->getMessage(),
                'type' => 'error',
            ], $error->status());
        }

        $this->session->set('badges', $result['badgesJson'], true);
        if (isset($result['balanceJson'])) {
            $this->session->set('balance', $result['balanceJson'], true);
        }

        $reward = is_array($result['reward'] ?? null) ? $result['reward'] : [];
        $rewardTitle = trim((string)($reward['title'] ?? $reward['rewardName'] ?? 'Награда'));
        $alreadyClaimed = ($result['alreadyClaimed'] ?? false) === true;
        if ($alreadyClaimed) {
            $message = 'Награда «' . $rewardTitle . '» уже была получена этим профилем.';
        } else {
            $parts = [];
            $badge = is_array($result['badge'] ?? null) ? $result['badge'] : null;
            if (($result['badgeApplied'] ?? false) === true && $badge !== null) {
                $parts[] = 'добавлен бейдж «' . trim((string)($badge['badgeName'] ?? '')) . '»';
            }
            $currency = is_array($result['currency'] ?? null) ? $result['currency'] : null;
            if (($result['currencyApplied'] ?? false) === true && $currency !== null) {
                $parts[] = 'начислено ' . (int)($currency['amount'] ?? 0)
                    . ' ' . trim((string)($currency['currencyName'] ?? ''));
            }
            $message = 'Награда «' . $rewardTitle . '» получена.';
            if ($parts !== []) {
                $message .= ' ' . ucfirst(implode('; ', $parts)) . '.';
            }
        }

        $badges = json_decode((string)$result['badgesJson'], true);
        $this->respond([
            'message' => $message,
            'type' => $alreadyClaimed ? 'warning' : 'success',
            'alreadyClaimed' => $alreadyClaimed,
            'badgeApplied' => ($result['badgeApplied'] ?? false) === true,
            'currencyApplied' => ($result['currencyApplied'] ?? false) === true,
            'reward' => $reward,
            'badge' => $result['badge'] ?? null,
            'currency' => $result['currency'] ?? null,
            'offer' => $offer,
            'badges' => is_array($badges) ? $badges : [],
            'balance' => $result['balance'] ?? null,
        ]);
    }


    private function getUserSettings(): never
    {
        if (!$this->session->isLogged()) {
            $this->respond(['message' => 'Нужно войти в аккаунт.', 'type' => 'error'], 401);
        }

        $requestedUuid = $this->request->string('userUuid');
        $login = $this->request->string('login');
        if ($requestedUuid !== '' && !Uuid::isValid($requestedUuid)) {
            $this->respond(['message' => 'Некорректный UUID пользователя.', 'type' => 'error'], 400);
        }
        if ($requestedUuid === '' && preg_match('/^[A-Za-z0-9_.-]{3,64}$/D', $login) !== 1) {
            $this->respond(['message' => 'Некорректный логин.', 'type' => 'error'], 400);
        }

        $parameters = [];
        if ($requestedUuid !== '') {
            $placeholders = [];
            foreach (Uuid::databaseCandidates($requestedUuid) as $index => $candidate) {
                $placeholder = ':identity_' . $index;
                $placeholders[] = $placeholder;
                $parameters[$placeholder] = $candidate;
            }
            $where = '`uuid` IN (' . implode(', ', $placeholders) . ')';
        } else {
            $where = '`login` = :identity';
            $parameters[':identity'] = $login;
        }
        $statement = $this->db->prepare(
            'SELECT `uuid`, `login`, `groupTag`, `realname`, `email`, `profilePhoto`, '
            . '`userStatus`, `land`, `colorScheme` FROM `users` WHERE ' . $where . ' LIMIT 1'
        );
        $statement->execute($parameters);
        $user = $statement->fetch(PDO::FETCH_ASSOC);
        if (!is_array($user)) {
            $this->respond(['message' => 'Пользователь не найден.', 'type' => 'error'], 404);
        }

        $isOwner = Uuid::equals($this->session->uuid(), (string)$user['uuid']);
        if (!$isOwner && !$this->session->isAdmin()) {
            $this->respond(['message' => 'Недостаточно прав для изменения этого профиля.', 'type' => 'error'], 403);
        }
        $this->respond($user);
    }

    private function getUserData(): never
    {
        $login = $this->request->string('login');
        if (preg_match('/^[A-Za-z0-9_.-]{1,64}$/', $login) !== 1) {
            $this->respond(['message' => 'Некорректный логин.', 'type' => 'error'], 400);
        }

        $fields = implode(', ', array_map(
            static fn (string $field): string => '`user`.`' . $field . '` AS `' . $field . '`',
            self::PUBLIC_PROFILE_FIELDS,
        ));
        $statement = $this->db->prepare(
            'SELECT ' . $fields . ', '
            . '`user`.`balance` AS `balance`, '
            . '`group`.`groupName` AS `groupName`, '
            . '`group`.`groupColor` AS `groupColor` '
            . 'FROM `users` AS `user` '
            . 'LEFT JOIN `groupAssociation` AS `group` ON `group`.`groupTag` = `user`.`groupTag` '
            . 'WHERE `user`.`login` = :login LIMIT 1'
        );
        $statement->execute([':login' => $login]);
        $user = $statement->fetch(PDO::FETCH_ASSOC);
        if (!is_array($user)) {
            $this->respond(['message' => 'Пользователь не найден.', 'type' => 'error'], 404);
        }

        $user['balance'] = BalanceMatrix::normalize($user['balance'] ?? null);
        foreach (['badges', 'serversOnline'] as $jsonField) {
            if (isset($user[$jsonField]) && is_string($user[$jsonField])) {
                $user[$jsonField] = json_decode($user[$jsonField], true) ?? [];
            }
        }

        $isOwner = $this->session->isLogged()
            && Uuid::equals($this->session->uuid(), (string)$user['uuid']);
        if (!$isOwner && !$this->session->isAdmin()) {
            unset($user['balance']);
        }

        $this->respond($user);
    }

    private function respond(array $payload, int $status = 200): never
    {
        if ($status >= 400) {
            RequestTelemetry::rejectHttp(
                'user_settings.operation.rejected',
                $status,
                (string)($payload['message'] ?? 'User settings operation was rejected.'),
            );
        }
        JsonResponse::send($payload, $status);
    }
}
