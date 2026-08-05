<?php

declare(strict_types=1);

namespace FoxCMS\Api\Game;

final class PlayerIdentityResolver
{
    public function __construct(private readonly \db $database)
    {
    }

    public function resolve(?string $uuid, ?string $login): string
    {
        $uuid = trim((string)$uuid);
        $login = trim((string)$login);
        if ($uuid === '' && $login === '') {
            throw new \GameApiException('player_identity_required', 'Параметр uuid или login обязателен.', 400);
        }
        if ($uuid !== '' && !\Uuid::isValid($uuid)) {
            throw new \GameApiException('player_identity_invalid', 'Некорректный UUID игрока.', 400);
        }
        if ($uuid !== '') {
            return \Uuid::normalize($uuid);
        }

        if (preg_match('/^[A-Za-z0-9_.-]{1,64}$/D', $login) !== 1) {
            throw new \GameApiException('player_identity_invalid', 'Некорректный логин игрока.', 400);
        }
        $statement = $this->database->prepare('SELECT `uuid` FROM `users` WHERE `login` = :login LIMIT 1');
        $statement->execute([':login' => $login]);
        $resolved = trim((string)($statement->fetchColumn() ?: ''));
        if (!\Uuid::isValid($resolved)) {
            throw new \GameApiException('player_not_found', 'Игрок не найден.', 404);
        }
        return \Uuid::normalize($resolved);
    }
}
