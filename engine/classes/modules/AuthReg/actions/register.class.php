<?php

declare(strict_types=1);

if (!defined('auth')) {
    http_response_code(403);
    exit('Forbidden');
}

final class Register
{
    public function __construct(
        private HttpRequest $request,
        private db $db,
        private Logger $logger,
        private UserSession $session,
        private array $config,
    ) {
    }

    public function register(): never
    {
        global $lang;

        CsrfToken::requireValid($this->request->csrfToken());

        $login = $this->request->string('login');
        $email = mb_strtolower($this->request->string('email'));
        $password = $this->request->string('password1');
        $confirmation = $this->request->string('password2');
        $maxLoginLength = max(3, min(64, (int)($this->config['register']['maxLoginLength'] ?? 16)));
        $minPasswordLength = max(8, (int)($this->config['register']['passminCount'] ?? 10));

        if (preg_match('/^[A-Za-z0-9_.-]{3,' . $maxLoginLength . '}$/', $login) !== 1) {
            $this->respond(
                'Логин должен содержать 3–' . $maxLoginLength . ' латинских символов, цифр, точек, дефисов или подчёркиваний.',
                'error',
                422,
            );
        }
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false || mb_strlen($email) > 254) {
            $this->respond($lang['noEmail'] ?? 'Укажите корректную электронную почту.', 'error', 422);
        }
        if ($password !== $confirmation) {
            $this->respond($lang['passUnequals'] ?? 'Пароли не совпадают.', 'error', 422);
        }
        if (
            strlen($password) < $minPasswordLength
            || strlen($password) > 72
            || preg_match('/[А-Яа-яЁё]/u', $password) === 1
        ) {
            $this->respond(
                'Пароль должен содержать от ' . $minPasswordLength . ' до 72 символов без кириллицы.',
                'error',
                422,
            );
        }

        $duplicate = $this->db->prepare(
            'SELECT `login`, `email` FROM `users` WHERE `login` = :login OR `email` = :email LIMIT 1'
        );
        $duplicate->execute([':login' => $login, ':email' => $email]);
        $existing = $duplicate->fetch(PDO::FETCH_ASSOC);
        if (is_array($existing)) {
            $message = strcasecmp((string)($existing['login'] ?? ''), $login) === 0
                ? ($lang['loginUsed'] ?? 'Этот логин уже используется.')
                : ($lang['emailUsed'] ?? 'Эта почта уже используется.');
            $this->respond($message, 'warn', 409);
        }

        $group = $this->resolveGroup($this->request->string('regCode'));
        $userUuid = Uuid::v7();
        $profilePhoto = UPLOADS_DIR . USR_SUBFOLDER . 'anonymous/avatar.jpg';
        $realname = trim($this->request->string('realname', $login));
        if ($realname === '' || mb_strlen($realname) > 64) {
            $realname = $login;
        }
        $clientIp = $this->request->clientIp();

        try {
            $user = $this->db->transactional(function () use (
                $login,
                $email,
                $password,
                $group,
                $realname,
                $clientIp,
                $profilePhoto,
                $userUuid,
            ): array {
                $insert = $this->db->prepare(
                    'INSERT INTO `users` '
                    . '(`login`, `uuid`, `password`, `email`, `user_group`, `realname`, `reg_date`, `reg_ip`, `logged_ip`, `last_date`, `profilePhoto`) '
                    . 'VALUES (:login, :uuid, :password, :email, :user_group, :realname, :reg_date, :reg_ip, :logged_ip, :last_date, :profilePhoto)'
                );
                $insert->execute([
                    ':login' => $login,
                    ':uuid' => $userUuid,
                    ':password' => authorize::hashPassword($password),
                    ':email' => $email,
                    ':user_group' => $group,
                    ':realname' => $realname,
                    ':reg_date' => CURRENT_TIME,
                    ':reg_ip' => $clientIp,
                    ':logged_ip' => $clientIp,
                    ':last_date' => CURRENT_TIME,
                    ':profilePhoto' => $profilePhoto,
                ]);

                $select = $this->db->prepare(
                    'SELECT `uuid`, `user_id`, `email`, `login`, `user_group`, `realname`, `reg_date`, `last_date`, '
                    . '`logged_ip`, `profilePhoto`, `userStatus`, `land`, `colorScheme`, `badges`, `balance`, '
                    . '`serversOnline`, `userPerms` '
                    . 'FROM `users` WHERE `uuid` = :uuid LIMIT 1'
                );
                $select->execute([':uuid' => $userUuid]);
                $registered = $select->fetch(PDO::FETCH_ASSOC);
                if (!is_array($registered)) {
                    throw new RuntimeException('Registered user could not be loaded.');
                }
                return $registered;
            });

            $this->session->authenticate($user);
            $folder = $this->session->userFolder();
            if (!is_dir($folder) && !mkdir($folder, 0750, true) && !is_dir($folder)) {
                $this->logger->logError('Unable to create user directory for ' . $login);
            }

            $this->logger->logInfo('User registered: ' . $login . ' from ' . $clientIp);
            $this->sendWelcomeMail($email, $login);
            $this->respond($lang['regComplete'] ?? 'Регистрация завершена.', 'success');
        } catch (Throwable $error) {
            $this->logger->logError('Registration failed for ' . $login . ': ' . $error->getMessage());
            $this->respond('Не удалось создать аккаунт.', 'error', 500);
        }
    }

    private function resolveGroup(string $code): int
    {
        $default = max(1, (int)($this->config['register']['baseUserGroup'] ?? 4));
        if ($code === '' || preg_match('/^[A-Za-z0-9_-]{4,64}$/', $code) !== 1) {
            return $default;
        }

        $statement = $this->db->prepare('SELECT `groupNum` FROM `regCodes` WHERE `code` = :code LIMIT 1');
        $statement->execute([':code' => $code]);
        $group = $statement->fetchColumn();
        return $group === false ? $default : max(1, (int)$group);
    }

    private function sendWelcomeMail(string $email, string $login): void
    {
        try {
            UtilityLoader::load('FoxMail', '1.0.0');
            $mail = new FoxMail(true);
            $mail->send($email, 'Добро пожаловать в FoxesCraft', 'welcome.html', ['username' => $login]);
        } catch (Throwable $error) {
            $this->logger->logError('Welcome mail failed for ' . $login . ': ' . $error->getMessage());
        }
    }

    private function respond(string $message, string $type, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=UTF-8');
        header('Cache-Control: no-store');
        $encoded = json_encode(
            ['message' => $message, 'type' => $type],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        );
        if (!is_string($encoded)) {
            throw new RuntimeException('Unable to encode registration response.');
        }
        exit($encoded);
    }
}
