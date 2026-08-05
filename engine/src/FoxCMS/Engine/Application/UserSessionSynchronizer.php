<?php

declare(strict_types=1);

namespace FoxCMS\Engine\Application;

final class UserSessionSynchronizer
{
    public function __construct(
        private \db $db,
        private \Logger $logger,
        private \HttpRequest $request,
        private \UserSession $session,
        private array $config,
    ) {
    }

    public function synchronize(): void
    {
        $startedAt = hrtime(true);
        $wasAuthenticated = $this->session->isLogged();
        $previousActorUuid = $this->session->uuid();
        $previousActorLogin = $this->session->login();

        try {
            $this->session->synchronizeWithDatabase();
            $this->touchCurrentUser();
            $this->synchronizeBrowserSession();

            $authenticated = $this->session->isLogged();
            $duration = $this->durationMs($startedAt);
            $sessionState = !$wasAuthenticated
                ? 'guest_confirmed'
                : ($authenticated ? 'database_refreshed' : 'invalidated');

            \RequestTelemetry::annotate([
                'actorUuid' => $this->session->uuid(),
                'actorLogin' => $this->session->login(),
                'actorGroup' => $this->session->group(),
                'authenticated' => $authenticated,
                'sessionState' => $sessionState,
            ]);

            if ($wasAuthenticated && !$authenticated) {
                \RequestTelemetry::deviation(
                    'session.synchronize.invalidated',
                    'session_identity_not_found',
                    'Authenticated session was invalidated because its user identity could not be refreshed.',
                    'warning',
                    ['sessionState' => 'database_refreshed'],
                    ['sessionState' => 'invalidated'],
                    [
                        'component' => 'session',
                        'previousActorUuid' => $previousActorUuid,
                        'previousActorLogin' => $previousActorLogin,
                        'durationMs' => $duration,
                    ],
                );
            } elseif ($authenticated) {
                \RequestTelemetry::event(
                    'session.synchronize.completed',
                    sprintf(
                        'Session refreshed from the users table for %s [%s] in %.3f ms.',
                        $this->session->login(),
                        $this->session->group(),
                        $duration,
                    ),
                    [
                        'component' => 'session',
                        'operation' => 'session.synchronize',
                        'sessionState' => $sessionState,
                        'durationMs' => $duration,
                        'authenticated' => true,
                    ],
                    'DEBUG',
                    'success',
                );
            } elseif ($duration > 250) {
                \RequestTelemetry::deviation(
                    'session.synchronize.slow_guest',
                    'guest_session_synchronization_slow',
                    'Guest session initialization exceeded the expected duration.',
                    'notice',
                    ['maximumDurationMs' => 250],
                    ['durationMs' => $duration],
                    ['component' => 'session', 'sessionState' => $sessionState],
                );
            }
        } catch (\Throwable $error) {
            \RequestTelemetry::failure(
                'session.synchronize.failed',
                $error,
                'Session synchronization failed before request dispatch.',
                [
                    'component' => 'session',
                    'previousActorUuid' => $previousActorUuid,
                    'previousActorLogin' => $previousActorLogin,
                    'durationMs' => $this->durationMs($startedAt),
                ],
            );
            throw $error;
        }
    }

    private function synchronizeBrowserSession(): void
    {
        if (!$this->session->isLogged()) {
            return;
        }

        try {
            $valid = (new \UserSessionRegistryService($this->db, $this->config))
                ->synchronizeCurrentSession(
                    $this->session,
                    (new \LoginContextResolver())->resolve($this->request),
                );
            if (!$valid && $this->session->browserSessionUuid() !== '') {
                $this->logger->event(
                    'session.browser_registry.expired',
                    'Browser session registry entry expired or was revoked.',
                    ['component' => 'session', 'operation' => 'registry_sync'],
                    'NOTICE',
                    'expired',
                );
                $this->session->clear();
            }
        } catch (\Throwable $error) {
            if (\UserSessionRegistryService::isSchemaMissing($error)) {
                return;
            }
            $this->logger->exception(
                'session.browser_registry.sync_failed',
                $error,
                'Browser session registry could not be synchronized.',
                ['component' => 'session', 'operation' => 'registry_sync'],
            );
        }
    }

    private function touchCurrentUser(): void
    {
        if (!$this->session->isLogged()) {
            return;
        }

        $placeholders = [];
        $parameters = [':last_date' => CURRENT_TIME];
        foreach (\Uuid::databaseCandidates($this->session->uuid()) as $index => $candidate) {
            $placeholder = ':uuid_' . $index;
            $placeholders[] = $placeholder;
            $parameters[$placeholder] = $candidate;
        }
        $statement = $this->db->prepare(
            'UPDATE `users` SET `last_date` = :last_date '
            . 'WHERE `uuid` IN (' . implode(', ', $placeholders) . ')'
        );
        $statement->execute($parameters);
    }

    private function durationMs(int $startedAt): float
    {
        return round(max(0, hrtime(true) - $startedAt) / 1_000_000, 3);
    }
}
