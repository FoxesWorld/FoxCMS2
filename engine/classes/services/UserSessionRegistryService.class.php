<?php

declare(strict_types=1);

final class UserSessionRegistryService
{
    private int $idleSeconds;
    private int $absoluteSeconds;
    private int $rememberSeconds;

    public function __construct(private db $db, array $config = [])
    {
        $security = is_array($config['securitySetings'] ?? null)
            ? $config['securitySetings']
            : [];
        $this->idleSeconds = max(300, (int)($security['sessionIdleSeconds'] ?? 7200));
        $this->absoluteSeconds = max(900, (int)($security['sessionAbsoluteSeconds'] ?? 86400));
        $this->rememberSeconds = max(3600, (int)($security['rememberSeconds'] ?? 31536000));
    }

    public static function isSchemaMissing(Throwable $error): bool
    {
        do {
            $message = strtolower($error->getMessage());
            if (
                str_contains($message, 'sqlstate 42s02')
                || str_contains($message, "table 'userbrowsersessions' doesn't exist")
                || str_contains($message, "table `userbrowsersessions` doesn't exist")
                || (str_contains($message, '1146') && str_contains($message, 'userbrowsersessions'))
            ) {
                return true;
            }
            $error = $error->getPrevious();
        } while ($error instanceof Throwable);

        return false;
    }

    /**
     * @param array<string,mixed> $context
     * @return array{sessionUuid:string,sessionType:string,token:?string,expiresAt:int}
     */
    public function issueForAuthenticatedSession(
        UserSession $session,
        string $userUuid,
        bool $remembered,
        array $context,
    ): array {
        $userUuid = Uuid::normalize($userUuid);
        $now = time();
        $sessionUuid = Uuid::v7();
        $issued = $remembered ? RememberToken::issue($this->rememberSeconds) : null;
        $sessionType = $remembered ? 'remembered' : 'short';
        $createdAt = max(1, $session->issuedAt() ?: $now);
        $expiresAt = $remembered
            ? (int)$issued['expiresAt']
            : $createdAt + $this->absoluteSeconds;
        $idleExpiresAt = $remembered
            ? $expiresAt
            : min($expiresAt, $now + $this->idleSeconds);

        $statement = $this->db->prepare(
            'INSERT INTO `userBrowserSessions` '
            . '(`sessionUuid`, `userUuid`, `rememberDigest`, `sessionType`, `ipAddress`, `userAgent`, '
            . '`browser`, `operatingSystem`, `deviceLabel`, `locationLabel`, `createdAt`, `lastSeenAt`, '
            . '`expiresAt`, `idleExpiresAt`) VALUES '
            . '(:sessionUuid, :userUuid, :rememberDigest, :sessionType, :ipAddress, :userAgent, '
            . ':browser, :operatingSystem, :deviceLabel, :locationLabel, :createdAt, :lastSeenAt, '
            . ':expiresAt, :idleExpiresAt)'
        );
        $statement->execute([
            ':sessionUuid' => $sessionUuid,
            ':userUuid' => $userUuid,
            ':rememberDigest' => $issued !== null ? (string)$issued['digest'] : null,
            ':sessionType' => $sessionType,
            ':ipAddress' => $this->contextValue($context, 'ip', 45),
            ':userAgent' => $this->contextValue($context, 'userAgent', 512),
            ':browser' => $this->contextValue($context, 'browser', 100),
            ':operatingSystem' => $this->contextValue($context, 'operatingSystem', 100),
            ':deviceLabel' => $this->contextValue($context, 'deviceLabel', 180, 'неизвестное устройство'),
            ':locationLabel' => $this->contextValue($context, 'locationLabel', 180, 'регион не определён'),
            ':createdAt' => $createdAt,
            ':lastSeenAt' => $now,
            ':expiresAt' => $expiresAt,
            ':idleExpiresAt' => $idleExpiresAt,
        ]);
        $session->bindBrowserSession($sessionUuid, $sessionType);

        return [
            'sessionUuid' => $sessionUuid,
            'sessionType' => $sessionType,
            'token' => $issued !== null ? (string)$issued['token'] : null,
            'expiresAt' => $expiresAt,
        ];
    }

