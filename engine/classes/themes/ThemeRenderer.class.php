<?php

declare(strict_types=1);

final class ThemeRenderer
{
    private const USER_FIELDS = [
        'isLogged', 'uuid', 'user_id', 'login', 'realname', 'groupTag', 'profilePhoto',
        'userStatus', 'land', 'colorScheme', 'balance', 'email', 'groupName', 'groupColor',
        'notificationsUnread',
    ];

    public function __construct(
        private array $config,
        private array $user,
        private array $theme,
        private array $frontend,
    ) {
    }

    public function render(): void
    {
        $html = file_get_contents((string)$this->theme['shell']);
        if ($html === false) {
            throw new RuntimeException('Theme shell could not be read.');
        }

        $bootstrap = json_encode(
            $this->bootstrapPayload(),
            JSON_UNESCAPED_UNICODE
            | JSON_UNESCAPED_SLASHES
            | JSON_HEX_TAG
            | JSON_HEX_AMP
            | JSON_HEX_APOS
            | JSON_HEX_QUOT
            | JSON_THROW_ON_ERROR
        );
        $bootstrapScript = '<script id="foxescraft-bootstrap" type="application/json">'
            . $bootstrap . '</script>';
        $html = preg_replace(
            '#<script\s+id="foxescraft-bootstrap"\s+type="application/json">.*?</script>#s',
            $bootstrapScript,
            $html,
            1
        ) ?? $html;

        $styles = implode("\n  ", array_map(
            static fn(string $url): string => '<link rel="stylesheet" href="' . htmlspecialchars($url, ENT_QUOTES) . '">',
            $this->theme['styles'] ?? []
        ));
        $scripts = implode("\n  ", array_map(
            static fn(string $url): string => '<script type="module" src="' . htmlspecialchars($url, ENT_QUOTES) . '"></script>',
            $this->theme['scripts'] ?? []
        ));
        $html = str_replace('<!-- foxescraft:styles -->', $styles, $html);
        $html = str_replace('<!-- foxescraft:scripts -->', $scripts, $html);

        $site = is_array($this->config['siteSettings'] ?? null) ? $this->config['siteSettings'] : [];
        $html = $this->applySeo($html, $site);
        $html = $this->setHtmlAttribute($html, 'lang', (string)($site['lang'] ?? 'ru'));
        $html = $this->setHtmlAttribute($html, 'data-theme', (string)$this->theme['name']);

        header('Content-Type: text/html; charset=UTF-8');
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Cache-Control: no-store, max-age=0');
        echo $html;
    }

