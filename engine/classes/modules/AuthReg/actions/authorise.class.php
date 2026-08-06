<?php

declare(strict_types=1);

use FoxCMS\Engine\Auth\RememberCookie;

if (!defined('auth')) {
    http_response_code(403);
    exit('Forbidden');
}

final class Authorise
{
    private const DUMMY_PASSWORD_HASH = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.';

    private AuthInputValidator $validator;
    private RememberCookie $rememberCookie;

    public function __construct(
        private HttpRequest $request,
        private db $db,
        private Logger $logger,
        private UserSession $session,
        private array $config,
    ) {
        $register = is_array($config['register'] ?? null) ? $config['register'] : [];
        $this->rememberCookie = new RememberCookie($request, AuthManager::REMEMBER_COOKIE);
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

        $previousLastDate = max(0, (int)($user['last_date'] ?? 0));
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

        $loginContext = new LoginContextResolver();
        $context = $loginContext->resolve($this->request);
        try {
            $this->session->authenticate($user);
            try {
                $issuedSession = (new UserSessionRegistryService($this->db, $this->config))
                    ->issueForAuthenticatedSession($this->session, $userUuid, $remember, $context);
                if (is_string($issuedSession['token']) && $issuedSession['token'] !== '') {
                    $this->rememberCookie->set($issuedSession['token'], (int)$issuedSession['expiresAt']);
                } else {
                    $this->rememberCookie->clear();
                }
            } catch (Throwable $error) {
                if (!UserSessionRegistryService::isSchemaMissing($error)) {
                    throw $error;
                }
                $this->updateRememberToken($storageUuid, $remember);
            }
        } catch (Throwable $error) {
            $this->session->clear();
            $this->rememberCookie->clear();
            throw $error;
        }

        try {
            $notifications = new NotificationService($this->db);
            $notifications->notifyLogin($userUuid, $context, CURRENT_TIME);
            $absenceSeconds = $previousLastDate > 0 ? max(0, CURRENT_TIME - $previousLastDate) : 0;
            if ($previousLastDate > 0 && $absenceSeconds >= $loginContext->welcomeBackThresholdSeconds()) {
                $notifications->notifyWelcomeBack(
                    $userUuid,
                    (string)($user['login'] ?? $login),
                    $absenceSeconds,
                    CURRENT_TIME,
                );
            }
        } catch (Throwable $error) {
            $this->logger->exception(
                'notifications.login.failed',
                $error,
                'Login completed, but account notifications could not be recorded.',
                [
                    'component' => 'notifications',
                    'operation' => 'login_events',
                    'targetUserUuid' => $userUuid,
                ],
            );
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

        if ($remember) {
            $this->rememberCookie->set($cookieValue, $expiresAt);
        } else {
            $this->rememberCookie->clear();
        }
    }

}
