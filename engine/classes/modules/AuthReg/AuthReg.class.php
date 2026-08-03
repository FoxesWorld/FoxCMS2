<?php

declare(strict_types=1);

if (!defined('FOXXEY')) {
    http_response_code(403);
    exit('Forbidden');
}
if (!defined('auth')) {
    define('auth', true);
}

require_once __DIR__ . '/AuthFailure.class.php';
require_once __DIR__ . '/AuthInputValidator.class.php';

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

        $this->restoreRememberedSessionSafely();
        $action = $this->request->string('userAction');
        if ($action === '') {
            return;
        }

        $handler = match ($action) {
            'auth' => 'authenticate',
            'register' => 'Register::register',
            'lastUser' => 'LastUser::toArray',
            'logout' => 'logout',
            default => 'unresolved',
        };
        RequestTelemetry::identify('auth.' . $action, [
            'component' => 'authentication',
            'action' => $action,
            'handler' => $handler,
            'moduleName' => 'AuthReg',
        ]);
        try {
            $this->dispatch($action);
        } catch (AuthFailure $failure) {
            $this->handleExpectedFailure($failure, $action);
        } catch (Throwable $error) {
            $this->handleUnexpectedFailure($error, $action);
        }
    }

    private function dispatch(string $action): never
    {
        if (!$this->request->isPost()) {
            throw new AuthFailure(
                'Для этой операции требуется POST-запрос.',
                'authentication_method_not_allowed',
                405,
                headers: ['Allow' => 'POST'],
                severity: 'warning',
                expected: ['httpMethod' => 'POST'],
                actual: ['httpMethod' => $this->request->method()],
            );
        }
        if (in_array($action, ['auth', 'register', 'logout'], true)
            && !CsrfToken::validate($this->request->csrfToken())) {
            throw new AuthFailure(
                'Защитный токен устарел. Обновите страницу и повторите действие.',
                'csrf_token_invalid',
                403,
                severity: 'warning',
                expected: ['csrfTokenValid' => true],
                actual: ['csrfTokenValid' => false],
                auditContext: ['component' => 'security'],
            );
        }

        $maintenance = (new MaintenanceModeRepository($this->db))->current();
        if (MaintenanceModePolicy::isEnabled($maintenance)
            && !MaintenanceModePolicy::allows($maintenance, $this->session)
            && !MaintenanceModePolicy::authActionAllowed($action)) {
            $this->logger->deviation(
                'auth.maintenance.rejected',
                'maintenance_mode_active',
                'Authentication operation was blocked by maintenance mode.',
                'notice',
                ['maintenanceMode' => false],
                ['maintenanceMode' => true],
                ['component' => 'authentication', 'action' => $action],
            );
            JsonResponse::send([
                'type' => 'warning',
                'code' => 'maintenance_mode',
                'message' => (string)($maintenance['title'] ?? 'Ведутся технические работы.'),
            ], 503, ['Retry-After' => '300']);
        }

        match ($action) {
            'auth' => $this->authenticate($maintenance),
            'register' => $this->json((new Register(
                $this->request,
                $this->db,
                $this->logger,
                $this->session,
                $this->config,
            ))->register()),
            'lastUser' => $this->json((new LastUser($this->db))->toArray()),
            'logout' => $this->logout(),
            default => throw new AuthFailure(
                'Неизвестная операция авторизации.',
                'authentication_action_unknown',
                400,
                severity: 'warning',
                expected: ['knownAction' => true],
                actual: ['knownAction' => false],
                auditContext: ['action' => mb_substr($action, 0, 64)],
            ),
        };
    }

    private function authenticate(array $maintenance): never
    {
        global $lang;

        if (!$this->session->isLogged()) {
            (new Authorise(
                $this->request,
                $this->db,
                $this->logger,
                $this->session,
                $this->config,
            ))->authenticate();
        }

        $maintenanceAccess = $this->request->boolean('maintenanceAccess')
            || $this->request->boolean('maintenanceAdmin');
        if (MaintenanceModePolicy::isEnabled($maintenance)
            && !MaintenanceModePolicy::allows($maintenance, $this->session)) {
            $rejectedGroup = $this->session->group();
            $this->session->clear();
            $this->clearRememberCookie();
            throw new AuthFailure(
                'Группа этой учётной записи не допущена во время технических работ.',
                'maintenance_group_required',
                403,
                severity: 'warning',
                expected: ['maintenanceGroupAllowed' => true],
                actual: [
                    'maintenanceGroupAllowed' => false,
                    'actorGroup' => $rejectedGroup,
                ],
                auditContext: ['component' => 'authentication'],
            );
        }

        $user = $this->session->all();
        $accessToken = bin2hex(random_bytes(16));
        $launcherSessionCreated = false;
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
            $launcherSessionCreated = true;
        }

        $balance = BalanceMatrix::normalize($user['balance'] ?? null);

        $this->logger->event(
            'auth.response.issued',
            'Authentication response issued.',
            [
                'component' => 'authentication',
                'operation' => 'issue_response',
                'launcherSessionCreated' => $launcherSessionCreated,
                'maintenanceAccess' => $maintenanceAccess,
            ],
            'INFO',
            'success',
        );
        $this->json([
            'type' => 'success',
            'code' => 'authentication_completed',
            'message' => $maintenanceAccess
                ? 'Доступ подтверждён. Открываем сайт…'
                : ($lang['authSuccess'] ?? 'Вход выполнен.'),
            'balance' => $balance,
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

    private function restoreRememberedSessionSafely(): void
    {
        try {
            $this->restoreRememberedSession();
        } catch (Throwable $error) {
            $this->session->clear();
            $this->clearRememberCookie();
            $this->logger->exception(
                'auth.remembered_session.restore_failed',
                $error,
                'Remembered session restoration failed and was discarded.',
                ['component' => 'authentication'],
            );
        }
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
        if ($token === null) {
            return;
        }
        if (!RememberToken::isUsable($token, $ttl)) {
            $this->logger->deviation(
                'auth.remembered_session.rejected',
                'remember_token_invalid',
                'Remembered authentication cookie was malformed or expired.',
                'notice',
                ['rememberTokenUsable' => true],
                ['rememberTokenUsable' => false],
                ['component' => 'authentication'],
            );
            $this->clearRememberCookie();
            return;
        }

        $digest = RememberToken::digest($token);
        $statement = $this->db->prepare('SELECT * FROM `users` WHERE `token` = :token LIMIT 1');
        $statement->execute([':token' => $digest]);
        $user = $statement->fetch(PDO::FETCH_ASSOC);
        if (!is_array($user)) {
            $this->logger->deviation(
                'auth.remembered_session.rejected',
                'remember_token_not_found',
                'Remembered authentication token was not associated with an account.',
                'notice',
                ['rememberTokenMatched' => true],
                ['rememberTokenMatched' => false],
                ['component' => 'authentication'],
            );
            $this->clearRememberCookie();
            return;
        }

        unset($user['password'], $user['token']);
        try {
            $userUuid = Uuid::normalize((string)($user['uuid'] ?? ''));
        } catch (InvalidArgumentException $error) {
            throw new RuntimeException('Remembered account contains an invalid UUID.', 0, $error);
        }
        $user['uuid'] = $userUuid;
        $this->session->authenticate($user);
        $this->rotateRememberToken($userUuid, $ttl);
        RequestTelemetry::annotate([
            'actorUuid' => $this->session->uuid(),
            'actorLogin' => $this->session->login(),
            'actorGroup' => $this->session->group(),
            'authenticated' => true,
        ]);
        $this->logger->event(
            'auth.remembered_session.restored',
            'Remembered user session restored and token rotated.',
            ['component' => 'authentication', 'operation' => 'restore_remembered_session'],
            'INFO',
            'success',
        );
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

    private function logout(): never
    {
        global $lang;

        if (!$this->session->isLogged()) {
            throw new AuthFailure(
                'Пользователь не авторизован.',
                'user_not_authenticated',
                400,
                severity: 'notice',
                expected: ['authenticated' => true],
                actual: ['authenticated' => false],
            );
        }

        $userUuid = $this->session->uuid();
        $this->updateUserTokenByUuid($userUuid, '');
        $this->clearRememberCookie();
        $this->session->clear();
        CsrfToken::rotate();
        RequestTelemetry::annotate([
            'actorUuid' => '',
            'actorLogin' => 'anonymous',
            'actorGroup' => 'guest',
            'authenticated' => false,
        ]);
        $this->logger->event(
            'auth.logout.completed',
            'User logout completed.',
            [
                'component' => 'authentication',
                'operation' => 'logout',
                'targetUserUuid' => $userUuid,
            ],
            'INFO',
            'success',
        );
        $this->json([
            'type' => 'success',
            'code' => 'logout_completed',
            'message' => $lang['loggedOut'] ?? 'Вы вышли из аккаунта.',
        ]);
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
            'UPDATE `users` SET `token` = :token WHERE `uuid` IN (' . implode(', ', $placeholders) . ')'
        );
        $statement->execute($parameters);
        if ($statement->rowCount() > 1) {
            throw new RuntimeException('UUID token update affected more than one account.');
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

    private function handleExpectedFailure(AuthFailure $failure, string $action): never
    {
        $context = array_merge($failure->auditContext(), [
            'component' => 'authentication',
            'action' => $action,
        ]);
        if ($failure->field() !== null) {
            $context['field'] = $failure->field();
        }
        $this->logger->deviation(
            'auth.operation.rejected',
            $failure->publicCode(),
            $failure->getMessage(),
            $failure->severity(),
            $failure->expected(),
            $failure->actual(),
            $context,
        );
        $payload = $failure->payload();
        $requestId = RequestTelemetry::requestId();
        $correlationId = RequestTelemetry::correlationId();
        if ($requestId !== '') {
            $payload['requestId'] = $requestId;
        }
        if ($correlationId !== '') {
            $payload['correlationId'] = $correlationId;
        }
        JsonResponse::send(
            $payload,
            $failure->status(),
            $failure->headers(),
        );
    }

    private function handleUnexpectedFailure(Throwable $error, string $action): never
    {
        if (in_array($action, ['auth', 'register'], true)) {
            $this->session->clear();
            $this->clearRememberCookie();
        }

        $this->logger->exception(
            'auth.operation.failed',
            $error,
            'Authentication operation failed unexpectedly.',
            [
                'component' => 'authentication',
                'action' => $action,
            ],
        );
        $code = $action === 'register'
            ? 'registration_internal_error'
            : 'authentication_internal_error';
        JsonResponse::error(
            $action === 'register'
                ? 'Не удалось завершить регистрацию. Повторите попытку позже.'
                : 'Не удалось завершить авторизацию. Повторите попытку позже.',
            500,
            [],
            ['code' => $code],
        );
    }

    /** @param array<string, mixed> $payload */
    private function json(array $payload, int $status = 200): never
    {
        JsonResponse::send($payload, $status);
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
        if (preg_match('/^[a-f0-9]{32}$/D', $this->accessToken) !== 1) {
            throw new InvalidArgumentException('Invalid launcher access token.');
        }
        $statement = $this->db->prepare(
            'INSERT INTO `usersession` (`userUuid`, `accessToken`, `serverId`, `expiresAt`) '
            . 'VALUES (:userUuid, :accessToken, NULL, :expiresAt) '
            . 'ON DUPLICATE KEY UPDATE '
            . '`accessToken` = VALUES(`accessToken`), `serverId` = NULL, '
            . '`expiresAt` = VALUES(`expiresAt`), `updatedAt` = CURRENT_TIMESTAMP(4)'
        );
        $statement->execute([
            ':userUuid' => $userUuid,
            ':accessToken' => hash('sha256', $this->accessToken),
            ':expiresAt' => CURRENT_TIME + max(300, $this->ttlSeconds),
        ]);
    }
}
