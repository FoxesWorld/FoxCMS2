<?php

declare(strict_types=1);

final class GroupRepository
{
    public function __construct(private $db)
    {
    }

    public static function normalizeTag(mixed $value, string $fallback = 'guest'): string
    {
        $tag = strtolower(trim((string)$value));
        return preg_match('/^[a-z][a-z0-9_-]{0,63}$/D', $tag) === 1 ? $tag : $fallback;
    }

    public function find(string $groupTag): array
    {
        $groupTag = self::normalizeTag($groupTag);
        $statement = $this->db->prepare(
            'SELECT `groupTag`, `groupName`, `groupColor` '
            . 'FROM `groupAssociation` WHERE `groupTag` = :groupTag LIMIT 1'
        );
        $statement->execute([':groupTag' => $groupTag]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $this->normalizeRow($row) : $this->fallback($groupTag);
    }

    public function findByLegacyNumber(int $groupNumber): array
    {
        $statement = $this->db->prepare(
            'SELECT `groupTag`, `groupName`, `groupColor` '
            . 'FROM `groupAssociation` WHERE `groupNum` = :groupNumber LIMIT 1'
        );
        $statement->execute([':groupNumber' => max(1, $groupNumber)]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $this->normalizeRow($row) : $this->fallback('guest');
    }

    public function resolveTag(mixed $identity, string $fallback = 'guest'): string
    {
        if (is_int($identity) || (is_string($identity) && ctype_digit(trim($identity)))) {
            $statement = $this->db->prepare(
                'SELECT `groupTag` FROM `groupAssociation` WHERE `groupNum` = :groupNumber LIMIT 1'
            );
            $statement->execute([':groupNumber' => max(1, (int)$identity)]);
            $legacyTag = $statement->fetchColumn();
            return is_string($legacyTag) ? self::normalizeTag($legacyTag, $fallback) : $fallback;
        }

        $tag = self::normalizeTag($identity, $fallback);
        return $this->exists($tag) ? $tag : $fallback;
    }

    public function exists(string $groupTag): bool
    {
        $groupTag = self::normalizeTag($groupTag, '');
        if ($groupTag === '') {
            return false;
        }
        $statement = $this->db->prepare(
            'SELECT 1 FROM `groupAssociation` WHERE `groupTag` = :groupTag LIMIT 1'
        );
        $statement->execute([':groupTag' => $groupTag]);
        return $statement->fetchColumn() !== false;
    }

    /** @return list<array{groupTag:string,groupName:string,groupColor:string}> */
    public function all(): array
    {
        $statement = $this->db->prepare(
            'SELECT `groupTag`, `groupName`, `groupColor` '
            . 'FROM `groupAssociation` ORDER BY `groupName`, `groupTag`'
        );
        $statement->execute();
        $groups = [];
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            if (is_array($row)) {
                $groups[] = $this->normalizeRow($row);
            }
        }
        return $groups;
    }

    private function normalizeRow(array $row): array
    {
        $tag = self::normalizeTag($row['groupTag'] ?? 'guest');
        $color = strtolower(trim((string)($row['groupColor'] ?? '#ffffff')));
        if (preg_match('/^#[0-9a-f]{6}$/D', $color) !== 1) {
            $color = '#ffffff';
        }
        return [
            'groupTag' => $tag,
            'groupName' => trim((string)($row['groupName'] ?? '')) ?: $tag,
            'groupColor' => $color,
        ];
    }

    private function fallback(string $groupTag): array
    {
        $tag = self::normalizeTag($groupTag);
        return [
            'groupTag' => $tag,
            'groupName' => $tag === 'guest' ? 'Гости' : $tag,
            'groupColor' => '#ffffff',
        ];
    }
}
