<?php

declare(strict_types=1);

if (!defined('auth')) {
    http_response_code(403);
    exit('Forbidden');
}

final class Authorise
{
    private const DUMMY_PASSWORD_HASH = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.';

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
            (int)($register['maxLoginLength'] ?? 64),
            (int)($register['passminCount'] ?? 10),
        );
    }

    public function authenticate(): void
    {
        $credentials = $this->validator->authenticationCredentials(
            $this->request->string('login'),
            $this->request->string('password'),
        );
        $login = $credentials['login'];
        $password = $credentials['password'];
        $remember = $this->request->boolean('rememberMe')
            && !$this->request->boolean('maintenanceAccess')
            && !$this->request->boolean('maintenanceAdmin');

        $clientIp = $this->request->clientIp();
        $antiBrute = new AntiBrute($clientIp, $this->db, $this->config, $this->logger, false);
        $antiBrute->assertAllowed();

        $statement = $this->db->prepare('SELECT * FROM `users` WHERE `login` = :login LIMIT 1');
        $statement->execute([':login' => $login]);
        $user = $statement->fetch(PDO::FETCH_ASSOC);
        $storedPassword = is_array($user)
            ? (string)($user['password'] ?? self::DUMMY_PASSWORD_HASH)
            : self::DUMMY_PASSWORD_HASH;
        $passwordValid = authorize::passVerify($password, $storedPassword);

        if (!is_array($user) || !$passwordValid) {
            $antiBrute->failedAuth($clientIp);
            throw new AuthFailure(
                'Неверный логин или пароль.',
                'invalid_credentials',
                401,
                severity: 'warning',
                expected: ['credentialsValid' => true],
                actual: [
                    'credentialsValid' => false,
                    'accountFound' => is_array($user),
                    'loginLength' => mb_strlen($login, 'UTF-8'),
                ],
                auditContext: ['component' => 'authentication'],
            );
        }

        $storageUuid = (string)($user['uuid'] ?? '');
        try {
            $userUuid = Uuid::normalize($storageUuid);
        } catch (InvalidArgumentException $error) {
            throw new AuthFailure(
                'Идентификатор учётной записи требует обновления базы данных.',
                'identity_migration_required',
                409,
                severity: 'critical',
                expected: ['storedUuidValid' => true],
                actual: ['storedUuidValid' => false],
                auditContext: ['component' => 'authentication'],
                previous: $error,
            );
        }

        $parameters = [
            ':lastDate' => CURRENT_TIME,
            ':loggedIp' => $clientIp,
            ':uuid' => $storageUuid,
        ];
        $setParts = [
            '`last_date` = :lastDate',
            '`logged_ip` = :loggedIp',
        ];
        $passwordRehashed = authorize::needsRehash($storedPassword);
        if ($passwordRehashed) {
            $setParts[] = '`password` = :password';
            $parameters[':password'] = authorize::hashPassword($password);
        }

        $update = $this->db->prepare(
            'UPDATE `users` SET ' . implode(', ', $setParts) . ' WHERE `uuid` = :uuid'
        );
        $update->execute($parameters);
        if ($update->rowCount() > 1) {
            throw new RuntimeException('Authentication update affected more than one account.');
        }

        $user['uuid'] = $userUuid;
        $user['last_date'] = CURRENT_TIME;
        $user['logged_ip'] = $clientIp;
        unset($user['password'], $user['token']);

        try {
            $this->updateRememberToken($storageUuid, $remember);
            $this->session->authenticate($user);
        } catch (Throwable $error) {
            $this->session->clear();
            $this->clearRememberCookie();
            throw $error;
        }

        RequestTelemetry::annotate([
            'actorUuid' => $this->session->uuid(),
            'actorLogin' => $this->session->login(),
            'actorGroup' => $this->session->group(),
            'authenticated' => true,
        ]);
        $antiBrute->clearIp($clientIp);
        $this->logger->event(
            'auth.login.completed',
            'User authentication completed.',
            [
                'component' => 'authentication',
                'operation' => 'login',
                'remembered' => $remember,
                'passwordRehashed' => $passwordRehashed,
            ],
            'INFO',
            'success',
        );
    }

    private function updateRememberToken(string $storageUuid, bool $remember): void
    {
        $security = is_array($this->config['securitySetings'] ?? null)
            ? $this->config['securitySetings']
            : [];
        $ttl = max(3600, (int)($security['rememberSeconds'] ?? 31536000));
        $digest = '';
        $cookieValue = '';
        $expiresAt = time() - 3600;

        if ($remember) {
            $issued = RememberToken::issue($ttl);
            $digest = $issued['digest'];
            $cookieValue = $issued['token'];
            $expiresAt = $issued['expiresAt'];
        }

        $statement = $this->db->prepare('UPDATE `users` SET `token` = :token WHERE `uuid` = :uuid');
        $statement->execute([':token' => $digest, ':uuid' => $storageUuid]);
        if ($statement->rowCount() > 1) {
            throw new RuntimeException('Remember-token update affected more than one account.');
        }

        setcookie(AuthManager::REMEMBER_COOKIE, $cookieValue, [
            'expires' => $expiresAt,
            'path' => '/',
            'secure' => $this->request->isSecure(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    private function clearRememberCookie(): void
    {
        setcookie(AuthManager::REMEMBER_COOKIE, '', [
            'expires' => time() - 3600,
            'path' => '/',
            'secure' => $this->request->isSecure(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
}
