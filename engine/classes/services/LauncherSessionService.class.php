<?php

declare(strict_types=1);

final class LauncherSessionService
{
    public function __construct(
        private db $database,
        private ?Logger $logger = null,
    ) {
    }

    /** @return array{userUuid:string,profileId:string,login:string} */
    public function resolve(string $accessToken): array
    {
        $accessToken = strtolower(trim($accessToken));
        if (preg_match('/^[a-f0-9]{32,128}$/D', $accessToken) !== 1) {
            $this->logger?->deviation(
                'launcher.session.rejected',
                'launcher_token_format_invalid',
                'Launcher session token format is invalid.',
                'warning',
                ['tokenFormatValid' => true],
                [
                    'tokenFormatValid' => false,
                    'tokenLength' => strlen($accessToken),
                ],
                ['component' => 'launcher_session'],
            );
            throw new HttpException('Invalid launcher token.', 401);
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
            $this->logger?->deviation(
                'launcher.session.rejected',
                'launcher_session_invalid_or_expired',
                'Launcher session is invalid or expired.',
                'warning',
                ['sessionState' => 'active'],
                ['sessionState' => 'missing_or_expired'],
                ['component' => 'launcher_session'],
            );
            throw new HttpException('Launcher session is invalid or expired.', 401);
        }

        $userUuid = Uuid::normalize((string)$row['userUuid']);
        $this->logger?->event(
            'launcher.session.resolved',
            'Launcher session resolved.',
            [
                'component' => 'launcher_session',
                'operation' => 'resolve',
                'targetUserUuid' => $userUuid,
            ],
            'DEBUG',
            'success',
        );
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
        } catch (HttpException $error) {
            if ($error->status() === 401) {
                return null;
            }
            throw $error;
        }
    }

    /** @return array{userUuid:string,profileId:string,login:string} */
    public function requireAuthenticated(string $accessToken): array
    {
        return $this->resolve($accessToken);
    }
}
