<?php

declare(strict_types=1);

namespace FoxCMS\Engine\User;

final class AuthenticatedUserGuard
{
    public function __construct(
        private readonly \UserSession $session,
        private readonly UserActionResponder $responder,
    ) {
    }

    public function uuid(): string
    {
        if (!$this->session->isLogged()) {
            $this->responder->send(['message' => 'Требуется вход в систему.', 'type' => 'error'], 401);
        }
        $userUuid = $this->session->uuid();
        if (!\Uuid::isValid($userUuid)) {
            $this->responder->send(['message' => 'Некорректный UUID пользователя.', 'type' => 'error'], 409);
        }
        return \Uuid::normalize($userUuid);
    }

    public function requireRewardAccess(): string
    {
        $uuid = $this->uuid();
        if ($this->session->group() === 'guest') {
            $this->responder->send(['message' => 'Гостевой профиль не может получать награды.', 'type' => 'error'], 403);
        }
        return $uuid;
    }
}
