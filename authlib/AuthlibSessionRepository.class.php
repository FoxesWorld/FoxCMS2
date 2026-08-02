<?php

declare(strict_types=1);

final class AuthlibSessionRepository
{
    public function __construct(private db $database)
    {
    }

    /** @return array{userUuid:string,profileId:string,username:string}|null */
    public function join(string $profileId, string $accessToken): ?array
    {
        $candidates = Uuid::databaseCandidates($profileId);
        if (preg_match('/^[a-f0-9]{32,128}$/D', $accessToken) !== 1) {
            return null;
        }

        [$identitySql, $identityParameters] = $this->identityPredicate('session.userUuid', $candidates, 'join_uuid');
        $statement = $this->database->prepare(
            'SELECT `session`.`userUuid`, `user`.`login` '
            . 'FROM `usersession` AS `session` '
            . 'INNER JOIN `users` AS `user` ON `user`.`uuid` = `session`.`userUuid` '
            . 'WHERE ' . $identitySql . ' '
            . 'AND `session`.`accessToken` = :accessToken '
            . 'AND `session`.`expiresAt` >= :currentTime LIMIT 1'
        );
        $statement->execute($identityParameters + [
            ':accessToken' => hash('sha256', $accessToken),
            ':currentTime' => CURRENT_TIME,
        ]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            return null;
        }

        return $this->profileResult($row);
    }

    public function attachServer(string $userUuid, string $serverId): bool
    {
        $statement = $this->database->prepare(
            'UPDATE `usersession` SET `serverId` = :serverId, `updatedAt` = CURRENT_TIMESTAMP(4) '
            . 'WHERE `userUuid` = :userUuid AND `expiresAt` >= :currentTime'
        );
        $statement->execute([
            ':serverId' => $serverId,
            ':userUuid' => Uuid::normalize($userUuid),
            ':currentTime' => CURRENT_TIME,
        ]);
        return $statement->rowCount() === 1;
    }

    /** @return array{userUuid:string,profileId:string,username:string}|null */
    public function findJoined(string $username, string $serverId): ?array
    {
        if (preg_match('/^[A-Za-z0-9_.-]{1,64}$/D', $username) !== 1) {
            return null;
        }

        $statement = $this->database->prepare(
            'SELECT `session`.`userUuid`, `user`.`login` '
            . 'FROM `usersession` AS `session` '
            . 'INNER JOIN `users` AS `user` ON `user`.`uuid` = `session`.`userUuid` '
            . 'WHERE `user`.`login` = :username '
            . 'AND `session`.`serverId` = :serverId '
            . 'AND `session`.`expiresAt` >= :currentTime LIMIT 1'
        );
        $statement->execute([
            ':username' => $username,
            ':serverId' => $serverId,
            ':currentTime' => CURRENT_TIME,
        ]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $this->profileResult($row) : null;
    }

    /** @return array{userUuid:string,profileId:string,username:string}|null */
    public function findProfile(string $profileId): ?array
    {
        $candidates = Uuid::databaseCandidates($profileId);
        [$identitySql, $identityParameters] = $this->identityPredicate('uuid', $candidates, 'profile_uuid');
        $statement = $this->database->prepare(
            'SELECT `uuid` AS `userUuid`, `login` FROM `users` WHERE ' . $identitySql . ' LIMIT 1'
        );
        $statement->execute($identityParameters);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $this->profileResult($row) : null;
    }

    /** @param list<string> $profileIds @return array<string, array{userUuid:string,profileId:string,username:string}> */
    public function findProfiles(array $profileIds): array
    {
        $identities = [];
        foreach ($profileIds as $profileId) {
            foreach (Uuid::databaseCandidates($profileId) as $identity) {
                $identities[$identity] = true;
            }
        }
        if ($identities === []) {
            return [];
        }

        [$identitySql, $identityParameters] = $this->identityPredicate(
            'uuid',
            array_keys($identities),
            'profiles_uuid',
        );
        $statement = $this->database->prepare(
            'SELECT `uuid` AS `userUuid`, `login` FROM `users` WHERE ' . $identitySql
        );
        $statement->execute($identityParameters);

        $profiles = [];
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $profile = $this->profileResult($row);
            $profiles[$profile['profileId']] = $profile;
        }
        return $profiles;
    }

    /**
     * @param list<string> $usernames
     * @return array<string, array{userUuid:string,profileId:string,username:string}>
     */
    public function findProfilesByNames(array $usernames): array
    {
        $normalized = [];
        foreach ($usernames as $username) {
            if (preg_match('/^[A-Za-z0-9_.-]{1,64}$/D', $username) !== 1) {
                continue;
            }
            $normalized[strtolower($username)] = $username;
        }
        if ($normalized === []) {
            return [];
        }

        $placeholders = [];
        $parameters = [];
        foreach (array_keys($normalized) as $index => $username) {
            $placeholder = ':profile_name_' . $index;
            $placeholders[] = $placeholder;
            $parameters[$placeholder] = $username;
        }

        $statement = $this->database->prepare(
            'SELECT `uuid` AS `userUuid`, `login` FROM `users` '
            . 'WHERE LOWER(`login`) IN (' . implode(', ', $placeholders) . ')'
        );
        $statement->execute($parameters);

        $profiles = [];
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $profile = $this->profileResult($row);
            $profiles[strtolower($profile['username'])] = $profile;
        }
        return $profiles;
    }

    /** @param list<string> $identities @return array{0:string,1:array<string,string>} */
    private function identityPredicate(string $column, array $identities, string $prefix): array
    {
        if ($identities === []) {
            throw new InvalidArgumentException('At least one identity candidate is required.');
        }

        $placeholders = [];
        $parameters = [];
        foreach (array_values(array_unique($identities)) as $index => $identity) {
            $placeholder = ':' . $prefix . '_' . $index;
            $placeholders[] = $placeholder;
            $parameters[$placeholder] = $identity;
        }
        return ['`' . str_replace('.', '`.`', $column) . '` IN (' . implode(', ', $placeholders) . ')', $parameters];
    }

    /** @param array<string,mixed> $row @return array{userUuid:string,profileId:string,username:string} */
    private function profileResult(array $row): array
    {
        $userUuid = Uuid::normalize((string)($row['userUuid'] ?? ''));
        return [
            'userUuid' => $userUuid,
            'profileId' => Uuid::compact($userUuid),
            'username' => (string)($row['login'] ?? ''),
        ];
    }
}
