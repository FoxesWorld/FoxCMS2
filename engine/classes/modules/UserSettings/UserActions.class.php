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
            'claimBadge' => 'claimBadge',
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
            'claimBadge' => $this->claimBadge(),
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


    private function claimBadge(): never
    {
        if (!$this->session->isLogged()) {
            $this->respond(['message' => 'Нужно войти в аккаунт.', 'type' => 'error'], 401);
        }
        CsrfToken::requireValid($this->request->csrfToken());

        $claimCode = trim($this->request->string('claimCode'));
        $badgeName = trim($this->request->string('badgeName'));
        if (($claimCode === '') === ($badgeName === '')) {
            $this->respond([
                'message' => 'Передайте либо код получения, либо название публичного бейджа.',
                'type' => 'error',
            ], 400);
        }

        $service = new BadgeClaimService($this->db, $this->logger);
        try {
            $result = $claimCode !== ''
                ? $service->claim($claimCode, $this->session->uuid())
                : $service->claimPublic($badgeName, $this->session->uuid());
        } catch (HttpException $error) {
            $this->respond([
                'message' => $error->getMessage(),
                'type' => 'error',
            ], $error->status());
        }
        $this->session->set('badges', $result['badgesJson'], true);

        $badge = is_array($result['badge'] ?? null) ? $result['badge'] : [];
        $badgeName = trim((string)($badge['badgeName'] ?? 'Бейдж'));
        $alreadyOwned = ($result['alreadyOwned'] ?? false) === true;
        $this->respond([
            'message' => $alreadyOwned
                ? 'Бейдж «' . $badgeName . '» уже есть в вашем профиле.'
                : 'Бейдж «' . $badgeName . '» добавлен в ваш профиль.',
            'type' => $alreadyOwned ? 'warning' : 'success',
            'alreadyOwned' => $alreadyOwned,
            'badge' => $badge,
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
