<?php

declare(strict_types=1);

final class SiteSettingsRepository
{
    private const DEFAULT_TITLE_TEMPLATE = '%page% — %site%';
    private const ROBOTS = [
        'index,follow',
        'index,nofollow',
        'noindex,follow',
        'noindex,nofollow',
    ];
    private const TWITTER_CARDS = ['summary', 'summary_large_image'];

    public function __construct(private db $db)
    {
    }

    /**
     * @param array<string, mixed> $fallback
     * @return array<string, mixed>
     */
    public function current(array $fallback, bool $ensureSchema = false): array
    {
        if ($ensureSchema) {
            $this->ensureSchema();
        }

        $stored = [];
        $updatedAt = '';
        $updatedByUuid = '';
        $storageReady = false;

        try {
            $statement = $this->db->prepare(
                'SELECT `settings`, `updatedAt`, `updatedByUuid` FROM `site_settings` WHERE `id` = 1 LIMIT 1'
            );
            $statement->execute();
            $row = $statement->fetch(PDO::FETCH_ASSOC);
            $storageReady = true;
            if (is_array($row)) {
                $decoded = json_decode((string)($row['settings'] ?? '{}'), true);
                if (is_array($decoded)) {
                    $stored = $decoded;
                }
                $updatedAt = (string)($row['updatedAt'] ?? '');
                $updatedByUuid = (string)($row['updatedByUuid'] ?? '');
            }
        } catch (Throwable) {
            $storageReady = false;
        }

        return [
            'settings' => $this->normalize(array_replace($fallback, $stored), $fallback),
            'updatedAt' => $updatedAt,
            'updatedByUuid' => $updatedByUuid,
            'storageReady' => $storageReady,
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @param array<string, mixed> $fallback
     * @return array<string, mixed>
     */
    public function save(array $input, array $fallback, string $updatedByUuid): array
    {
        $this->ensureSchema();
        $settings = $this->normalize(array_replace($fallback, $input), $fallback);
        $updatedByUuid = Uuid::isValid($updatedByUuid) ? Uuid::canonical($updatedByUuid) : '';

        $statement = $this->db->prepare(
            'UPDATE `site_settings` SET `settings` = :settings, `updatedByUuid` = :updatedByUuid, '
            . '`updatedAt` = CURRENT_TIMESTAMP(4) WHERE `id` = 1'
        );
        $statement->execute([
            ':settings' => json_encode(
                $settings,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
            ),
            ':updatedByUuid' => $updatedByUuid === '' ? null : $updatedByUuid,
        ]);

        return $this->current($fallback);
    }

    public function ensureSchema(): void
    {
        $this->db->exec(
            'CREATE TABLE IF NOT EXISTS `site_settings` ('
            . '`id` TINYINT UNSIGNED NOT NULL, '
            . '`settings` LONGTEXT NOT NULL, '
            . '`updatedAt` DATETIME(4) NOT NULL DEFAULT CURRENT_TIMESTAMP(4) ON UPDATE CURRENT_TIMESTAMP(4), '
            . '`updatedByUuid` CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NULL, '
            . 'PRIMARY KEY (`id`)'
            . ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
        $statement = $this->db->prepare(
            'INSERT IGNORE INTO `site_settings` (`id`, `settings`) VALUES (1, :settings)'
        );
        $statement->execute([':settings' => '{}']);
    }

    /**
     * @param array<string, mixed> $values
     * @param array<string, mixed> $fallback
     * @return array<string, string>
     */
    private function normalize(array $values, array $fallback): array
    {
        $siteTitle = $this->text($values['siteTitle'] ?? $fallback['siteTitle'] ?? 'FoxesCraft', 120, 'FoxesCraft');
        $siteDesc = $this->text($values['siteDesc'] ?? $fallback['siteDesc'] ?? '', 320, '');
        $canonicalFallback = (string)($fallback['canonicalUrl'] ?? '');

        return [
            'siteTitle' => $siteTitle,
            'siteStatus' => $this->text($values['siteStatus'] ?? $fallback['siteStatus'] ?? '', 120, ''),
            'siteDesc' => $siteDesc,
            'homeTitle' => $this->text($values['homeTitle'] ?? $fallback['homeTitle'] ?? $siteTitle, 180, $siteTitle),
            'titleTemplate' => $this->titleTemplate((string)($values['titleTemplate'] ?? self::DEFAULT_TITLE_TEMPLATE)),
            'keywords' => $this->keywords($values['keywords'] ?? $fallback['keywords'] ?? ''),
            'robots' => $this->enum((string)($values['robots'] ?? 'index,follow'), self::ROBOTS, 'index,follow'),
            'canonicalUrl' => $this->absoluteUrl((string)($values['canonicalUrl'] ?? $canonicalFallback), $canonicalFallback),
            'lang' => $this->language((string)($values['lang'] ?? $fallback['lang'] ?? 'ru'), 'ru'),
            'locale' => $this->locale((string)($values['locale'] ?? $fallback['locale'] ?? 'ru_RU'), 'ru_RU'),
            'author' => $this->text($values['author'] ?? $fallback['author'] ?? 'FoxesCraft', 120, 'FoxesCraft'),
            'themeColor' => $this->color((string)($values['themeColor'] ?? $fallback['themeColor'] ?? '#152019'), '#152019'),
            'faviconUrl' => $this->assetUrl((string)($values['faviconUrl'] ?? $fallback['faviconUrl'] ?? '/favicon.ico')),
            'ogSiteName' => $this->text($values['ogSiteName'] ?? $fallback['ogSiteName'] ?? $siteTitle, 120, $siteTitle),
            'ogTitle' => $this->text($values['ogTitle'] ?? $fallback['ogTitle'] ?? $siteTitle, 180, $siteTitle),
            'ogDescription' => $this->text($values['ogDescription'] ?? $fallback['ogDescription'] ?? $siteDesc, 320, $siteDesc),
            'ogImage' => $this->assetUrl((string)($values['ogImage'] ?? $fallback['ogImage'] ?? '')),
            'twitterCard' => $this->enum((string)($values['twitterCard'] ?? 'summary_large_image'), self::TWITTER_CARDS, 'summary_large_image'),
            'twitterSite' => $this->socialHandle((string)($values['twitterSite'] ?? '')),
            'twitterCreator' => $this->socialHandle((string)($values['twitterCreator'] ?? '')),
            'googleVerification' => $this->token((string)($values['googleVerification'] ?? '')),
            'yandexVerification' => $this->token((string)($values['yandexVerification'] ?? '')),
            'bingVerification' => $this->token((string)($values['bingVerification'] ?? '')),
        ];
    }

    private function text(mixed $value, int $limit, string $fallback): string
    {
        $value = trim(preg_replace('/\s+/u', ' ', (string)$value) ?? '');
        if ($value === '') {
            return $fallback;
        }
        return mb_substr($value, 0, $limit, 'UTF-8');
    }

    private function titleTemplate(string $value): string
    {
        $value = $this->text($value, 180, self::DEFAULT_TITLE_TEMPLATE);
        if (!str_contains($value, '%site%')) {
            $value .= ' — %site%';
        }
        if (!str_contains($value, '%page%')) {
            $value = '%page% — ' . $value;
        }
        return mb_substr($value, 0, 180, 'UTF-8');
    }

    private function keywords(mixed $value): string
    {
        $source = is_array($value) ? $value : preg_split('/[,;\r\n]+/u', (string)$value);
        $items = [];
        foreach (is_array($source) ? $source : [] as $keyword) {
            $keyword = trim(preg_replace('/\s+/u', ' ', (string)$keyword) ?? '');
            if ($keyword !== '') {
                $items[mb_strtolower($keyword, 'UTF-8')] = $keyword;
            }
        }
        return implode(', ', array_values($items));
    }

    /** @param list<string> $allowed */
    private function enum(string $value, array $allowed, string $fallback): string
    {
        $value = trim($value);
        return in_array($value, $allowed, true) ? $value : $fallback;
    }

    private function absoluteUrl(string $value, string $fallback = ''): string
    {
        $value = trim($value);
        if ($value === '') {
            return trim($fallback);
        }
        if (filter_var($value, FILTER_VALIDATE_URL) === false || !preg_match('#^https?://#i', $value)) {
            return trim($fallback);
        }
        return rtrim(mb_substr($value, 0, 2048, 'UTF-8'), '/');
    }

    private function assetUrl(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }
        if (str_starts_with($value, '/')) {
            return mb_substr($value, 0, 2048, 'UTF-8');
        }
        return $this->absoluteUrl($value, '');
    }

    private function language(string $value, string $fallback): string
    {
        $value = trim($value);
        return preg_match('/^[a-z]{2,3}(?:-[A-Z]{2})?$/D', $value) === 1 ? $value : $fallback;
    }

    private function locale(string $value, string $fallback): string
    {
        $value = trim($value);
        return preg_match('/^[a-z]{2,3}_[A-Z]{2}$/D', $value) === 1 ? $value : $fallback;
    }

    private function color(string $value, string $fallback): string
    {
        $value = trim($value);
        return preg_match('/^#[0-9A-Fa-f]{6}$/D', $value) === 1 ? strtolower($value) : $fallback;
    }

    private function socialHandle(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }
        $value = '@' . ltrim($value, '@');
        return preg_match('/^@[A-Za-z0-9_]{1,30}$/D', $value) === 1 ? $value : '';
    }

    private function token(string $value): string
    {
        $value = preg_replace('/\s+/u', '', trim($value)) ?? '';
        if ($value === '' || preg_match('/^[A-Za-z0-9._:-]{1,180}$/D', $value) !== 1) {
            return '';
        }
        return $value;
    }
}