    /**
     * @param array<string,mixed> $context
     * @return array{token:string,expiresAt:int,userUuid:string}|null
     */
    public function restoreRememberedSession(
        string $token,
        UserSession $session,
        array $context,
    ): ?array {
        if (!RememberToken::isUsable($token, $this->rememberSeconds)) {
            return null;
        }
        $digest = RememberToken::digest($token);
        $now = time();
        $result = $this->db->transactional(function () use ($digest, $context, $now): ?array {
            $statement = $this->db->prepare(
                'SELECT `user`.*, '
                . '`browserSession`.`id` AS `registryId`, '
                . '`browserSession`.`sessionUuid` AS `registrySessionUuid` '
                . 'FROM `userBrowserSessions` AS `browserSession` '
                . 'INNER JOIN `users` AS `user` ON `user`.`uuid` = `browserSession`.`userUuid` '
                . 'WHERE `browserSession`.`rememberDigest` = :digest '
                . 'AND `browserSession`.`sessionType` = \'remembered\' '
                . 'AND `browserSession`.`revokedAt` IS NULL '
                . 'AND `browserSession`.`expiresAt` > :now LIMIT 1 FOR UPDATE'
            );
            $statement->execute([':digest' => $digest, ':now' => $now]);
            $row = $statement->fetch(PDO::FETCH_ASSOC);
            if (!is_array($row)) {
                return null;
            }

            $issued = RememberToken::issue($this->rememberSeconds);
            $update = $this->db->prepare(
                'UPDATE `userBrowserSessions` SET '
                . '`rememberDigest` = :rememberDigest, `ipAddress` = :ipAddress, `userAgent` = :userAgent, '
                . '`browser` = :browser, `operatingSystem` = :operatingSystem, `deviceLabel` = :deviceLabel, '
                . '`locationLabel` = :locationLabel, `lastSeenAt` = :lastSeenAt, '
                . '`expiresAt` = :expiresAt, `idleExpiresAt` = :idleExpiresAt '
                . 'WHERE `id` = :id'
            );
            $update->execute([
                ':rememberDigest' => (string)$issued['digest'],
                ':ipAddress' => $this->contextValue($context, 'ip', 45),
                ':userAgent' => $this->contextValue($context, 'userAgent', 512),
                ':browser' => $this->contextValue($context, 'browser', 100),
                ':operatingSystem' => $this->contextValue($context, 'operatingSystem', 100),
                ':deviceLabel' => $this->contextValue($context, 'deviceLabel', 180, 'неизвестное устройство'),
                ':locationLabel' => $this->contextValue($context, 'locationLabel', 180, 'регион не определён'),
                ':lastSeenAt' => $now,
                ':expiresAt' => (int)$issued['expiresAt'],
                ':idleExpiresAt' => (int)$issued['expiresAt'],
                ':id' => (int)$row['registryId'],
            ]);

            $registrySessionUuid = Uuid::normalize((string)$row['registrySessionUuid']);
            unset($row['registryId'], $row['registrySessionUuid'], $row['password'], $row['token']);
            return [
                'user' => $row,
                'sessionUuid' => $registrySessionUuid,
                'token' => (string)$issued['token'],
                'expiresAt' => (int)$issued['expiresAt'],
            ];
        });
        if ($result === null) {
            return null;
        }

        $session->authenticate((array)$result['user']);
        $session->bindBrowserSession((string)$result['sessionUuid'], 'remembered');
        return [
            'token' => (string)$result['token'],
            'expiresAt' => (int)$result['expiresAt'],
            'userUuid' => $session->uuid(),
        ];
    }

    /** @param array<string,mixed> $context */
    public function synchronizeCurrentSession(UserSession $session, array $context): bool
    {
        if (!$session->isLogged()) {
            return false;
        }
        $sessionUuid = $session->browserSessionUuid();
        if ($sessionUuid === '') {
            $this->issueForAuthenticatedSession($session, $session->uuid(), false, $context);
            return true;
        }

        $now = time();
        $statement = $this->db->prepare(
            'SELECT `sessionType`, `expiresAt`, `idleExpiresAt` FROM `userBrowserSessions` '
            . 'WHERE `sessionUuid` = :sessionUuid AND `userUuid` = :userUuid '
            . 'AND `revokedAt` IS NULL LIMIT 1'
        );
        $statement->execute([
            ':sessionUuid' => $sessionUuid,
            ':userUuid' => $session->uuid(),
        ]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            return false;
        }
        $type = (string)($row['sessionType'] ?? 'short');
        $expiresAt = max(0, (int)($row['expiresAt'] ?? 0));
        $idleExpiresAt = max(0, (int)($row['idleExpiresAt'] ?? 0));
        if ($expiresAt <= $now || ($type !== 'remembered' && $idleExpiresAt <= $now)) {
            return false;
        }

        $newIdleExpiresAt = $type === 'remembered'
            ? $expiresAt
            : min($expiresAt, $now + $this->idleSeconds);
        $update = $this->db->prepare(
            'UPDATE `userBrowserSessions` SET `lastSeenAt` = :lastSeenAt, '
            . '`idleExpiresAt` = :idleExpiresAt WHERE `sessionUuid` = :sessionUuid'
        );
        $update->execute([
            ':lastSeenAt' => $now,
            ':idleExpiresAt' => $newIdleExpiresAt,
            ':sessionUuid' => $sessionUuid,
        ]);
        return true;
    }

