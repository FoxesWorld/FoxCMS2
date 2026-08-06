<?php

declare(strict_types=1);

namespace FoxCMS\Engine\User;

use \CsrfToken;
use \LogicException;
use \Throwable;
use \UserSessionRegistryService;
use \Uuid;

final class UserBrowserSessionController
{
    public function __construct(
        private readonly \db $db,
        private readonly \Logger $logger,
        private readonly \HttpRequest $request,
        private readonly \UserSession $session,
        private readonly array $config,
        private readonly AuthenticatedUserGuard $guard,
        private readonly UserActionResponder $responder,
    ) {
    }

    public function getActiveSessions(): never
    {
        $userUuid = $this->guard->uuid();
        try {
            $result = (new UserSessionRegistryService($this->db, $this->config))->activeSessions(
                $userUuid,
                $this->session->browserSessionUuid(),
            );
            $this->responder->send($result);
        } catch (Throwable $error) {
            if (UserSessionRegistryService::isSchemaMissing($error)) {
                $this->responder->send([
                    'message' => 'Реестр устройств не инициализирован. Примените миграцию 024_user_browser_sessions.sql.',
                    'type' => 'error',
                    'migration' => '024_user_browser_sessions.sql',
                ], 503);
            }
            $this->logger->exception(
                'sessions.list.failed',
                $error,
                'Active browser sessions could not be loaded.',
                ['component' => 'sessions', 'operation' => 'list', 'targetUserUuid' => $userUuid],
            );
            $this->responder->fatal($error, 500, ['operation' => 'list_sessions']);
        }
    }

    public function revokeActiveSession(): never
    {
        $userUuid = $this->guard->uuid();
        CsrfToken::requireValid($this->request->csrfToken());
        $sessionUuid = trim($this->request->string('sessionUuid'));
        if (!Uuid::isValid($sessionUuid)) {
            $this->responder->send(['message' => 'Некорректный идентификатор сессии.', 'type' => 'error'], 400);
        }
        $sessionUuid = Uuid::normalize($sessionUuid);
        $currentSessionUuid = $this->session->browserSessionUuid();
        if ($currentSessionUuid !== '' && Uuid::equals($sessionUuid, $currentSessionUuid)) {
            $this->responder->send([
                'message' => 'Текущую сессию нельзя деактивировать с этой страницы.',
                'type' => 'error',
            ], 409);
        }

        try {
            $service = new UserSessionRegistryService($this->db, $this->config);
            if (!$service->revokeSession($userUuid, $sessionUuid, $currentSessionUuid)) {
                $this->responder->send([
                    'message' => 'Сессия не найдена или уже была деактивирована.',
                    'type' => 'error',
                ], 404);
            }
            $active = $service->activeSessions($userUuid, $currentSessionUuid);
            $this->logger->event(
                'sessions.revoke.completed',
                'A remote browser session was revoked by its owner.',
                [
                    'component' => 'sessions',
                    'operation' => 'revoke',
                    'targetUserUuid' => $userUuid,
                    'targetSessionUuid' => $sessionUuid,
                ],
                'NOTICE',
                'success',
            );
            $this->responder->send([
                'message' => 'Сессия деактивирована. На выбранном устройстве доступ будет завершён.',
                'type' => 'success',
                'revoked' => true,
                'sessionUuid' => $sessionUuid,
                'activeCount' => (int)$active['activeCount'],
            ]);
        } catch (LogicException $error) {
            $this->responder->send(['message' => $error->getMessage(), 'type' => 'error'], 409);
        } catch (Throwable $error) {
            if (UserSessionRegistryService::isSchemaMissing($error)) {
                $this->responder->send([
                    'message' => 'Реестр устройств не инициализирован. Примените миграцию 024_user_browser_sessions.sql.',
                    'type' => 'error',
                    'migration' => '024_user_browser_sessions.sql',
                ], 503);
            }
            $this->logger->exception(
                'sessions.revoke.failed',
                $error,
                'Remote browser session could not be revoked.',
                [
                    'component' => 'sessions',
                    'operation' => 'revoke',
                    'targetUserUuid' => $userUuid,
                    'targetSessionUuid' => $sessionUuid,
                ],
            );
            $this->responder->fatal($error, 500, ['operation' => 'revoke_session']);
        }
    }
}
