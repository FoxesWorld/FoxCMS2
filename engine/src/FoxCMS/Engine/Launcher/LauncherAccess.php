<?php

declare(strict_types=1);

namespace FoxCMS\Engine\Launcher;

final class LauncherAccess
{
    public function __construct(
        private \HttpRequest $request,
        private \UserSession $userSession,
        private \LauncherSessionService $launcherSessions,
    ) {
    }

    public function authenticatedUserUuid(): string
    {
        if ($this->userSession->isLogged()) {
            return $this->userSession->uuid();
        }
        return $this->requireAuthenticated()['userUuid'];
    }

    /** @return array{userUuid: string} */
    public function requireAuthenticated(): array
    {
        return $this->launcherSessions->requireAuthenticated($this->token());
    }

    public function token(): string
    {
        $token = strtolower($this->request->string('accessToken'));
        if ($token === '') {
            $authorization = $this->request->header('Authorization');
            if (strncasecmp($authorization, 'Bearer ', 7) === 0) {
                $token = strtolower(trim(substr($authorization, 7)));
            }
        }
        return $token;
    }
}
