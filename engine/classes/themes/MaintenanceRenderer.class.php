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
        $siteTitle = $this->escape((string)($site['siteTitle'] ?? 'FoxesCraft'));
        $title = $this->escape((string)($this->settings['title'] ?? 'Ведутся технические работы'));
        $message = $this->escape((string)($this->settings['message'] ?? 'Сайт временно недоступен.'));
        $csrf = $this->escape(CsrfToken::issue());
        $login = $this->escape($this->session->login());
        $groupName = $this->escape((string)$this->session->get('groupName', 'Группа ' . $this->session->group()));
        $form = $this->session->isLogged()
            ? $this->logoutForm($csrf, $login, $groupName)
            : $this->loginForm($csrf);

        echo '<!doctype html><html lang="ru"><head>'
            . '<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
            . '<meta name="theme-color" content="#201b18">'
            . '<title>' . $title . ' — ' . $siteTitle . '</title>'
            . '<link rel="stylesheet" href="' . $assetBase . 'css/maintenance.css">'
            . '</head><body>'
            . '<main class="maintenance-shell">'
            . '<section class="maintenance-card" aria-labelledby="maintenance-title">'
            . '<div class="maintenance-brand"><span class="maintenance-brand__mark">FC</span><span>'
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
        return '<form class="maintenance-access" data-maintenance-form>'
            . '<input type="hidden" name="userAction" value="auth">'
            . '<input type="hidden" name="csrf_token" value="' . $csrf . '">'
            . '<div class="maintenance-access__heading"><strong>Доступ для разрешённых групп</strong>'
            . '<span>Войдите в аккаунт, чтобы сервер проверил вашу группу.</span></div>'
            . '<label><span>Логин</span><input name="login" autocomplete="username" required maxlength="64"></label>'
            . '<label><span>Пароль</span><input type="password" name="password" autocomplete="current-password" required></label>'
            . '<label class="maintenance-check"><input type="checkbox" name="rememberMe" value="1" checked><span>Запомнить вход</span></label>'
            . '<button type="submit">Проверить доступ</button>'
            . '<p class="maintenance-feedback" data-maintenance-feedback role="status" aria-live="polite"></p>'
            . '</form>';
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

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
