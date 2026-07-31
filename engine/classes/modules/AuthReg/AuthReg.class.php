<?php

declare(strict_types=1);

if (!defined('FOXXEY')) {
    http_response_code(403);
    exit('Forbidden');
}
if (!defined('auth')) {
    define('auth', true);
}

final class AuthManager extends Module
{
    public const REMEMBER_COOKIE = 'userToken';

    public function __construct(
        private db $db,
        private Logger $logger,
        private HttpRequest $request,
        private UserSession $session,
        private array $config = [],
    ) {
        require_once __DIR__ . '/actions/authorise.class.php';
        require_once __DIR__ . '/actions/register.class.php';
        require_once __DIR__ . '/actions/lastUser.class.php';

        $this->restoreRememberedSession();
        $this->dispatch();
    }

    private function dispatch(): void
    {
        $action = $this->request->string('userAction');
        $maintenance = (new MaintenanceModeRepository($this->db))->current();
        if (MaintenanceModePolicy::isEnabled($maintenance)
            && !MaintenanceModePolicy::allows($maintenance, $this->session)
            && !MaintenanceModePolicy::authActionAllowed($action)) {
            $this->json([
                'type' => 'warning',
                'code' => 'maintenance_mode',
                'message' => (string)($maintenance['title'] ?? 'Ведутся технические работы.'),
            ], 503);
        }
        if ($action === '') {
            return;
        }

        match ($action) {
            'auth' => $this->authenticate(),
            'register' => (new Register(
                $this->request,
                $this->db,
                $this->logger,
                $this->session,
                $this->config,
            ))->register(),
            'lastUser' => $this->json((new LastUser($this->db))->toArray()),
            'logout' => $this->logoutAfterCsrfCheck(),
            default => $this->json([
                'type' => 'error',
                'message' => 'Unknown authentication action.',
            ], 400),
        };
    }

    private function authenticate(): never
    {
        global $lang;

        if (!$this->session->isLogged()) {
            $authorise = new Authorise(
                $this->request,
                $this->db,
                $this->logger,
                $this->session,
                $this->config,
            );
            try {
                $authenticated = $authorise->authenticate();
            } catch (UserIdentityException $exception) {
                $this->logger->logError(
                    'Authentication blocked by user identity integrity: ' . $exception->getMessage()
                );
                $this->json([
                    'type' => 'error',
                    'code' => 'identity_migration_required',
                    'message' => 'Идентификатор учётной записи требует обновления базы данных.',
                ], 409);
            }
            if (!$authenticated) {
                $this->json([
                    'type' => 'error',
                    'message' => $lang['authWrong'] ?? 'Неверный логин или пароль.',
                ], 401);
            }
        }

        $maintenanceAdmin = $this->request->boolean('maintenanceAdmin');
        if ($maintenanceAdmin && !$this->session->isAdmin()) {
            $rejectedLogin = $this->session->login();
            $this->logger->logError(
                'Maintenance administrator access rejected for ' . $rejectedLogin
                . ' from ' . $this->request->clientIp()
            );
            $this->session->clear();
            $this->clearRememberCookie();
            $this->json([
                'type' => 'error',
                'code' => 'administrator_required',
                'message' => 'Эта форма предназначена только для администраторов.',
            ], 403);
        }

        $user = $this->session->all();
        $accessToken = bin2hex(random_bytes(16));
        if ($this->request->userAgent() === 'FoxesWorldLauncher') {
            $launcherConfig = is_array($this->config['launcherSettings'] ?? null)
                ? $this->config['launcherSettings']
                : [];
            (new AuthLibSession(
                $this->db,
                $user,
                $accessToken,
                max(300, (int)($launcherConfig['sessionSeconds'] ?? 900)),
            ))->persist();
        }

        $this->json([
            'type' => 'success',
            'message' => $maintenanceAdmin
                ? 'Администратор авторизован. Открываем сайт…'
                : ($lang['authSuccess'] ?? 'Вход выполнен.'),
            'balance' => json_decode((string)($user['balance'] ?? '[]'), true) ?? [],
            'login' => (string)($user['login'] ?? ''),
            'token' => $accessToken,
            'groupTag' => $this->session->group(),
            'groupName' => (string)$this->session->get('groupName', ''),
            'userUuid' => $this->session->uuid(),
            'uuid' => $this->session->compactUuid(),
            'colorScheme' => (string)($user['colorScheme'] ?? ''),
            'userFullName' => (string)($user['realname'] ?? ''),
        ]);
    }

