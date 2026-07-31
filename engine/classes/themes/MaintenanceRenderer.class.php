<?php

declare(strict_types=1);

final class MaintenanceRenderer
{
    public function __construct(
        private array $config,
        private array $settings,
        private UserSession $session,
    ) {
    }

    public function render(): never
    {
        http_response_code(503);
        header('Content-Type: text/html; charset=UTF-8');
        header('Cache-Control: no-store, max-age=0');
        header('Retry-After: 300');
        header('X-Robots-Tag: noindex, nofollow');

        $site = is_array($this->config['siteSettings'] ?? null) ? $this->config['siteSettings'] : [];
        $template = preg_replace('/[^A-Za-z0-9_-]/', '', (string)($site['siteTpl'] ?? 'foxengine2')) ?: 'foxengine2';
        $assetBase = '/templates/' . rawurlencode($template) . '/assets/';
        $season = $this->currentSeason();
        $seasonAsset = $assetBase . 'img/season/' . $this->seasonFile($season);
        $logoAsset = $assetBase . 'img/logo.png';
        $siteTitle = $this->escape((string)($site['siteTitle'] ?? 'FoxesCraft'));
        $title = $this->escape((string)($this->settings['title'] ?? 'Ведутся технические работы'));
        $message = $this->escape((string)($this->settings['message'] ?? 'Сайт временно недоступен.'));
        $csrf = $this->escape(CsrfToken::issue());
        $login = $this->escape($this->session->login());
        $groupName = $this->escape((string)$this->session->get('groupName', 'Тег ' . $this->session->group()));
        $form = $this->session->isLogged()
            ? $this->logoutForm($csrf, $login, $groupName)
            : $this->loginForm($csrf);

        echo '<!doctype html><html lang="ru"><head>'
            . '<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
            . '<meta name="theme-color" content="#201b18">'
            . '<link rel="icon" type="image/png" href="' . $this->escape($logoAsset) . '">'
            . '<title>' . $title . ' — ' . $siteTitle . '</title>'
            . ($seasonAsset !== '' ? '<link rel="preload" as="image" href="' . $this->escape($seasonAsset) . '">' : '')
            . '<link rel="stylesheet" href="' . $assetBase . 'css/maintenance.css">'
            . '</head><body data-season="' . $this->escape($season) . '">'
            . '<main class="maintenance-shell">'
            . '<section class="maintenance-card" aria-labelledby="maintenance-title">'
            . '<div class="maintenance-brand"><img class="maintenance-brand__logo" src="'
                . $this->escape($logoAsset) . '" alt="" width="96" height="72"><span>'
            . '<strong>' . $siteTitle . '</strong><small>Maintenance protocol</small></span></div>'
            . '<div class="maintenance-indicator"><i></i><span>Технические работы активны</span></div>'
            . '<h1 id="maintenance-title">' . $title . '</h1>'
            . '<p class="maintenance-message">' . $message . '</p>'
            . '<div class="maintenance-progress" aria-hidden="true"><span></span></div>'
            . $form
            . '<footer>Сервер вернул HTTP 503. Повторная проверка доступности выполняется после обновления страницы.</footer>'
            . '</section></main>'
            . '<script type="module" src="' . $assetBase . 'maintenance.js"></script>'
            . '</body></html>';
        exit;
    }

    private function loginForm(string $csrf): string
    {
        return '<details class="maintenance-admin-access">'
            . '<summary><span><strong>Вы администратор?</strong>'
            . '<small>Войдите, чтобы продолжить работу с сайтом и панелью управления.</small></span>'
            . '<i aria-hidden="true">+</i></summary>'
            . '<form class="maintenance-access" data-maintenance-form>'
            . '<input type="hidden" name="userAction" value="auth">'
            . '<input type="hidden" name="maintenanceAdmin" value="1">'
            . '<input type="hidden" name="csrf_token" value="' . $csrf . '">'
            . '<div class="maintenance-access__heading"><strong>Вход администратора</strong>'
            . '<span>Доступ будет предоставлен только учётной записи с административными правами.</span></div>'
            . '<label><span>Логин</span><input name="login" autocomplete="username" required maxlength="64"></label>'
            . '<label><span>Пароль</span><input type="password" name="password" autocomplete="current-password" required></label>'
            . '<button type="submit">Войти как администратор</button>'
            . '<p class="maintenance-feedback" data-maintenance-feedback role="status" aria-live="polite"></p>'
            . '</form></details>';
    }

    private function logoutForm(string $csrf, string $login, string $groupName): string
    {
        return '<form class="maintenance-access" data-maintenance-form>'
            . '<input type="hidden" name="userAction" value="logout">'
            . '<input type="hidden" name="csrf_token" value="' . $csrf . '">'
            . '<div class="maintenance-access__heading"><strong>Доступ для этой группы закрыт</strong>'
            . '<span>Аккаунт ' . $login . ' · ' . $groupName . '</span></div>'
            . '<button type="submit" class="maintenance-access__secondary">Выйти из аккаунта</button>'
            . '<p class="maintenance-feedback" data-maintenance-feedback role="status" aria-live="polite"></p>'
            . '</form>';
    }

    private function currentSeason(): string
    {
        $month = (int)date('n');
        if ($month >= 3 && $month <= 5) {
            return 'spring';
        }
        if ($month >= 6 && $month <= 8) {
            return 'summer';
        }
        if ($month >= 9 && $month <= 11) {
            return 'autumn';
        }
        return 'winter';
    }

    private function seasonFile(string $season): string
    {
        return match ($season) {
            'spring' => 'spring.png',
            'summer' => 'summer.png',
            'autumn' => 'autumn.png',
            default => 'winter.jpg',
        };
    }

    private function escape(mixed $value): string
    {
        return htmlspecialchars((string)($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
