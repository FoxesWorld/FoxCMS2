<?php

declare(strict_types=1);

final class SecurityHeaders
{
    public static function apply(NetworkContext $network, bool $development = false): void
    {
        if (headers_sent()) {
            return;
        }

        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Cross-Origin-Opener-Policy: same-origin');
        header('Cross-Origin-Resource-Policy: same-origin');
        header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=(), usb=()');
        header('X-Permitted-Cross-Domain-Policies: none');

        $hcaptchaSources = ['https://hcaptcha.com', 'https://*.hcaptcha.com'];
        $connectSources = array_merge(["'self'"], $hcaptchaSources);
        if ($development) {
            $connectSources[] = 'ws:';
            $connectSources[] = 'wss:';
        }

        $policy = [
            "default-src 'self'",
            "base-uri 'self'",
            "frame-ancestors 'none'",
            "form-action 'self'",
            "object-src 'none'",
            "script-src 'self' " . implode(' ', $hcaptchaSources),
            "style-src 'self' " . implode(' ', $hcaptchaSources),
            'frame-src ' . implode(' ', $hcaptchaSources),
            "img-src 'self' data: blob:",
            "font-src 'self' data:",
            "media-src 'self'",
            'connect-src ' . implode(' ', $connectSources),
            "manifest-src 'self'",
            "worker-src 'self' blob:",
            'upgrade-insecure-requests',
        ];
        header('Content-Security-Policy: ' . implode('; ', $policy));

        if ($network->isSecure()) {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        }
    }
}
