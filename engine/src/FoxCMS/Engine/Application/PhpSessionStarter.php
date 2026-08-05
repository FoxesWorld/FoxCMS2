<?php

declare(strict_types=1);

namespace FoxCMS\Engine\Application;

final class PhpSessionStarter
{
    public function start(array $config, \NetworkContext $network): void
    {
        if (session_status() !== PHP_SESSION_NONE) {
            return;
        }

        $security = is_array($config['securitySetings'] ?? null)
            ? $config['securitySetings']
            : [];
        $absoluteLifetime = max(900, (int)($security['sessionAbsoluteSeconds'] ?? 86400));

        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.cookie_httponly', '1');
        ini_set('session.cookie_samesite', 'Lax');
        ini_set('session.gc_maxlifetime', (string)$absoluteLifetime);
        ini_set('session.sid_length', '48');
        ini_set('session.sid_bits_per_character', '6');

        session_name('FOXESCRAFTSESSID');
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => $network->isSecure(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_cache_limiter('nocache');
        if (!session_start()) {
            throw new \RuntimeException('Unable to start the FoxCMS session.');
        }
    }
}
