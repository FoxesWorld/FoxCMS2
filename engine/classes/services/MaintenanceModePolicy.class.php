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
            ? array_values(array_filter(array_map(
                static fn (mixed $group): string => GroupRepository::normalizeTag($group, ''),
                $settings['allowedGroups'],
            )))
            : [];
        return in_array($session->group(), $groups, true);
    }

    public static function authActionAllowed(string $action): bool
    {
        return in_array($action, ['', 'auth', 'logout', 'lastUser'], true);
    }
}
