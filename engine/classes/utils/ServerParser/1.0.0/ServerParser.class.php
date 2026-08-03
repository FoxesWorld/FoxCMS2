<?php

declare(strict_types=1);

final class ServerParser
{
    private string $userGroupTag;
    private GroupRepository $groups;

    public function __construct(
        private db $db,
        string $userUuid = '',
        private bool $parseAll = false,
    ) {
        $this->groups = new GroupRepository($db);
        $this->userGroupTag = $this->getUserGroupTag($userUuid);
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
            $groups = $this->normalizeServerGroups($server['serverGroups'] ?? []);
            if (!in_array($this->userGroupTag, $groups, true)) {
                continue;
            }
            if (filter_var($server['enabled'] ?? false, FILTER_VALIDATE_BOOLEAN) || $this->parseAll) {
                $server['serverGroups'] = $groups;
                $visible[] = $server;
            }
        }

        return $this->encode($visible ?: ['error' => 'ServerNotFound']);
    }

    private function getUserGroupTag(string $userUuid): string
    {
        if (!Uuid::isValid($userUuid)) {
            return 'guest';
        }

        $placeholders = [];
        $parameters = [];
        foreach (Uuid::databaseCandidates($userUuid) as $index => $candidate) {
            $placeholder = ':userUuid_' . $index;
            $placeholders[] = $placeholder;
            $parameters[$placeholder] = $candidate;
        }
        $statement = $this->db->prepare(
            'SELECT `groupTag` FROM `users` '
            . 'WHERE `uuid` IN (' . implode(', ', $placeholders) . ') LIMIT 1'
        );
        $statement->execute($parameters);
        $groupTag = $statement->fetchColumn();
        return is_string($groupTag) ? GroupRepository::normalizeTag($groupTag) : 'guest';
    }

    /** @return list<string> */
    private function normalizeServerGroups(mixed $value): array
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            $source = is_array($decoded) ? $decoded : explode(',', $value);
        } elseif (is_array($value)) {
            $source = $value;
        } else {
            $source = [];
        }

        $tags = [];
        foreach ($source as $group) {
            $tag = $this->groups->resolveTag($group, '');
            if ($tag !== '' && $this->groups->exists($tag)) {
                $tags[] = $tag;
            }
        }
        $tags = array_values(array_unique($tags));
        sort($tags, SORT_STRING);
        return $tags;
    }

    private function encode(array $payload): string
    {
        return json_encode(
            $payload,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
        );
    }
}
