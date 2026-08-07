<?php

declare(strict_types=1);

namespace FoxCMS\Api\Badge;

use UnexpectedValueException;

final class BadgePresenter
{
    public function __construct(private readonly string $publicBaseUrl)
    {
    }

    /**
     * @param array<string, mixed> $badge
     * @return array<string, mixed>
     */
    public function present(array $badge): array
    {
        $id = self::text($badge['id'] ?? null);
        $badgeName = self::text($badge['badgeName'] ?? null);
        if ($id === null || $badgeName === null) {
            throw new UnexpectedValueException('Badge definition is missing its public identity.');
        }

        $imageSource = $this->imageSource($badge['image'] ?? null);
        $imageUrl = $this->absoluteImageUrl($imageSource);

        return [
            'id' => $id,
            'databaseId' => max(0, (int)($badge['databaseId'] ?? 0)),
            'badgeName' => $badgeName,
            'title' => self::text($badge['title'] ?? null) ?? $badgeName,
            'description' => self::text($badge['description'] ?? null) ?? '',
            'image' => $imageUrl,
            'badgeImg' => $imageUrl,
            'imagePath' => $imageSource !== null && str_starts_with($imageSource, '/')
                ? $imageSource
                : null,
            'imageMimeType' => self::imageMimeType($imageSource),
            'pageConfigured' => (bool)($badge['pageConfigured'] ?? false),
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

    private function imageSource(mixed $value): ?string
    {
        $source = self::text($value);
        if ($source === null
            || str_contains($source, "\0")
            || str_contains($source, '\\')
            || preg_match('#(?:^|/)\.\.(?:/|$)#', $source) === 1) {
            return null;
        }

        if (str_starts_with($source, '/')) {
            return str_starts_with($source, '//') ? null : $source;
        }

        $url = filter_var($source, FILTER_VALIDATE_URL);
        if (is_string($url)) {
            $scheme = strtolower((string)parse_url($url, PHP_URL_SCHEME));
            return in_array($scheme, ['http', 'https'], true) ? $url : null;
        }

        if (preg_match('#^[A-Za-z0-9._~!$&\'()*+,;=@%/-]+$#D', $source) !== 1) {
            return null;
        }
        return '/' . ltrim($source, '/');
    }

    private function absoluteImageUrl(?string $source): ?string
    {
        if ($source === null || !str_starts_with($source, '/')) {
            return $source;
        }

        $baseUrl = rtrim(trim($this->publicBaseUrl), '/');
        $validated = filter_var($baseUrl, FILTER_VALIDATE_URL);
        $scheme = is_string($validated)
            ? strtolower((string)parse_url($validated, PHP_URL_SCHEME))
            : '';
        return in_array($scheme, ['http', 'https'], true)
            ? $validated . $source
            : $source;
    }

    private static function imageMimeType(?string $source): ?string
    {
        if ($source === null) {
            return null;
        }

        $path = parse_url($source, PHP_URL_PATH);
        $extension = is_string($path) ? strtolower(pathinfo($path, PATHINFO_EXTENSION)) : '';
        return match ($extension) {
            'svg' => 'image/svg+xml',
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'webp' => 'image/webp',
            'gif' => 'image/gif',
            default => null,
        };
    }
}
