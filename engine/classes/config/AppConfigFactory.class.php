<?php

declare(strict_types=1);

final class AppConfigFactory
{
    /**
     * Build and validate the compatibility configuration array consumed by
     * current modules. Environment variables remain the only secret source.
     *
     * @return array<string, mixed>
     */
    public static function fromEnvironment(): array
    {
        $template = self::identifier(
            foxEnv('FOXESCRAFT_TEMPLATE', 'foxengine2') ?? 'foxengine2',
            'FOXESCRAFT_TEMPLATE',
        );
        $timezone = foxEnv('FOXESCRAFT_TIMEZONE', 'Europe/Amsterdam') ?? 'Europe/Amsterdam';
        try {
            new DateTimeZone($timezone);
        } catch (Throwable $exception) {
            throw new RuntimeException('Invalid FOXESCRAFT_TIMEZONE value.', 0, $exception);
        }

        $recaptchaPublicKey = trim(foxEnv('FOXESCRAFT_RECAPTCHA_PUBLIC_KEY', '') ?? '');
        $recaptchaSecretKey = trim(foxEnv('FOXESCRAFT_RECAPTCHA_SECRET_KEY', '') ?? '');
        $publicBaseUrl = rtrim(foxEnv('FOXESCRAFT_PUBLIC_BASE_URL', '') ?? '', '/');
        if ($publicBaseUrl !== '' && filter_var($publicBaseUrl, FILTER_VALIDATE_URL) === false) {
            throw new RuntimeException('FOXESCRAFT_PUBLIC_BASE_URL must be an absolute URL.');
        }

        $databaseName = trim(foxEnv('FOXESCRAFT_DB_NAME', 'foxescraft') ?? '');
        $databaseUser = trim(foxEnv('FOXESCRAFT_DB_USER', 'foxescraft') ?? '');
        if ($databaseName === '' || $databaseUser === '') {
            throw new RuntimeException('FOXESCRAFT_DB_NAME and FOXESCRAFT_DB_USER are required.');
        }

        $trustedProxies = array_values(array_filter(array_map(
            'trim',
            explode(',', foxEnv('FOXESCRAFT_TRUSTED_PROXIES', '') ?? ''),
        ), static fn (string $value): bool => $value !== ''));

        return [
            'environment' => [
                'name' => self::enum(
                    foxEnv('FOXESCRAFT_ENV', 'production') ?? 'production',
                    ['production', 'staging', 'development', 'testing'],
                    'FOXESCRAFT_ENV',
                ),
                'debug' => foxEnvBool('FOXESCRAFT_DEBUG', false),
                'publicBaseUrl' => $publicBaseUrl,
                'trustedProxies' => $trustedProxies,
            ],
            'observability' => [
                'slowRequestMilliseconds' => max(100, foxEnvInt('FOXESCRAFT_SLOW_REQUEST_MS', 2000)),
                'criticalRequestMilliseconds' => max(500, foxEnvInt('FOXESCRAFT_CRITICAL_REQUEST_MS', 5000)),
                'memoryWarningBytes' => max(8_388_608, foxEnvInt('FOXESCRAFT_MEMORY_WARNING_BYTES', 67_108_864)),
                'fingerprintKey' => trim(foxEnv('FOXESCRAFT_LOG_FINGERPRINT_KEY', '') ?? ''),
            ],
            'database' => [
                'dbHost' => foxEnv('FOXESCRAFT_DB_HOST', '127.0.0.1') ?? '127.0.0.1',
                'dbPort' => foxEnvInt('FOXESCRAFT_DB_PORT', 3306),
                'dbUser' => $databaseUser,
                'dbPass' => foxEnv('FOXESCRAFT_DB_PASSWORD', '') ?? '',
                'dbName' => $databaseName,
                'dbCharset' => 'utf8mb4',
                'connectTimeout' => max(1, min(30, foxEnvInt('FOXESCRAFT_DB_CONNECT_TIMEOUT', 5))),
            ],
            'siteSettings' => [
                'lang' => self::identifier(foxEnv('FOXESCRAFT_LANGUAGE', 'ru') ?? 'ru', 'FOXESCRAFT_LANGUAGE'),
                'siteTpl' => $template,
                'siteTitle' => foxEnv('FOXESCRAFT_SITE_TITLE', 'Лисий Мир 3.0') ?? 'Лисий Мир 3.0',
                'siteStatus' => foxEnv('FOXESCRAFT_SITE_STATUS', 'IN DEVELOPMENT') ?? 'IN DEVELOPMENT',
                'siteDesc' => foxEnv('FOXESCRAFT_SITE_DESCRIPTION', 'Независимая игровая студия') ?? '',
                'keywords' => foxEnv('FOXESCRAFT_SITE_KEYWORDS', 'FoxesCraft,FoxesWorld,Лисий Мир,GameDev,Minecraft') ?? '',
                'homeTitle' => foxEnv('FOXESCRAFT_HOME_TITLE', foxEnv('FOXESCRAFT_SITE_TITLE', 'Лисий Мир 3.0')) ?? 'Лисий Мир 3.0',
                'titleTemplate' => foxEnv('FOXESCRAFT_TITLE_TEMPLATE', '%page% — %site%') ?? '%page% — %site%',
                'contactEmail' => self::email(foxEnv('FOXESCRAFT_CONTACT_EMAIL', 'admin@localhost') ?? 'admin@localhost'),
                'robots' => foxEnv('FOXESCRAFT_ROBOTS', 'index,follow') ?? 'index,follow',
                'canonicalUrl' => foxEnv('FOXESCRAFT_CANONICAL_URL', $publicBaseUrl) ?? $publicBaseUrl,
                'locale' => foxEnv('FOXESCRAFT_LOCALE', 'ru_RU') ?? 'ru_RU',
                'author' => foxEnv('FOXESCRAFT_AUTHOR', 'FoxesCraft') ?? 'FoxesCraft',
                'themeColor' => foxEnv('FOXESCRAFT_THEME_COLOR', '#152019') ?? '#152019',
                'faviconUrl' => foxEnv('FOXESCRAFT_FAVICON_URL', '/favicon.ico') ?? '/favicon.ico',
                'ogSiteName' => foxEnv('FOXESCRAFT_OG_SITE_NAME', foxEnv('FOXESCRAFT_SITE_TITLE', 'Лисий Мир 3.0')) ?? 'Лисий Мир 3.0',
                'ogTitle' => foxEnv('FOXESCRAFT_OG_TITLE', foxEnv('FOXESCRAFT_SITE_TITLE', 'Лисий Мир 3.0')) ?? 'Лисий Мир 3.0',
                'ogDescription' => foxEnv('FOXESCRAFT_OG_DESCRIPTION', foxEnv('FOXESCRAFT_SITE_DESCRIPTION', 'Независимая игровая студия')) ?? '',
                'ogImage' => foxEnv('FOXESCRAFT_OG_IMAGE', '') ?? '',
                'twitterCard' => foxEnv('FOXESCRAFT_TWITTER_CARD', 'summary_large_image') ?? 'summary_large_image',
                'twitterSite' => foxEnv('FOXESCRAFT_TWITTER_SITE', '') ?? '',
                'twitterCreator' => foxEnv('FOXESCRAFT_TWITTER_CREATOR', '') ?? '',
                'discordLink' => foxEnv('FOXESCRAFT_DISCORD_LINK', '') ?? '',
                'telegramLink' => foxEnv('FOXESCRAFT_TELEGRAM_LINK', '') ?? '',
                'githubLink' => foxEnv('FOXESCRAFT_GITHUB_LINK', '') ?? '',
                'youtubeLink' => foxEnv('FOXESCRAFT_YOUTUBE_LINK', '') ?? '',
                'googleVerification' => foxEnv('FOXESCRAFT_GOOGLE_VERIFICATION', '') ?? '',
                'yandexVerification' => foxEnv('FOXESCRAFT_YANDEX_VERIFICATION', '') ?? '',
                'bingVerification' => foxEnv('FOXESCRAFT_BING_VERIFICATION', '') ?? '',
                'mailMethod' => 'smtp',
                'mailFromAddress' => '',
                'mailFromName' => 'FoxesCraft',
                'smtpHost' => 'smtp.mail.ru',
                'smtpPort' => '465',
                'smtpSecurity' => 'ssl',
                'smtpUsername' => '',
                'smtpPassword' => '',
                'contactPhone' => foxEnv('FOXESCRAFT_CONTACT_PHONE', '') ?? '',
                'ServiceVersion' => foxEnv('FOXESCRAFT_SERVICE_VERSION', '3.0.0-dev') ?? '3.0.0-dev',
            ],
            'launcherSettings' => [
                'gameFiles' => self::relativeDirectory(foxEnv('FOXESCRAFT_GAME_FILES_DIR', 'game/') ?? 'game/'),
                'serverPictures' => 'assets/img/servers/',
                'jreDir' => self::relativeDirectory(foxEnv('FOXESCRAFT_JRE_DIR', 'files/runtime/') ?? 'files/runtime/'),
                'sessionSeconds' => max(300, foxEnvInt('FOXESCRAFT_LAUNCHER_SESSION_SECONDS', 900)),
            ],
            'securitySetings' => [
                'reCaptchaCheck' => foxEnvBool('FOXESCRAFT_RECAPTCHA_ENABLED', false)
                    && $recaptchaPublicKey !== ''
                    && $recaptchaSecretKey !== '',
                'reCaptchaSecret' => $recaptchaSecretKey,
                'reCaptchaWebsite' => $recaptchaPublicKey,
                'bantime' => (string)max(1, foxEnvInt('FOXESCRAFT_AUTH_BAN_MINUTES', 20)),
                'maxLoginAttempts' => (string)max(1, foxEnvInt('FOXESCRAFT_AUTH_MAX_ATTEMPTS', 5)),
                'attemptWindowSeconds' => max(60, foxEnvInt('FOXESCRAFT_AUTH_ATTEMPT_WINDOW_SECONDS', 900)),
                'allowedMime' => 'image/jpeg,image/png,image/gif,image/webp',
                'sessionIdleSeconds' => max(300, foxEnvInt('FOXESCRAFT_SESSION_IDLE_SECONDS', 7200)),
                'sessionAbsoluteSeconds' => max(900, foxEnvInt('FOXESCRAFT_SESSION_ABSOLUTE_SECONDS', 86400)),
                'rememberSeconds' => max(3600, foxEnvInt('FOXESCRAFT_REMEMBER_SECONDS', 31536000)),
            ],
            'monitor' => [
                'dayRecordPath' => foxEnv('FOXESCRAFT_MONITOR_DAY_RECORD', ROOT_DIR . '/engine/cache/tmp/record_day.log'),
                'absoluteRecordPath' => foxEnv('FOXESCRAFT_MONITOR_RECORD', ROOT_DIR . '/engine/cache/tmp/record.log'),
                'tempFilePath' => foxEnv('FOXESCRAFT_MONITOR_TEMP', ROOT_DIR . '/engine/cache/tmp/timefile.log'),
            ],
            'register' => [
                'passminCount' => (string)max(8, foxEnvInt('FOXESCRAFT_PASSWORD_MIN_LENGTH', 10)),
                'maxLoginLength' => (string)max(3, min(64, foxEnvInt('FOXESCRAFT_LOGIN_MAX_LENGTH', 64))),
                'baseUserGroupTag' => self::identifier(foxEnv('FOXESCRAFT_DEFAULT_USER_GROUP_TAG', 'user') ?? 'user', 'FOXESCRAFT_DEFAULT_USER_GROUP_TAG'),
            ],
            'other' => [
                'appId' => foxEnv('FOXESCRAFT_DISCORD_APP_ID', '') ?? '',
                'accessToken' => foxEnv('FOXESCRAFT_API_ACCESS_TOKEN', '') ?? '',
                'healthToken' => foxEnv('FOXESCRAFT_HEALTH_TOKEN', '') ?? '',
                'vkLink' => foxEnv('FOXESCRAFT_VK_LINK', '') ?? '',
                'timezone' => $timezone,
                'webserviceName' => 'FoxesCraft',
                'userOptions' => 'userOptions',
                'OptionReplaceValues' => '',
                'userFieldsArray' => 'user_id,email,login,groupTag,realname,reg_date,last_date,logged_ip,profilePhoto,userStatus,land,colorScheme,groupName,badges,balance,serversOnline,groupColor,userPerms',
            ],
        ];
    }

    private static function enum(string $value, array $allowed, string $name): string
    {
        if (!in_array($value, $allowed, true)) {
            throw new RuntimeException($name . ' contains an unsupported value.');
        }
        return $value;
    }

    private static function identifier(string $value, string $name): string
    {
        if (preg_match('/^[A-Za-z0-9][A-Za-z0-9_-]{0,63}$/', $value) !== 1) {
            throw new RuntimeException($name . ' must be a safe identifier.');
        }
        return $value;
    }

    private static function email(string $value): string
    {
        if ($value === 'admin@localhost' || filter_var($value, FILTER_VALIDATE_EMAIL) !== false) {
            return $value;
        }
        throw new RuntimeException('FOXESCRAFT_CONTACT_EMAIL is invalid.');
    }

    private static function relativeDirectory(string $value): string
    {
        $value = str_replace('\\', '/', trim($value));
        if ($value === '' || str_starts_with($value, '/') || preg_match('#(?:^|/)\.\.(?:/|$)#', $value) === 1) {
            throw new RuntimeException('Launcher directories must be safe relative paths.');
        }
        return rtrim($value, '/') . '/';
    }
}
