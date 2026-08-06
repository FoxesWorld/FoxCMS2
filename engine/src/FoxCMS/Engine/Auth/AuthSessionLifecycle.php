<?php

declare(strict_types=1);

namespace FoxCMS\Engine\Auth;

use \AuthFailure;
use \CsrfToken;
use \HttpRequest;
use \InvalidArgumentException;
use \Logger;
use \LoginContextResolver;
use \PDO;
use \RememberToken;
use \RequestTelemetry;
use \RuntimeException;
use \Throwable;
use \UserSession;
use \UserSessionRegistryService;
use \Uuid;
use \db;

/** Owns remembered authentication, browser-session cleanup and logout. */
final class AuthSessionLifecycle
{
    private RememberCookie $rememberCookie;

    public function __construct(
        private readonly db $db,
        private readonly Logger $logger,
        private readonly HttpRequest $request,
        private readonly UserSession $session,
        private readonly array $config,
        string $rememberCookieName,
    ) {
        $this->rememberCookie = new RememberCookie($request, $rememberCookieName);
    }

    public function restoreSafely(): void
    {
        try {
            $this->restoreRememberedSession();
        } catch (Throwable $error) {
            $this->invalidateCurrent();
            $this->logger->exception(
                'auth.remembered_session.restore_failed',
                $error,
                'Remembered session restoration failed and was discarded.',
                ['component' => 'authentication'],
            );
        }
    }

    public function invalidateCurrent(): void
    {
        $this->revokeCurrentBrowserSessionSafely();
        $this->session->clear();
        $this->rememberCookie->clear();
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
        $token = $this->rememberCookie->value();
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
            $this->rememberCookie->clear();
            return;
        }

        $context = (new LoginContextResolver())->resolve($this->request);
        $registry = new UserSessionRegistryService($this->db, $this->config);
        try {
            $restored = $registry->restoreRememberedSession($token, $this->session, $context);
            if ($restored !== null) {
                $this->rememberCookie->set((string)$restored['token'], (int)$restored['expiresAt']);
                $this->completeRememberedRestore('registry');
                return;
            }
        } catch (Throwable $error) {
            if (!UserSessionRegistryService::isSchemaMissing($error)) {
                throw $error;
            }
        }

        // Migration bridge: accept the pre-024 token once, then move it into
        // the per-device registry without invalidating other registry sessions.
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
            $this->rememberCookie->clear();
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
        try {
            $issued = $registry->issueForAuthenticatedSession(
                $this->session,
                $userUuid,
                true,
                $context,
            );
            $this->updateUserTokenByUuid($userUuid, '');
            $this->rememberCookie->set((string)$issued['token'], (int)$issued['expiresAt']);
            $this->completeRememberedRestore('legacy_migrated');
        } catch (Throwable $error) {
            if (!UserSessionRegistryService::isSchemaMissing($error)) {
                throw $error;
            }
            $this->rotateRememberToken($userUuid, $ttl);
            $this->completeRememberedRestore('legacy');
        }
    }

    private function completeRememberedRestore(string $source): void
    {
        RequestTelemetry::annotate([
            'actorUuid' => $this->session->uuid(),
            'actorLogin' => $this->session->login(),
            'actorGroup' => $this->session->group(),
            'authenticated' => true,
        ]);
        $this->logger->event(
            'auth.remembered_session.restored',
            'Remembered user session restored and token rotated.',
            [
                'component' => 'authentication',
                'operation' => 'restore_remembered_session',
                'source' => $source,
            ],
            'INFO',
            'success',
        );
    }

    private function rotateRememberToken(string $userUuid, int $ttl): void
    {
        $issued = RememberToken::issue($ttl);
        $this->updateUserTokenByUuid($userUuid, $issued['digest']);
        $this->rememberCookie->set($issued['token'], $issued['expiresAt']);
    }

    public function logout(): array
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
        try {
            (new UserSessionRegistryService($this->db, $this->config))
                ->revokeCurrentSession($this->session);
        } catch (Throwable $error) {
            if (!UserSessionRegistryService::isSchemaMissing($error)) {
                throw $error;
            }
            $this->updateUserTokenByUuid($userUuid, '');
        }
        $this->rememberCookie->clear();
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
        return [
            'type' => 'success',
            'code' => 'logout_completed',
            'message' => $lang['loggedOut'] ?? 'Вы вышли из аккаунта.',
        ];
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

    private function revokeCurrentBrowserSessionSafely(): void
    {
        if (!$this->session->isLogged() || $this->session->browserSessionUuid() === '') {
            return;
        }
        try {
            (new UserSessionRegistryService($this->db, $this->config))
                ->revokeCurrentSession($this->session);
        } catch (Throwable $error) {
            if (!UserSessionRegistryService::isSchemaMissing($error)) {
                $this->logger->exception(
                    'session.browser_registry.revoke_failed',
                    $error,
                    'Browser session registry entry could not be revoked during authentication cleanup.',
                    ['component' => 'session', 'operation' => 'registry_revoke'],
                );
            }
        }
    }

    /** @return array{expires:int,path:string,secure:bool,httponly:bool,samesite:string} */
}