    private function restoreRememberedSession(): void
    {
        if ($this->session->isLogged()) {
            return;
        }

        $security = is_array($this->config['securitySetings'] ?? null)
            ? $this->config['securitySetings']
            : [];
        $ttl = max(3600, (int)($security['rememberSeconds'] ?? 31536000));
        $token = $this->request->cookie(self::REMEMBER_COOKIE);
        if ($token === null || !RememberToken::isUsable($token, $ttl)) {
            $this->clearRememberCookie();
            return;
        }

        $digest = RememberToken::digest($token);
        $statement = $this->db->prepare('SELECT * FROM `users` WHERE `token` = :token LIMIT 1');
        $statement->execute([':token' => $digest]);
        $user = $statement->fetch(PDO::FETCH_ASSOC);
        if (!is_array($user)) {
            $this->clearRememberCookie();
            return;
        }

        unset($user['password'], $user['token']);
        try {
            $userUuid = Uuid::normalize((string)($user['uuid'] ?? ''));
            $user['uuid'] = $userUuid;
            $this->session->authenticate($user);
            $this->rotateRememberToken($userUuid, $ttl);
        } catch (InvalidArgumentException) {
            $this->logger->logError('Remembered session rejected because the stored user identity is invalid.');
            $this->clearRememberCookie();
        }
    }

    private function rotateRememberToken(string $userUuid, int $ttl): void
    {
        $issued = RememberToken::issue($ttl);
        $this->updateUserTokenByUuid($userUuid, $issued['digest']);

        setcookie(self::REMEMBER_COOKIE, $issued['token'], [
            'expires' => $issued['expiresAt'],
            'path' => '/',
            'secure' => $this->request->isSecure(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    private function logoutAfterCsrfCheck(): never
    {
        CsrfToken::requireValid($this->request->csrfToken());
        $this->logout();
    }

    private function logout(): never
    {
        global $lang;

        if (!$this->session->isLogged()) {
            $this->json(['type' => 'error', 'message' => 'Пользователь не авторизован.'], 400);
        }

        $this->updateUserTokenByUuid($this->session->uuid(), '');

        $this->clearRememberCookie();
        $this->session->clear();
        CsrfToken::rotate();
        $this->json(['type' => 'success', 'message' => $lang['loggedOut'] ?? 'Вы вышли из аккаунта.']);
    }

    private function updateUserTokenByUuid(string $userUuid, string $token): void
    {
        $placeholders = [];
        $parameters = [':token' => $token];
        foreach (Uuid::databaseCandidates($userUuid) as $index => $candidate) {
            $placeholder = ':userUuid_' . $index;
            $placeholders[] = $placeholder;
            $parameters[$placeholder] = $candidate;
        }
        $statement = $this->db->prepare(
            'UPDATE `users` SET `token` = :token '
            . 'WHERE `uuid` IN (' . implode(', ', $placeholders) . ')'
        );
        $statement->execute($parameters);
        if ($statement->rowCount() > 1) {
            throw new RuntimeException('UUID token update affected more than one user.');
        }
    }

    private function clearRememberCookie(): void
    {
        setcookie(self::REMEMBER_COOKIE, '', [
            'expires' => time() - 3600,
            'path' => '/',
            'secure' => $this->request->isSecure(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    private function json(array $payload, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=UTF-8');
        header('Cache-Control: no-store');
        $encoded = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($encoded)) {
            throw new RuntimeException('Unable to encode authentication response.');
        }
        exit($encoded);
    }
}

final class AuthLibSession
{
    public function __construct(
        private db $db,
        private array $user,
        private string $accessToken,
        private int $ttlSeconds,
    ) {
    }

    public function persist(): void
    {
        $userUuid = Uuid::normalize((string)($this->user['uuid'] ?? ''));
        $statement = $this->db->prepare(
            'INSERT INTO `usersession` (`userUuid`, `accessToken`, `serverId`, `expiresAt`) '
            . 'VALUES (:userUuid, :accessToken, NULL, :expiresAt) '
            . 'ON DUPLICATE KEY UPDATE '
            . '`accessToken` = VALUES(`accessToken`), '
            . '`serverId` = NULL, '
            . '`expiresAt` = VALUES(`expiresAt`), '
            . '`updatedAt` = CURRENT_TIMESTAMP(4)'
        );
        $statement->execute([
            ':userUuid' => $userUuid,
            ':accessToken' => hash('sha256', $this->accessToken),
            ':expiresAt' => CURRENT_TIME + max(300, $this->ttlSeconds),
        ]);
    }
}
