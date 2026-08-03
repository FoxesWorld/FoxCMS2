<?php

declare(strict_types=1);

if (!defined('auth')) {
    http_response_code(403);
    exit('Forbidden');
}

final class Register
{
    private AuthInputValidator $validator;

    public function __construct(
        private HttpRequest $request,
        private db $db,
        private Logger $logger,
        private UserSession $session,
        private array $config,
    ) {
        $register = is_array($config['register'] ?? null) ? $config['register'] : [];
        $this->validator = new AuthInputValidator(
            (int)($register['maxLoginLength'] ?? 16),
            (int)($register['passminCount'] ?? 10),
        );
    }

    /** @return array<string, mixed> */
    public function register(): array
    {
        global $lang;

        $input = $this->validator->registration(
            $this->request->string('login'),
            $this->request->string('email'),
            $this->request->string('password1'),
            $this->request->string('password2'),
            $this->request->string('realname'),
            $this->request->string('regCode'),
        );

        $duplicate = $this->duplicateFailure($input['login'], $input['email']);
        if ($duplicate instanceof AuthFailure) {
            throw $duplicate;
        }

        $group = $this->resolveGroup($input['registrationCode']);
        $userUuid = Uuid::v7();
        $profilePhoto = UPLOADS_DIR . USR_SUBFOLDER . 'anonymous/avatar.jpg';
        $clientIp = $this->request->clientIp();

        try {
            $user = $this->db->transactional(function () use (
                $input,
                $group,
                $clientIp,
                $profilePhoto,
                $userUuid,
            ): array {
                $insert = $this->db->prepare(
                    'INSERT INTO `users` '
                    . '(`login`, `uuid`, `password`, `email`, `groupTag`, `realname`, '
                    . '`reg_date`, `reg_ip`, `logged_ip`, `last_date`, `profilePhoto`) '
                    . 'VALUES (:login, :uuid, :password, :email, :groupTag, :realname, '
                    . ':regDate, :regIp, :loggedIp, :lastDate, :profilePhoto)'
                );
                $insert->execute([
                    ':login' => $input['login'],
                    ':uuid' => $userUuid,
                    ':password' => authorize::hashPassword($input['password']),
                    ':email' => $input['email'],
                    ':groupTag' => $group,
                    ':realname' => $input['realname'],
                    ':regDate' => CURRENT_TIME,
                    ':regIp' => $clientIp,
                    ':loggedIp' => $clientIp,
                    ':lastDate' => CURRENT_TIME,
                    ':profilePhoto' => $profilePhoto,
                ]);

                $select = $this->db->prepare(
                    'SELECT `uuid`, `user_id`, `email`, `login`, `groupTag`, `realname`, '
                    . '`reg_date`, `last_date`, `logged_ip`, `profilePhoto`, `userStatus`, '
                    . '`land`, `colorScheme`, `badges`, `balance`, `serversOnline`, `userPerms` '
                    . 'FROM `users` WHERE `uuid` = :uuid LIMIT 1'
                );
                $select->execute([':uuid' => $userUuid]);
                $registered = $select->fetch(PDO::FETCH_ASSOC);
                if (!is_array($registered)) {
                    throw new RuntimeException('Registered account could not be loaded after insertion.');
                }
                return $registered;
            });
        } catch (Throwable $error) {
            if ($this->isIntegrityViolation($error)) {
                $duplicate = $this->duplicateFailure($input['login'], $input['email']);
                if ($duplicate instanceof AuthFailure) {
                    throw $duplicate;
                }
            }
            throw $error;
        }

        $authenticated = $this->authenticateCreatedAccount($user);
        $folderCreated = $this->ensureUserDirectory($userUuid);
        $mailSent = $this->sendWelcomeMail($input['email'], $input['login']);

        $this->logger->event(
            'auth.registration.completed',
            'User registration completed.',
            [
                'component' => 'authentication',
                'operation' => 'register',
                'targetUserUuid' => $userUuid,
                'assignedGroup' => $group,
                'authenticated' => $authenticated,
                'userDirectoryCreated' => $folderCreated,
                'welcomeMailSent' => $mailSent,
            ],
            'INFO',
            'success',
        );

        if (!$authenticated) {
            return [
                'type' => 'warning',
                'code' => 'registration_completed_login_required',
                'message' => 'Аккаунт создан, но автоматический вход не выполнен. Войдите с новым логином и паролем.',
                'authenticated' => false,
                'userUuid' => $userUuid,
            ];
        }

        return [
            'type' => 'success',
            'code' => 'registration_completed',
            'message' => $lang['regComplete'] ?? 'Регистрация завершена.',
            'authenticated' => true,
            'userUuid' => $userUuid,
        ];
    }