    private function applySeo(string $html, array $site): string
    {
        $siteTitle = (string)($site['siteTitle'] ?? 'FoxesCraft');
        $title = (string)($site['homeTitle'] ?? $siteTitle);
        $description = (string)($site['siteDesc'] ?? '');
        $canonical = (string)($site['canonicalUrl'] ?? $this->config['environment']['publicBaseUrl'] ?? '');
        $ogImage = $this->absoluteAssetUrl((string)($site['ogImage'] ?? ''), $canonical);
        $favicon = (string)($site['faviconUrl'] ?? '/favicon.ico');

        $html = preg_replace(
            '#<title>.*?</title>#is',
            '<title>' . $this->escape($title !== '' ? $title : $siteTitle) . '</title>',
            $html,
            1,
        ) ?? $html;

        $html = $this->upsertMeta($html, 'description', $description);
        $html = $this->upsertMeta($html, 'keywords', (string)($site['keywords'] ?? ''));
        $html = $this->upsertMeta($html, 'robots', (string)($site['robots'] ?? 'index,follow'));
        $html = $this->upsertMeta($html, 'author', (string)($site['author'] ?? 'FoxesCraft'));
        $html = $this->upsertMeta($html, 'application-name', $siteTitle);
        $html = $this->upsertMeta($html, 'theme-color', (string)($site['themeColor'] ?? '#152019'));

        $html = $this->upsertLink($html, 'canonical', $canonical);
        $html = $this->upsertLink($html, 'icon', $favicon);

        $html = $this->upsertPropertyMeta($html, 'og:type', 'website');
        $html = $this->upsertPropertyMeta($html, 'og:site_name', (string)($site['ogSiteName'] ?? $siteTitle));
        $html = $this->upsertPropertyMeta($html, 'og:title', (string)($site['ogTitle'] ?? $title));
        $html = $this->upsertPropertyMeta($html, 'og:description', (string)($site['ogDescription'] ?? $description));
        $html = $this->upsertPropertyMeta($html, 'og:locale', (string)($site['locale'] ?? 'ru_RU'));
        $html = $this->upsertPropertyMeta($html, 'og:url', $canonical);
        $html = $this->upsertPropertyMeta($html, 'og:image', $ogImage);

        $html = $this->upsertMeta($html, 'twitter:card', (string)($site['twitterCard'] ?? 'summary_large_image'));
        $html = $this->upsertMeta($html, 'twitter:title', (string)($site['ogTitle'] ?? $title));
        $html = $this->upsertMeta($html, 'twitter:description', (string)($site['ogDescription'] ?? $description));
        $html = $this->upsertMeta($html, 'twitter:image', $ogImage);
        $html = $this->upsertMeta($html, 'twitter:site', (string)($site['twitterSite'] ?? ''));
        $html = $this->upsertMeta($html, 'twitter:creator', (string)($site['twitterCreator'] ?? ''));

        $html = $this->upsertMeta($html, 'google-site-verification', (string)($site['googleVerification'] ?? ''));
        $html = $this->upsertMeta($html, 'yandex-verification', (string)($site['yandexVerification'] ?? ''));
        $html = $this->upsertMeta($html, 'msvalidate.01', (string)($site['bingVerification'] ?? ''));

        return $this->upsertStructuredData($html, [
            '@context' => 'https://schema.org',
            '@type' => 'WebSite',
            'name' => $siteTitle,
            'alternateName' => $title,
            'description' => $description,
            'inLanguage' => (string)($site['lang'] ?? 'ru'),
            'url' => $canonical,
            'image' => $ogImage,
        ]);
    }

    private function bootstrapPayload(): array
    {
        $site = is_array($this->config['siteSettings'] ?? null) ? $this->config['siteSettings'] : [];
        $other = is_array($this->config['other'] ?? null) ? $this->config['other'] : [];
        $safeUser = [];
        foreach (self::USER_FIELDS as $field) {
            if (array_key_exists($field, $this->user)) {
                $safeUser[$field] = $field === 'balance'
                    ? BalanceMatrix::normalize($this->user[$field])
                    : $this->user[$field];
            }
        }

        $csrfToken = CsrfToken::issue();
        $replaceData = array_merge($safeUser, [
            'csrfToken' => $csrfToken,
            'template' => (string)$this->theme['name'],
            'assets' => (string)$this->theme['publicBase'] . 'assets/',
            'siteTitle' => (string)($site['siteTitle'] ?? 'FoxesCraft'),
            'siteStatus' => (string)($site['siteStatus'] ?? ''),
            'siteDesc' => (string)($site['siteDesc'] ?? ''),
            'serviceVersion' => (string)($site['ServiceVersion'] ?? ''),
            'discordLink' => (string)($site['discordLink'] ?? ''),
            'telegramLink' => (string)($site['telegramLink'] ?? ''),
            'githubLink' => (string)($site['githubLink'] ?? ''),
            'youtubeLink' => (string)($site['youtubeLink'] ?? ''),
            'vkLink' => (string)($other['vkLink'] ?? ''),
        ]);

        return [
            'engine' => [
                'version' => (string)($site['ServiceVersion'] ?? ''),
                'csrfToken' => $csrfToken,
                'endpoints' => $this->frontend['endpoints'] ?? [],
            ],
            'theme' => [
                'name' => (string)$this->theme['name'],
                'assets' => (string)$this->theme['publicBase'] . 'assets/',
                'mount' => (string)$this->theme['mount'],
                'settings' => $this->theme['settings'] ?? [],
            ],
            'site' => [
                'title' => (string)($site['siteTitle'] ?? 'FoxesCraft'),
                'homeTitle' => (string)($site['homeTitle'] ?? $site['siteTitle'] ?? 'FoxesCraft'),
                'titleTemplate' => (string)($site['titleTemplate'] ?? '%page% — %site%'),
                'status' => (string)($site['siteStatus'] ?? ''),
                'description' => (string)($site['siteDesc'] ?? ''),
                'keywords' => (string)($site['keywords'] ?? ''),
                'robots' => (string)($site['robots'] ?? 'index,follow'),
                'canonicalUrl' => (string)($site['canonicalUrl'] ?? ''),
                'language' => (string)($site['lang'] ?? 'ru'),
                'locale' => (string)($site['locale'] ?? 'ru_RU'),
                'themeColor' => (string)($site['themeColor'] ?? '#152019'),
                'ogImage' => (string)($site['ogImage'] ?? ''),
            ],
            'user' => $safeUser,
            'frontend' => $this->frontend,
            'replaceData' => $replaceData,
            'userFields' => array_values(array_keys($replaceData)),
        ];
    }

