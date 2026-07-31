<?php

declare(strict_types=1);

final class ThemeRenderer
{
    private const USER_FIELDS = [
        'isLogged', 'uuid', 'user_id', 'login', 'realname', 'groupTag', 'profilePhoto',
        'userStatus', 'land', 'colorScheme', 'email', 'groupName', 'groupColor',
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
        $title = $this->escape((string)($site['siteTitle'] ?? 'FoxesCraft'));
        $description = $this->escape((string)($site['siteDesc'] ?? 'FoxesCraft'));
        $html = preg_replace('#<title>.*?</title>#s', '<title>' . $title . '</title>', $html, 1) ?? $html;
        if (!str_contains($html, 'name="description"')) {
            $html = str_replace('</head>', '  <meta name="description" content="' . $description . '">' . "\n</head>", $html);
        }
        $html = preg_replace(
            '#<html\b#',
            '<html data-theme="' . $this->escape((string)$this->theme['name']) . '"',
            $html,
            1
        ) ?? $html;

        header('Content-Type: text/html; charset=UTF-8');
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Cache-Control: no-store, max-age=0');
        echo $html;
    }

    private function bootstrapPayload(): array
    {
        $site = is_array($this->config['siteSettings'] ?? null) ? $this->config['siteSettings'] : [];
        $other = is_array($this->config['other'] ?? null) ? $this->config['other'] : [];
        $safeUser = [];
        foreach (self::USER_FIELDS as $field) {
            if (array_key_exists($field, $this->user)) {
                $safeUser[$field] = $this->user[$field];
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
            'discordLink' => (string)($other['discordLink'] ?? ''),
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
                'status' => (string)($site['siteStatus'] ?? ''),
                'description' => (string)($site['siteDesc'] ?? ''),
            ],
            'user' => $safeUser,
            'frontend' => $this->frontend,
            'replaceData' => $replaceData,
            'userFields' => array_values(array_keys($replaceData)),
        ];
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
