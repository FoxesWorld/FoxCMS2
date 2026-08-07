<?php

declare(strict_types=1);

namespace FoxCMS\Api\Badge;

final class BadgeIdentifierMatcher
{
    /**
     * Match a badge by public slug, database ID, badgeName or title.
     *
     * @param list<array<string, mixed>> $badges
     * @return array<string, mixed>|null
     */
    public function find(array $badges, string $identifier): ?array
    {
        $needle = self::fold($identifier);
        if ($needle === '') {
            return null;
        }

        foreach ($badges as $badge) {
            foreach ([
                $badge['id'] ?? null,
                $badge['databaseId'] ?? null,
                $badge['badgeName'] ?? null,
                $badge['title'] ?? null,
            ] as $candidate) {
                if (is_scalar($candidate) && self::fold((string)$candidate) === $needle) {
                    return $badge;
                }
            }
        }

        return null;
    }

    private static function fold(string $value): string
    {
        $value = trim($value);
        return function_exists('mb_strtolower')
            ? mb_strtolower($value, 'UTF-8')
            : strtolower($value);
    }
}
