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
                'contactEmail' => self::email(foxEnv('FOXESCRAFT_CONTACT_EMAIL', 'admin@localhost') ?? 'admin@localhost'),
                'smtp_pass' => foxEnv('FOXESCRAFT_SMTP_PASSWORD', '') ?? '',
                'admin_mail' => foxEnv('FOXESCRAFT_SMTP_USERNAME', '') ?? '',
                'mail_title' => foxEnv('FOXESCRAFT_MAIL_TITLE', 'FoxesCraft') ?? 'FoxesCraft',
                'mail_metod' => self::enum(
                    foxEnv('FOXESCRAFT_MAIL_METHOD', 'smtp') ?? 'smtp',
                    ['smtp', 'mail'],
                    'FOXESCRAFT_MAIL_METHOD',
                ),
                'smtp_host' => foxEnv('FOXESCRAFT_SMTP_HOST', 'localhost') ?? 'localhost',
                'smtp_port' => (string)max(1, min(65535, foxEnvInt('FOXESCRAFT_SMTP_PORT', 465))),
                'smtp_secure' => self::enum(
                    foxEnv('FOXESCRAFT_SMTP_SECURITY', 'ssl') ?? 'ssl',
                    ['', 'ssl', 'tls'],
                    'FOXESCRAFT_SMTP_SECURITY',
                ),
                'contactPhone' => foxEnv('FOXESCRAFT_CONTACT_PHONE', '') ?? '',
                'ServiceVersion' => foxEnv('FOXESCRAFT_SERVICE_VERSION', '3.0.0-dev') ?? '3.0.0-dev',
            ],
            'launcherSettings' => [
                'gameFiles' => self::relativeDirectory(foxEnv('FOXESCRAFT_GAME_FILES_DIR', 'files/clients/') ?? 'files/clients/'),
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
                'baseUserGroup' => (string)max(1, foxEnvInt('FOXESCRAFT_DEFAULT_USER_GROUP', 4)),
            ],
            'other' => [
                'appId' => foxEnv('FOXESCRAFT_DISCORD_APP_ID', '') ?? '',
                'accessToken' => foxEnv('FOXESCRAFT_API_ACCESS_TOKEN', '') ?? '',
                'healthToken' => foxEnv('FOXESCRAFT_HEALTH_TOKEN', '') ?? '',
                'discordLink' => foxEnv('FOXESCRAFT_DISCORD_LINK', '') ?? '',
                'vkLink' => foxEnv('FOXESCRAFT_VK_LINK', '') ?? '',
                'timezone' => $timezone,
                'webserviceName' => 'FoxesCraft',
                'userOptions' => 'userOptions',
                'OptionReplaceValues' => '',
                'userFieldsArray' => 'user_id,email,login,user_group,realname,reg_date,last_date,logged_ip,profilePhoto,userStatus,land,colorScheme,groupName,badges,balance,serversOnline,groupColor,userPerms',
                'canEditGroup' => '1,4,3,6',
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
