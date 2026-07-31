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
        'user_group',
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
        match ($action) {
            'EditUser' => $this->editUser(),
            'updateProfilePhoto' => $this->updateProfilePhoto(),
            'getUserData' => $this->getUserData(),
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
        (new UpdateProfilePhoto($this->db, $this->request, $this->session))->upload();
    }

    private function lostPassword(): never
    {
        require_once __DIR__ . '/actions/lostpassword.class.php';
        (new LostPassword($this->db, $this->config))->resetPass($this->request->string('email'));
    }

    private function resetPassword(): never
    {
        require_once __DIR__ . '/actions/resetpassword.class.php';
        (new ResetPassword($this->db))->reset(
            $this->request->string('token'),
            $this->request->string('new_password'),
            $this->request->string('confirm_password'),
        );
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
            . 'LEFT JOIN `groupAssociation` AS `group` ON `group`.`groupNum` = `user`.`user_group` '
            . 'WHERE `user`.`login` = :login LIMIT 1'
        );
        $statement->execute([':login' => $login]);
        $user = $statement->fetch(PDO::FETCH_ASSOC);
        if (!is_array($user)) {
            $this->respond(['message' => 'Пользователь не найден.', 'type' => 'error'], 404);
        }

        foreach (['badges', 'serversOnline', 'balance'] as $jsonField) {
            if (isset($user[$jsonField]) && is_string($user[$jsonField])) {
                $fallback = $jsonField === 'serversOnline' ? [] : [];
                $user[$jsonField] = json_decode($user[$jsonField], true) ?? $fallback;
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
        http_response_code($status);
        header('Content-Type: application/json; charset=UTF-8');
        header('Cache-Control: no-store');
        $encoded = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($encoded)) {
            throw new RuntimeException('Unable to encode user response.');
        }
        exit($encoded);
    }
}