    private function resolveGroup(string $code): string
    {
        $groups = new GroupRepository($this->db);
        $default = GroupRepository::normalizeTag(
            $this->config['register']['baseUserGroupTag'] ?? 'user',
            'user',
        );
        if (!$groups->exists($default)) {
            $default = $groups->exists('user') ? 'user' : ($groups->exists('guest') ? 'guest' : '');
        }
        if ($default === '') {
            throw new RuntimeException('Registration default group is not configured.');
        }
        if ($code === '') {
            return $default;
        }

        $statement = $this->db->prepare('SELECT `groupTag` FROM `regCodes` WHERE `code` = :code LIMIT 1');
        $statement->execute([':code' => $code]);
        $groupTag = $statement->fetchColumn();
        if (!is_string($groupTag)) {
            throw new AuthFailure(
                'Код регистрации недействителен.',
                'registration_code_invalid',
                422,
                'regCode',
                expected: ['registrationCodeValid' => true],
                actual: ['registrationCodeValid' => false],
            );
        }

        $groupTag = GroupRepository::normalizeTag($groupTag, '');
        if ($groupTag === '' || !$groups->exists($groupTag)) {
            throw new AuthFailure(
                'Код регистрации связан с недоступной группой. Обратитесь к администратору.',
                'registration_code_group_unavailable',
                409,
                'regCode',
                severity: 'critical',
                expected: ['registrationGroupAvailable' => true],
                actual: ['registrationGroupAvailable' => false],
            );
        }
        return $groupTag;
    }

    private function duplicateFailure(string $login, string $email): ?AuthFailure
    {
        $statement = $this->db->prepare(
            'SELECT `login`, `email` FROM `users` WHERE `login` = :login OR `email` = :email LIMIT 1'
        );
        $statement->execute([':login' => $login, ':email' => $email]);
        $existing = $statement->fetch(PDO::FETCH_ASSOC);
        if (!is_array($existing)) {
            return null;
        }

        if (strcasecmp((string)($existing['login'] ?? ''), $login) === 0) {
            return new AuthFailure(
                'Этот логин уже используется.',
                'login_already_used',
                409,
                'login',
                'warn',
                severity: 'notice',
                expected: ['loginAvailable' => true],
                actual: ['loginAvailable' => false],
            );
        }
        return new AuthFailure(
            'Эта электронная почта уже используется.',
            'email_already_used',
            409,
            'email',
            'warn',
            severity: 'notice',
            expected: ['emailAvailable' => true],
            actual: ['emailAvailable' => false],
        );
    }

    /** @param array<string, mixed> $user */
    private function authenticateCreatedAccount(array $user): bool
    {
        try {
            $this->session->authenticate($user);
            RequestTelemetry::annotate([
                'actorUuid' => $this->session->uuid(),
                'actorLogin' => $this->session->login(),
                'actorGroup' => $this->session->group(),
                'authenticated' => true,
            ]);
            return true;
        } catch (Throwable $error) {
            $this->session->clear();
            $this->logger->exception(
                'auth.registration.session_initialization_failed',
                $error,
                'Account was created, but its authenticated session could not be initialized.',
                ['component' => 'authentication'],
            );
            return false;
        }
    }

    private function ensureUserDirectory(string $userUuid): bool
    {
        $folder = ROOT_DIR . UPLOADS_DIR . USR_SUBFOLDER
            . Uuid::canonical($userUuid) . DIRECTORY_SEPARATOR;
        if (is_dir($folder)) {
            return true;
        }
        if (mkdir($folder, 0755, true) || is_dir($folder)) {
            return true;
        }

        $this->logger->deviation(
            'auth.registration.user_directory_failed',
            'user_directory_creation_failed',
            'Registered user directory could not be created.',
            'warning',
            ['directoryCreated' => true],
            ['directoryCreated' => false],
            ['component' => 'authentication', 'targetUserUuid' => $userUuid],
        );
        return false;
    }

    private function sendWelcomeMail(string $email, string $login): bool
    {
        try {
            UtilityLoader::load('FoxMail', '1.0.0');
            (new FoxMail(true))->send(
                $email,
                'Добро пожаловать в FoxesCraft',
                'welcome.html',
                ['username' => $login],
            );
            return true;
        } catch (Throwable $error) {
            $this->logger->exception(
                'auth.registration.welcome_mail_failed',
                $error,
                'Welcome mail delivery failed after registration.',
                [
                    'component' => 'mail',
                    'operation' => 'send_welcome_mail',
                ],
            );
            return false;
        }
    }

    private function isIntegrityViolation(Throwable $error): bool
    {
        return $error instanceof PDOException
            && str_starts_with((string)$error->getCode(), '23');
    }
}