    private function upsertMeta(string $html, string $name, string $content): string
    {
        $pattern = '#<meta\b(?=[^>]*\bname=["\']' . preg_quote($name, '#') . '["\'])[^>]*>#i';
        return $this->upsertHeadTag(
            $html,
            $pattern,
            $content === '' ? '' : '<meta name="' . $this->escape($name) . '" content="' . $this->escape($content) . '">',
        );
    }

    private function upsertPropertyMeta(string $html, string $property, string $content): string
    {
        $pattern = '#<meta\b(?=[^>]*\bproperty=["\']' . preg_quote($property, '#') . '["\'])[^>]*>#i';
        return $this->upsertHeadTag(
            $html,
            $pattern,
            $content === '' ? '' : '<meta property="' . $this->escape($property) . '" content="' . $this->escape($content) . '">',
        );
    }

    private function upsertLink(string $html, string $rel, string $href): string
    {
        $pattern = '#<link\b(?=[^>]*\brel=["\']' . preg_quote($rel, '#') . '["\'])[^>]*>#i';
        return $this->upsertHeadTag(
            $html,
            $pattern,
            $href === '' ? '' : '<link rel="' . $this->escape($rel) . '" href="' . $this->escape($href) . '">',
        );
    }

    private function upsertHeadTag(string $html, string $pattern, string $tag): string
    {
        if (preg_match($pattern, $html) === 1) {
            return preg_replace($pattern, $tag, $html, 1) ?? $html;
        }
        if ($tag === '') {
            return $html;
        }
        return str_replace('</head>', '  ' . $tag . "\n</head>", $html);
    }

    private function upsertStructuredData(string $html, array $data): string
    {
        $data = array_filter($data, static fn(mixed $value): bool => $value !== '');
        $json = json_encode(
            $data,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_THROW_ON_ERROR,
        );
        $tag = '<script id="foxescraft-seo-schema" type="application/ld+json">' . $json . '</script>';
        $pattern = '#<script\s+id=["\']foxescraft-seo-schema["\'][^>]*>.*?</script>#is';
        return $this->upsertHeadTag($html, $pattern, $tag);
    }

    private function setHtmlAttribute(string $html, string $name, string $value): string
    {
        $value = $this->escape($value);
        $attributePattern = '#(<html\b[^>]*?)\s+' . preg_quote($name, '#') . '=["\'][^"\']*["\']#i';
        if (preg_match($attributePattern, $html) === 1) {
            return preg_replace($attributePattern, '$1 ' . $name . '="' . $value . '"', $html, 1) ?? $html;
        }
        return preg_replace('#<html\b#i', '<html ' . $name . '="' . $value . '"', $html, 1) ?? $html;
    }

    private function absoluteAssetUrl(string $value, string $canonical): string
    {
        $value = trim($value);
        if ($value === '' || !str_starts_with($value, '/')) {
            return $value;
        }
        $parts = parse_url($canonical);
        if (!is_array($parts) || !isset($parts['scheme'], $parts['host'])) {
            return $value;
        }
        $origin = $parts['scheme'] . '://' . $parts['host'];
        if (isset($parts['port'])) {
            $origin .= ':' . $parts['port'];
        }
        return $origin . $value;
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
