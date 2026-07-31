<?php

declare(strict_types=1);

if (!defined('auth')) {
    http_response_code(403);
    exit('Forbidden');
}

final class Authorise
{
    public function __construct(
        private HttpRequest $request,
        private db $db,
        private Logger $logger,
        private UserSession $session,
        private array $config,
    ) {
    }

    public function authenticate(): bool
    {
        $login = $this->request->string('login');
        $password = $this->request->string('password');
        $remember = $this->request->boolean('rememberMe');

        if (preg_match('/^[A-Za-z0-9_.-]{1,64}$/', $login) !== 1 || $password === '') {
            return false;
        }

        $clientIp = $this->request->clientIp();
        $antiBrute = new AntiBrute($clientIp, $this->db, $this->config, false);
        $statement = $this->db->prepare('SELECT * FROM `users` WHERE `login` = :login LIMIT 1');
        $statement->execute([':login' => $login]);
        $user = $statement->fetch(PDO::FETCH_ASSOC);
        $storedPassword = is_array($user) ? (string)($user['password'] ?? '') : '';

        if (!is_array($user) || !authorize::passVerify($password, $storedPassword)) {
            $this->logger->logError($login . ' failed authorization from ' . $clientIp);
            $antiBrute->failedAuth($clientIp);
            return false;
        }

        $storageUuid = (string)($user['uuid'] ?? '');
        try {
            $userUuid = Uuid::normalize($storageUuid);
        } catch (InvalidArgumentException $exception) {
            $this->logger->logError(
                'Invalid stored user identity for ' . $login . '; run the UUID identity migration.'
            );
            throw new UserIdentityException(
                'The account identity requires database migration.',
                0,
                $exception,
            );
        }
        $user['uuid'] = $userUuid;
        $parameters = [
            ':last_date' => CURRENT_TIME,
            ':logged_ip' => $clientIp,
            ':uuid' => $storageUuid,
        ];
        $setParts = [
            '`last_date` = :last_date',
            '`logged_ip` = :logged_ip',
        ];

        if (authorize::needsRehash($storedPassword)) {
            $setParts[] = '`password` = :password';
            $parameters[':password'] = authorize::hashPassword($password);
        }

        $update = $this->db->prepare(
            'UPDATE `users` SET ' . implode(', ', $setParts) . ' WHERE `uuid` = :uuid'
        );
        $update->execute($parameters);

        $user['last_date'] = CURRENT_TIME;
        $user['logged_ip'] = $clientIp;
        unset($user['password'], $user['token']);

        $this->session->authenticate($user);
        $this->updateRememberToken($storageUuid, $remember);
        $antiBrute->clearIp($clientIp);
        $this->logger->logInfo($login . ' successfully authorized from ' . $clientIp);
        return true;
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

        setcookie(AuthManager::REMEMBER_COOKIE, $cookieValue, [
            'expires' => $expiresAt,
            'path' => '/',
            'secure' => $this->request->isSecure(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
}
