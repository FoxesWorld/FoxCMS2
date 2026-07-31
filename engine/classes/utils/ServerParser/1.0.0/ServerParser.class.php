<?php

declare(strict_types=1);

final class ServerParser
{
    private int $userGroup;

    public function __construct(
        private db $db,
        string $userUuid = '',
        private bool $parseAll = false,
    ) {
        $this->userGroup = $this->getUserGroup($userUuid);
    }

    public function parseServers(?string $serverName = null): string
    {
        $sql = 'SELECT * FROM `servers`';
        $parameters = [];
        if ($serverName !== null && $serverName !== '') {
            if (preg_match('/^[\p{L}\p{N}_. -]{1,64}$/uD', $serverName) !== 1) {
                return $this->encode(['error' => 'InvalidServerName']);
            }
            $sql .= ' WHERE `serverName` = :serverName';
            $parameters[':serverName'] = $serverName;
        }

        $statement = $this->db->prepare($sql);
        $statement->execute($parameters);
        $visible = [];
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $server) {
            $groups = array_filter(array_map('trim', explode(',', (string)($server['serverGroups'] ?? ''))));
            if (!in_array((string)$this->userGroup, $groups, true)) {
                continue;
            }
            if (($server['enabled'] ?? 'false') === 'true' || $this->parseAll) {
                $visible[] = $server;
            }
        }

        return $this->encode($visible ?: ['error' => 'ServerNotFound']);
    }

    private function getUserGroup(string $userUuid): int
    {
        if (!Uuid::isValid($userUuid)) {
            return 5;
        }

        $placeholders = [];
        $parameters = [];
        foreach (Uuid::databaseCandidates($userUuid) as $index => $candidate) {
            $placeholder = ':userUuid_' . $index;
            $placeholders[] = $placeholder;
            $parameters[$placeholder] = $candidate;
        }
        $statement = $this->db->prepare(
            'SELECT `user_group` FROM `users` '
            . 'WHERE `uuid` IN (' . implode(', ', $placeholders) . ') LIMIT 1'
        );
        $statement->execute($parameters);
        $group = $statement->fetchColumn();
        return $group === false ? 5 : (int)$group;
    }

    private function encode(array $payload): string
    {
        return json_encode(
            $payload,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
        );
    }
}
