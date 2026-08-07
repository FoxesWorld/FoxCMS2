<?php

declare(strict_types=1);

namespace FoxCMS\Api\User;

use JsonException;
use stdClass;

final class UserProfilePresenter
{
    private const DEFAULT_ACCENT_COLOR = '#b5b8b1';
    private const DEFAULT_GROUP_COLOR = '#ffffff';

    public function __construct(private readonly string $publicBaseUrl)
    {
    }

    /**
     * Convert the persistence record into the stable, public API contract.
     *
     * @param array<string, mixed> $profile
     * @return array<string, mixed>
     */
    public function present(array $profile): array
    {
        $login = self::text($profile['login'] ?? null) ?? '';
        $fullName = self::text($profile['realname'] ?? null);
        $photoSource = $this->photoSource($profile['profilePhoto'] ?? null);

        return [
            'uuid' => \Uuid::canonical((string)($profile['uuid'] ?? '')),
            'login' => $login,
            'fullName' => $fullName,
            'displayName' => $fullName ?? $login,
            'status' => self::text($profile['userStatus'] ?? null),
            'location' => self::text($profile['land'] ?? null),
            'colorScheme' => self::color(
                $profile['colorScheme'] ?? null,
                self::DEFAULT_ACCENT_COLOR,
            ),
            'profilePhoto' => $this->absolutePhotoUrl($photoSource),
            'profilePhotoPath' => $photoSource !== null && str_starts_with($photoSource, '/')
                ? $photoSource
                : null,
            'registeredAt' => self::timestamp($profile['reg_date'] ?? null),
            'lastSeenAt' => self::timestamp($profile['last_date'] ?? null),
            'group' => [
                'tag' => self::text($profile['groupTag'] ?? null) ?? 'user',
                'name' => self::text($profile['groupName'] ?? null),
                'color' => self::color(
                    $profile['groupColor'] ?? null,
                    self::DEFAULT_GROUP_COLOR,
                ),
            ],
            'badges' => self::jsonDocument($profile['badges'] ?? null, false),
            'serversOnline' => self::jsonDocument($profile['serversOnline'] ?? null, true),
        ];
    }

    private static function text(mixed $value): ?string
    {
        if (!is_scalar($value)) {
            return null;
        }

        $value = trim((string)$value);
        return $value === '' ? null : $value;
    }

    private static function color(mixed $value, string $fallback): string
    {
        $value = self::text($value);
        return $value !== null && preg_match('/^#[0-9a-f]{6}$/iD', $value) === 1
            ? strtolower($value)
            : $fallback;
    }

    private static function timestamp(mixed $value): ?int
    {
        if (!is_int($value) && !(is_string($value) && preg_match('/^[0-9]+$/D', $value) === 1)) {
            return null;
        }

        $timestamp = (int)$value;
        return $timestamp > 0 ? $timestamp : null;
    }

    private static function jsonDocument(mixed $value, bool $emptyObject): mixed
    {
        if (is_array($value) || is_object($value)) {
            return $value;
        }
        if (!is_string($value) || trim($value) === '') {
            return $emptyObject ? new stdClass() : [];
        }

        $source = trim($value);
        try {
            $decoded = json_decode($source, true, 128, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return $emptyObject ? new stdClass() : [];
        }
        if (!is_array($decoded)) {
            return $emptyObject ? new stdClass() : [];
        }
        if ($decoded === [] && str_starts_with($source, '{')) {
            return new stdClass();
        }

        return $decoded;
    }

    private function photoSource(mixed $value): ?string
    {
        $source = self::text($value);
        if ($source === null || str_contains($source, "\0") || str_contains($source, '\\')) {
            return null;
        }

        if (str_starts_with($source, '/')) {
            if (str_starts_with($source, '//')
                || preg_match('#(?:^|/)\.\.(?:/|$)#', $source) === 1) {
                return null;
            }
            return $source;
        }

        $url = filter_var($source, FILTER_VALIDATE_URL);
        $scheme = is_string($url) ? strtolower((string)parse_url($url, PHP_URL_SCHEME)) : '';
        return in_array($scheme, ['http', 'https'], true) ? $url : null;
    }

    private function absolutePhotoUrl(?string $source): ?string
    {
        if ($source === null || !str_starts_with($source, '/')) {
            return $source;
        }

        $baseUrl = rtrim(trim($this->publicBaseUrl), '/');
        return $baseUrl === '' ? $source : $baseUrl . $source;
    }
}
