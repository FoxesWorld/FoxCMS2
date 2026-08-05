<?php

declare(strict_types=1);

namespace FoxCMS\Engine\Application;

final class FrontendResponder
{
    public function render(ApplicationContext $context): void
    {
        $theme = (new \ThemeResolver(TEMPLATE_DIR))->resolve(
            (string)($context->config['siteSettings']['siteTpl'] ?? '')
        );
        $frontend = new \FrontendRegistry(
            $context->session,
            (string)$theme['frontend'],
            ENGINE_DIR . 'data/modules.json',
        );
        $themeUser = $context->session->all();

        if ($context->session->isLogged()) {
            try {
                $themeUser['notificationsUnread'] = (new \NotificationService($context->db))
                    ->countUnread($context->session->uuid());
            } catch (\Throwable $error) {
                $themeUser['notificationsUnread'] = 0;
                $context->logger->exception(
                    'notifications.bootstrap.failed',
                    $error,
                    'Unread notification count could not be added to the frontend bootstrap.',
                    ['component' => 'notifications', 'operation' => 'bootstrap_count'],
                );
            }
        }

        (new \ThemeRenderer(
            $context->config,
            $themeUser,
            $theme,
            $frontend->manifest(),
        ))->render();
    }
}
