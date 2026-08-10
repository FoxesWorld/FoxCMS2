<?php

declare(strict_types=1);

use FoxCMS\Engine\Auth\AuthSessionLifecycle;

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

    private AuthSessionLifecycle $sessionLifecycle;

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

        $this->sessionLifecycle = new AuthSessionLifecycle(
            $db,
            $logger,
            $request,
            $session,
            $config,
            self::REMEMBER_COOKIE,
        );
        $this->sessionLifecycle->restoreSafely();
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

        if ($action === 'auth') {
            $this->assertHCaptcha('login');
        } elseif ($action === 'register') {
            $this->assertHCaptcha('registration');
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
            'logout' => $this->json($this->sessionLifecycle->logout()),
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

    private function assertHCaptcha(string $scope): void
    {
        if (!HCaptchaPolicy::required($this->config, $scope)) {
            return;
        }
        $result = HCaptchaPolicy::verify($this->config, $this->request);
        if (!$result['configured']) {
            throw new AuthFailure(
                'hCaptcha включена, но ключи сервиса не настроены.',
                'hcaptcha_not_configured',
                503,
                severity: 'critical',
            );
        }
        if ($result['transportError']) {
            throw new AuthFailure(
                'Сервис hCaptcha временно недоступен. Повторите попытку позже.',
                'hcaptcha_unavailable',
                503,
                severity: 'warning',
            );
        }
        if (!$result['success']) {
            throw new AuthFailure(
                'Подтвердите, что вы не робот, и повторите отправку формы.',
                'hcaptcha_verification_failed',
                422,
                'hcaptchaToken',
                severity: 'notice',
                expected: ['hcaptchaVerified' => true],
                actual: ['hcaptchaVerified' => false, 'errorCodes' => $result['errorCodes']],
                auditContext: ['component' => 'security', 'scope' => $scope],
            );
        }
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
            $this->sessionLifecycle->invalidateCurrent();
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
            $this->sessionLifecycle->invalidateCurrent();
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
        $requestId = RequestTelemetry::requestId();
        if ($requestId === '') {
            $requestId = ExceptionContext::requestId('authentication');
        }
        JsonResponse::send(
            \FoxCMS\Shared\Error\ThrowableDiagnostic::payload(
                $error,
                $requestId,
                ROOT_DIR,
                false,
                ['type' => 'error', 'code' => $code, 'action' => $action],
            ),
            500,
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
