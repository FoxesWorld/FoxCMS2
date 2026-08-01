<?php

declare(strict_types=1);

final class BadgeSlug
{
    /** @var array<string, string> */
    private const CYRILLIC_TRANSLITERATION = [
        'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd',
        'е' => 'e', 'ё' => 'yo', 'ж' => 'zh', 'з' => 'z', 'и' => 'i',
        'й' => 'y', 'к' => 'k', 'л' => 'l', 'м' => 'm', 'н' => 'n',
        'о' => 'o', 'п' => 'p', 'р' => 'r', 'с' => 's', 'т' => 't',
        'у' => 'u', 'ф' => 'f', 'х' => 'kh', 'ц' => 'ts', 'ч' => 'ch',
        'ш' => 'sh', 'щ' => 'shch', 'ъ' => '', 'ы' => 'y', 'ь' => '',
        'э' => 'e', 'ю' => 'yu', 'я' => 'ya',
        'і' => 'i', 'ї' => 'yi', 'є' => 'ye', 'ґ' => 'g', 'ў' => 'u',
        'ј' => 'j', 'љ' => 'lj', 'њ' => 'nj', 'ђ' => 'dj', 'ћ' => 'c',
        'џ' => 'dz', 'ѓ' => 'g', 'ќ' => 'k', 'ѕ' => 'dz',
    ];

    public static function fromName(string $name, int|string|null $fallbackId = null): string
    {
        $value = self::cleanUtf8($name);
        if (class_exists(Normalizer::class)) {
            $normalized = Normalizer::normalize($value, Normalizer::FORM_D);
            if (is_string($normalized)) {
                $value = $normalized;
            }
        }
        $value = mb_strtolower($value, 'UTF-8');
        $value = strtr($value, self::CYRILLIC_TRANSLITERATION);
        $value = preg_replace('/\p{Mn}+/u', '', $value) ?? $value;

        if (function_exists('iconv')) {
            $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
            if (is_string($ascii) && $ascii !== '') {
                $value = $ascii;
            }
        }

        // Display names may contain any normal Unicode symbols. Symbols do not
        // become part of the route; they are treated as separators.
        $slug = strtolower($value);
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? '';
        $slug = trim(preg_replace('/-+/', '-', $slug) ?? $slug, '-');
        $slug = substr($slug, 0, 72);
        $slug = rtrim($slug, '-');

        if ($slug !== '') {
            return $slug;
        }

        $numericId = filter_var($fallbackId, FILTER_VALIDATE_INT);
        if ($numericId !== false && (int)$numericId > 0) {
            return 'badge-' . (int)$numericId;
        }

        return 'badge-' . substr(hash('sha256', self::cleanUtf8($name)), 0, 12);
    }

    /**
     * Assign deterministic, collision-safe page slugs to database rows.
     *
     * @param list<array<string, mixed>> $rows
     * @return list<array<string, mixed>>
     */
    public static function assign(array $rows): array
    {
        $bases = [];
        foreach ($rows as $index => $row) {
            $base = self::fromName(
                (string)($row['badgeName'] ?? ''),
                $row['id'] ?? null,
            );
            $bases[$index] = $base;
        }

        $counts = array_count_values($bases);
        $assigned = [];
        foreach ($rows as $index => $row) {
            $base = $bases[$index];
            $slug = $base;
            if (($counts[$base] ?? 0) > 1) {
                $numericId = filter_var($row['id'] ?? null, FILTER_VALIDATE_INT);
                $suffix = $numericId !== false && (int)$numericId > 0
                    ? (string)(int)$numericId
                    : substr(hash('sha256', self::cleanUtf8((string)($row['badgeName'] ?? ''))), 0, 8);
                $slug = substr($base, 0, max(1, 79 - strlen($suffix))) . '-' . $suffix;
            }
            $row['pageSlug'] = $slug;
            $assigned[] = $row;
        }
        return $assigned;
    }

    private static function cleanUtf8(string $value): string
    {
        if (function_exists('mb_scrub')) {
            return mb_scrub($value, 'UTF-8');
        }
        if (function_exists('iconv')) {
            $clean = iconv('UTF-8', 'UTF-8//IGNORE', $value);
            if (is_string($clean)) {
                return $clean;
            }
        }
        return $value;
    }
}
