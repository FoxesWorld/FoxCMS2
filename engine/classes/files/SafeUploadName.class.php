<?php

declare(strict_types=1);

final class SafeUploadName
{
    private const BLOCKED_FILENAMES = [
        '.htaccess',
        '.user.ini',
        'web.config',
    ];

    private const BLOCKED_EXTENSIONS = [
        'cgi', 'fcgi', 'phar', 'php', 'php3', 'php4', 'php5', 'php7', 'php8',
        'pht', 'phtml', 'pl', 'py', 'rb', 'shtm', 'shtml',
    ];

    public static function validate(string $value): string
    {
        $name = trim($value);
        $stem = strtoupper(pathinfo($name, PATHINFO_FILENAME));
        $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $normalizedName = strtolower($name);
        $reservedWindowsName = preg_match('/^(?:CON|PRN|AUX|NUL|COM[1-9]|LPT[1-9])$/D', $stem) === 1;

        if ($name === '' || $name === '.' || $name === '..' || str_starts_with($name, '.')
            || rtrim($name, ". ") !== $name
            || str_contains($name, '/') || str_contains($name, '\\') || str_contains($name, "\0")
            || preg_match('/[<>:"|?*\x00-\x1f\x7f]/u', $name) === 1
            || $reservedWindowsName || mb_strlen($name) > 180
            || in_array($normalizedName, self::BLOCKED_FILENAMES, true)
            || in_array($extension, self::BLOCKED_EXTENSIONS, true)) {
            throw new InvalidArgumentException('Unsafe upload file or directory name.');
        }
        return $name;
    }
}
