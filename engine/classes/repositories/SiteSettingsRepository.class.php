<?php

declare(strict_types=1);

final class SiteSettingsRepository
{
    private const SCHEMA = 1;
    private const DEFAULT_TITLE_TEMPLATE = '%page% - %site%';
    private const ROBOTS = [
        'index,follow',
        'index,nofollow',
        'noindex,follow',
        'noindex,nofollow',
    ];
    private const TWITTER_CARDS = ['summary', 'summary_large_image'];
    private const MAIL_METHODS = ['smtp', 'mail'];
    private const SMTP_SECURITY = ['', 'ssl', 'tls'];

    public function __construct(private string $path)
    {
        $this->path = trim($this->path);
        if ($this->path === '') {
            throw new InvalidArgumentException('Site settings path must not be empty.');
        }
    }

    public function exists(): bool
    {
        return is_file($this->path);
    }

    /**
     * @param array<string, mixed> $fallback
     * @return array<string, mixed>
     */
    public function current(array $fallback): array
    {
        $stored = [];
        $updatedAt = '';
        $updatedByUuid = '';

        if ($this->exists()) {
            try {
                $document = $this->readDocument();
                $stored = is_array($document['settings'] ?? null) ? $document['settings'] : [];
                $updatedAt = trim((string)($document['updatedAt'] ?? ''));
                $updatedByUuid = trim((string)($document['updatedByUuid'] ?? ''));
            } catch (Throwable) {
                // Keep the site bootable with normalized defaults if the runtime file is damaged.
            }
        }

        return [
            'settings' => $this->normalize(array_replace($fallback, $stored), $fallback),
            'updatedAt' => $updatedAt !== '' ? $updatedAt : $this->fileTimestamp(),
            'updatedByUuid' => Uuid::isValid($updatedByUuid) ? Uuid::canonical($updatedByUuid) : '',
            'storageReady' => $this->storageReady(),
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @param array<string, mixed> $fallback
     * @return array<string, mixed>
     */
    public function save(array $input, array $fallback, string $updatedByUuid): array
    {
        $settings = $this->normalize(array_replace($fallback, $input), $fallback);
        $updatedByUuid = Uuid::isValid($updatedByUuid) ? Uuid::canonical($updatedByUuid) : '';
        $updatedAt = gmdate('c');

        $this->writeDocument([
            'schema' => self::SCHEMA,
            'updatedAt' => $updatedAt,
            'updatedByUuid' => $updatedByUuid,
            'settings' => $settings,
        ]);

        return [
            'settings' => $settings,
            'updatedAt' => $updatedAt,
            'updatedByUuid' => $updatedByUuid,
            'storageReady' => true,
        ];
    }

    /** @return array<string, mixed> */
    private function readDocument(): array
    {
        error_clear_last();
        $json = @file_get_contents($this->path);
        if (!is_string($json)) {
            throw new RuntimeException(
                'Unable to read the site settings file: ' . $this->path . '. ' . $this->lastFilesystemError()
            );
        }

        $decoded = json_decode($json, true, 64, JSON_THROW_ON_ERROR);
        if (!is_array($decoded) || array_is_list($decoded)) {
            throw new RuntimeException('The site settings file must contain a JSON object.');
        }

        return $decoded;
    }

    /** @param array<string, mixed> $document */
    private function writeDocument(array $document): void
    {
        $directory = dirname($this->path);
        if (!is_dir($directory)) {
            error_clear_last();
            if (!mkdir($directory, 0755, true) && !is_dir($directory)) {
                throw new RuntimeException(
                    'Unable to create the site settings directory: ' . $directory . '. ' . $this->lastFilesystemError()
                );
            }
        }

        clearstatcache(true, $directory);
        if (!is_writable($directory)) {
            throw new RuntimeException('The site settings directory is not writable: ' . $directory);
        }

        $encoded = json_encode(
            $document,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR,
        ) . PHP_EOL;

        $temporary = $directory . DIRECTORY_SEPARATOR . '.site-settings-' . bin2hex(random_bytes(12)) . '.tmp';
        $backup = null;

        try {
            error_clear_last();
            $written = @file_put_contents($temporary, $encoded, LOCK_EX);
            if ($written !== strlen($encoded)) {
                throw new RuntimeException(
                    'Unable to write the temporary site settings file: ' . $temporary . '. ' . $this->lastFilesystemError()
                );
            }
            @chmod($temporary, 0640);

            if ($this->exists()) {
                $backup = $directory . DIRECTORY_SEPARATOR . '.site-settings-backup-' . bin2hex(random_bytes(12)) . '.tmp';
                error_clear_last();
                if (!@rename($this->path, $backup)) {
                    throw new RuntimeException(
                        'Unable to prepare replacement of the site settings file: ' . $this->path . '. ' . $this->lastFilesystemError()
                    );
                }
            }

            error_clear_last();
            if (!@rename($temporary, $this->path)) {
                $replaceError = $this->lastFilesystemError();
                if (is_string($backup) && is_file($backup)) {
                    @rename($backup, $this->path);
                }
                throw new RuntimeException(
                    'Unable to replace the site settings file: ' . $this->path . '. ' . $replaceError
                );
            }

            @chmod($this->path, 0640);
            if (is_string($backup) && is_file($backup)) {
                @unlink($backup);
            }
        } catch (Throwable $error) {
            if (is_file($temporary)) {
                @unlink($temporary);
            }
            if (!is_file($this->path) && is_string($backup) && is_file($backup)) {
                @rename($backup, $this->path);
            }
            throw $error;
        }
    }

    private function storageReady(): bool
    {
        if ($this->exists()) {
            return is_readable($this->path) && is_writable($this->path);
        }

        $directory = dirname($this->path);
        return is_dir($directory) && is_writable($directory);
    }

    private function fileTimestamp(): string
    {
        if (!$this->exists()) {
            return '';
        }

        $timestamp = @filemtime($this->path);
        return is_int($timestamp) ? gmdate('c', $timestamp) : '';
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
            'discordLink' => $this->absoluteUrl((string)($values['discordLink'] ?? $fallback['discordLink'] ?? ''), ''),
            'telegramLink' => $this->absoluteUrl((string)($values['telegramLink'] ?? $fallback['telegramLink'] ?? ''), ''),
            'githubLink' => $this->absoluteUrl((string)($values['githubLink'] ?? $fallback['githubLink'] ?? ''), ''),
            'youtubeLink' => $this->absoluteUrl((string)($values['youtubeLink'] ?? $fallback['youtubeLink'] ?? ''), ''),
            'googleVerification' => $this->token((string)($values['googleVerification'] ?? '')),
            'yandexVerification' => $this->token((string)($values['yandexVerification'] ?? '')),
            'bingVerification' => $this->token((string)($values['bingVerification'] ?? '')),
            'mailMethod' => $this->enum((string)($values['mailMethod'] ?? $fallback['mailMethod'] ?? 'smtp'), self::MAIL_METHODS, 'smtp'),
            'mailFromAddress' => $this->email((string)($values['mailFromAddress'] ?? $fallback['mailFromAddress'] ?? '')),
            'mailFromName' => $this->text($values['mailFromName'] ?? $fallback['mailFromName'] ?? 'FoxesCraft', 120, 'FoxesCraft'),
            'smtpHost' => $this->hostname((string)($values['smtpHost'] ?? $fallback['smtpHost'] ?? 'smtp.mail.ru'), 'smtp.mail.ru'),
            'smtpPort' => (string)$this->port($values['smtpPort'] ?? $fallback['smtpPort'] ?? 465, 465),
            'smtpSecurity' => $this->enum((string)($values['smtpSecurity'] ?? $fallback['smtpSecurity'] ?? 'ssl'), self::SMTP_SECURITY, 'ssl'),
            'smtpUsername' => $this->email((string)($values['smtpUsername'] ?? $fallback['smtpUsername'] ?? '')),
            'smtpPassword' => $this->secret((string)($values['smtpPassword'] ?? $fallback['smtpPassword'] ?? '')),
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
            $value .= ' - %site%';
        }
        if (!str_contains($value, '%page%')) {
            $value = '%page% - ' . $value;
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

    private function email(string $value): string
    {
        $value = trim($value);
        return $value !== '' && filter_var($value, FILTER_VALIDATE_EMAIL) !== false
            ? mb_substr($value, 0, 254, 'UTF-8')
            : '';
    }

    private function hostname(string $value, string $fallback): string
    {
        $value = strtolower(trim($value));
        return preg_match('/^(?=.{1,253}$)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)*[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/D', $value) === 1
            ? $value
            : $fallback;
    }

    private function port(mixed $value, int $fallback): int
    {
        $port = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 65535]]);
        return is_int($port) ? $port : $fallback;
    }

    private function secret(string $value): string
    {
        $value = trim($value);
        if ($value === '' || strlen($value) > 512 || preg_match('/[\x00-\x1F\x7F]/', $value) === 1) return '';
        return $value;
    }

    private function lastFilesystemError(): string
    {
        $error = error_get_last();
        return is_array($error) && is_string($error['message'] ?? null)
            ? trim((string)$error['message'])
            : 'Filesystem did not provide a detailed error.';
    }
}
