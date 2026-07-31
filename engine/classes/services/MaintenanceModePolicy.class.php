<?php

declare(strict_types=1);

final class MaintenanceModePolicy
{
    public static function isEnabled(array $settings): bool
    {
        return ($settings['enabled'] ?? false) === true;
    }

    public static function allows(array $settings, UserSession $session): bool
    {
        if (!self::isEnabled($settings) || $session->isAdmin()) {
            return true;
        }
        $groups = is_array($settings['allowedGroups'] ?? null)
            ? array_map('intval', $settings['allowedGroups'])
            : [];
        return in_array($session->group(), $groups, true);
    }

    public static function authActionAllowed(string $action): bool
    {
        return in_array($action, ['', 'auth', 'logout', 'lastUser'], true);
    }
}
