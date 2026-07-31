<?php

declare(strict_types=1);

final class LauncherSessionService
{
    public function __construct(private db $database)
    {
    }

    /** @return array{userUuid:string,profileId:string,login:string} */
    public function resolve(string $accessToken): array
    {
        if (preg_match('/^[a-f0-9]{32,128}$/D', $accessToken) !== 1) {
            throw new RuntimeException('Invalid launcher token.', 401);
        }

        $statement = $this->database->prepare(
            'SELECT `session`.`userUuid`, `user`.`login` '
            . 'FROM `usersession` AS `session` '
            . 'INNER JOIN `users` AS `user` ON `user`.`uuid` = `session`.`userUuid` '
            . 'WHERE `session`.`accessToken` = :digest '
            . 'AND `session`.`expiresAt` >= :currentTime LIMIT 1'
        );
        $statement->execute([
            ':digest' => hash('sha256', $accessToken),
            ':currentTime' => CURRENT_TIME,
        ]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            throw new RuntimeException('Launcher session is invalid or expired.', 401);
        }

        $userUuid = Uuid::normalize((string)$row['userUuid']);
        return [
            'userUuid' => $userUuid,
            'profileId' => Uuid::compact($userUuid),
            'login' => (string)$row['login'],
        ];
    }

    /** @return array{userUuid:string,profileId:string,login:string}|null */
    public function authenticate(string $accessToken): ?array
    {
        try {
            return $this->resolve($accessToken);
        } catch (RuntimeException) {
            return null;
        }
    }

    /** @return array{userUuid:string,profileId:string,login:string} */
    public function requireAuthenticated(string $accessToken): array
    {
        return $this->resolve($accessToken);
    }
}
