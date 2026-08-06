<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/autoload.php';

/**
 * Engine autoloader.
 *
 * New code follows PSR-4 under FoxCMS\\Engine. Legacy global classes remain
 * available through a filename class map so existing modules can be migrated
 * incrementally instead of through a high-risk flag-day rewrite.
 */
spl_autoload_register(static function (string $class): void {
    if (str_contains($class, '\\')) {
        return;
    }

    static $legacyClassMap = null;
    if ($legacyClassMap === null) {
        $legacyClassMap = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(__DIR__, FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $entry) {
            if (!$entry->isFile() || !str_ends_with($entry->getFilename(), '.class.php')) {
                continue;
            }
            $name = substr($entry->getFilename(), 0, -strlen('.class.php'));
            $legacyClassMap[strtolower($name)] ??= $entry->getPathname();
        }

        $aliases = [
            'AuthManager' => __DIR__ . '/classes/modules/AuthReg/AuthReg.class.php',
            'AuthLibSession' => __DIR__ . '/classes/modules/AuthReg/AuthReg.class.php',
            'FoxesMon' => __DIR__ . '/classes/modules/Monitoring/Monitoring.class.php',
            'NewsModule' => __DIR__ . '/classes/modules/News/News.class.php',
            'User' => __DIR__ . '/classes/modules/UserSettings/UserSettings.class.php',
            'PlayTimeService' => __DIR__ . '/classes/services/PlayTimeService.php',
            'Authorise' => __DIR__ . '/classes/modules/AuthReg/actions/authorise.class.php',
            'LastUser' => __DIR__ . '/classes/modules/AuthReg/actions/lastUser.class.php',
            'Register' => __DIR__ . '/classes/modules/AuthReg/actions/register.class.php',
            'LostPassword' => __DIR__ . '/classes/modules/UserSettings/actions/lostpassword.class.php',
            'ResetPassword' => __DIR__ . '/classes/modules/UserSettings/actions/resetpassword.class.php',
            'UpdateProfilePhoto' => __DIR__ . '/classes/modules/UserSettings/actions/updateProfilePhoto.class.php',
        ];
        foreach ($aliases as $name => $path) {
            $legacyClassMap[strtolower($name)] = $path;
        }
    }

    $file = $legacyClassMap[strtolower($class)] ?? null;
    if (is_string($file) && is_file($file)) {
        require_once $file;
    }
});