    /** @return array{sessions:list<array<string,mixed>>,activeCount:int} */
    public function activeSessions(string $userUuid, string $currentSessionUuid = ''): array
    {
        $userUuid = Uuid::normalize($userUuid);
        $now = time();
        $statement = $this->db->prepare(
            'SELECT `sessionUuid`, `sessionType`, `ipAddress`, `browser`, `operatingSystem`, '
            . '`deviceLabel`, `locationLabel`, `createdAt`, `lastSeenAt`, `expiresAt`, `idleExpiresAt` '
            . 'FROM `userBrowserSessions` WHERE `userUuid` = :userUuid '
            . 'AND `revokedAt` IS NULL AND `expiresAt` > :expiresNow '
            . 'AND (`sessionType` = \'remembered\' OR `idleExpiresAt` > :idleNow) '
            . 'ORDER BY `lastSeenAt` DESC, `id` DESC'
        );
        $statement->execute([
            ':userUuid' => $userUuid,
            ':expiresNow' => $now,
            ':idleNow' => $now,
        ]);
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $sessions = [];
        foreach ($rows as $row) {
            $sessionUuid = trim((string)($row['sessionUuid'] ?? ''));
            if (!Uuid::isValid($sessionUuid)) {
                continue;
            }
            $type = (string)($row['sessionType'] ?? 'short') === 'remembered' ? 'remembered' : 'short';
            $expiresAt = max(0, (int)($row['expiresAt'] ?? 0));
            $idleExpiresAt = max(0, (int)($row['idleExpiresAt'] ?? 0));
            $sessions[] = [
                'sessionUuid' => Uuid::normalize($sessionUuid),
                'current' => $currentSessionUuid !== '' && Uuid::equals($sessionUuid, $currentSessionUuid),
                'sessionType' => $type,
                'remembered' => $type === 'remembered',
                'ipAddress' => trim((string)($row['ipAddress'] ?? '')),
                'browser' => trim((string)($row['browser'] ?? '')),
                'operatingSystem' => trim((string)($row['operatingSystem'] ?? '')),
                'deviceLabel' => trim((string)($row['deviceLabel'] ?? '')) ?: 'Неизвестное устройство',
                'locationLabel' => trim((string)($row['locationLabel'] ?? '')) ?: 'Регион не определён',
                'createdAt' => max(0, (int)($row['createdAt'] ?? 0)),
                'lastSeenAt' => max(0, (int)($row['lastSeenAt'] ?? 0)),
                'expiresAt' => $type === 'remembered' ? $expiresAt : min($expiresAt, $idleExpiresAt),
            ];
        }
        usort($sessions, static fn (array $left, array $right): int =>
            ((int)$right['current'] <=> (int)$left['current'])
            ?: ((int)$right['lastSeenAt'] <=> (int)$left['lastSeenAt'])
        );
        return ['sessions' => array_values($sessions), 'activeCount' => count($sessions)];
    }

    public function revokeSession(
        string $userUuid,
        string $sessionUuid,
        string $currentSessionUuid = '',
    ): bool {
        $userUuid = Uuid::normalize($userUuid);
        $sessionUuid = Uuid::normalize($sessionUuid);
        if ($currentSessionUuid !== '' && Uuid::equals($sessionUuid, $currentSessionUuid)) {
            throw new LogicException('Текущую сессию нельзя деактивировать с этой страницы.');
        }

        $statement = $this->db->prepare(
            'UPDATE `userBrowserSessions` SET `revokedAt` = :revokedAt, `rememberDigest` = NULL '
            . 'WHERE `sessionUuid` = :sessionUuid AND `userUuid` = :userUuid AND `revokedAt` IS NULL'
        );
        $statement->execute([
            ':revokedAt' => time(),
            ':sessionUuid' => $sessionUuid,
            ':userUuid' => $userUuid,
        ]);
        return $statement->rowCount() === 1;
    }

    public function revokeCurrentSession(UserSession $session): bool
    {
        $sessionUuid = $session->browserSessionUuid();
        if (!$session->isLogged() || $sessionUuid === '') {
            return false;
        }
        $statement = $this->db->prepare(
            'UPDATE `userBrowserSessions` SET `revokedAt` = :revokedAt, `rememberDigest` = NULL '
            . 'WHERE `sessionUuid` = :sessionUuid AND `userUuid` = :userUuid AND `revokedAt` IS NULL'
        );
        $statement->execute([
            ':revokedAt' => time(),
            ':sessionUuid' => $sessionUuid,
            ':userUuid' => $session->uuid(),
        ]);
        return $statement->rowCount() === 1;
    }

    private function contextValue(
        array $context,
        string $key,
        int $length,
        string $fallback = '',
    ): string {
        $value = trim((string)($context[$key] ?? ''));
        if ($value === '') {
            $value = $fallback;
        }
        return function_exists('mb_substr')
            ? mb_substr($value, 0, $length, 'UTF-8')
            : substr($value, 0, $length);
    }
}
