<?php

declare(strict_types=1);

namespace FoxCMS\Api\News;

use FoxCMS\Shared\Environment\Environment;
use finfo;

final class ImageDataUrlEncoder
{
    private const MAX_SOURCE_BYTES = 2 * 1024 * 1024;
    private const MAX_SOURCE_DIMENSION = 4096;
    private const TARGET_WIDTH = 960;
    private const TARGET_HEIGHT = 540;
    private const JPEG_QUALITY = 84;
    private const ALLOWED_MIME_TYPES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

    /** @param list<string> $allowedHosts */
    public function __construct(
        private readonly string $rootDirectory,
        private readonly array $allowedHosts,
    ) {
    }

    public function encode(?string $source): ?string
    {
        $source = trim((string)$source);
        if ($source === '') {
            return null;
        }

        if (preg_match('#^data:(image/(?:jpeg|png|gif|webp));base64,(.+)$#is', $source, $matches) === 1) {
            $bytes = base64_decode(preg_replace('/\s+/', '', $matches[2]) ?? '', true);
            return is_string($bytes) ? $this->encodeBytes($bytes, strtolower($matches[1])) : null;
        }

        $parts = parse_url($source);
        if ($parts === false) {
            return null;
        }
        if (isset($parts['scheme']) && !$this->isAllowedHost((string)($parts['host'] ?? ''))) {
            return null;
        }

        $relativePath = rawurldecode((string)($parts['path'] ?? $source));
        $relativePath = ltrim(str_replace('\\', '/', $relativePath), '/');
        if ($relativePath === '' || str_contains($relativePath, "\0") || !str_starts_with($relativePath, 'uploads/')) {
            return null;
        }

        $uploadsDirectory = realpath($this->rootDirectory . DIRECTORY_SEPARATOR . 'uploads');
        $absolutePath = realpath(
            $this->rootDirectory . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath),
        );
        if ($uploadsDirectory === false || $absolutePath === false || !is_file($absolutePath) || !is_readable($absolutePath)) {
            return null;
        }
        $uploadsPrefix = rtrim($uploadsDirectory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        if (!str_starts_with($absolutePath, $uploadsPrefix)) {
            return null;
        }

        $size = filesize($absolutePath);
        if (!is_int($size) || $size < 1 || $size > self::MAX_SOURCE_BYTES) {
            return null;
        }
        $bytes = file_get_contents($absolutePath);
        if (!is_string($bytes)) {
            return null;
        }

        $mime = null;
        if (class_exists(finfo::class)) {
            $detector = new finfo(FILEINFO_MIME_TYPE);
            $detected = $detector->buffer($bytes);
            $mime = is_string($detected) ? strtolower($detected) : null;
        }
        return $this->encodeBytes($bytes, $mime);
    }

    /** @return list<string> */
    public static function allowedHosts(\NetworkContext $network, Environment $environment): array
    {
        $originHost = parse_url($network->origin(), PHP_URL_HOST);
        $host = is_string($originHost) ? strtolower($originHost) : '';
        $hosts = array_filter([$host, 'localhost', '127.0.0.1']);
        if ($host !== '') {
            $hosts[] = str_starts_with($host, 'www.') ? substr($host, 4) : 'www.' . $host;
        }
        foreach ($environment->csv('FOXESCRAFT_PUBLIC_HOSTS') as $configuredHost) {
            $configuredHost = strtolower($configuredHost);
            if (preg_match('/^(?:[a-z0-9](?:[a-z0-9.-]*[a-z0-9])?|localhost)$/D', $configuredHost) === 1) {
                $hosts[] = $configuredHost;
            }
        }
        return array_values(array_unique($hosts));
    }

    private function isAllowedHost(string $host): bool
    {
        $host = strtolower(trim($host));
        return $host === '' || in_array($host, $this->allowedHosts, true);
    }

    private function encodeBytes(string $bytes, ?string $declaredMime): ?string
    {
        if ($bytes === '' || strlen($bytes) > self::MAX_SOURCE_BYTES) {
            return null;
        }
        $imageInfo = @getimagesizefromstring($bytes);
        if (!is_array($imageInfo)) {
            return null;
        }
        $width = (int)($imageInfo[0] ?? 0);
        $height = (int)($imageInfo[1] ?? 0);
        $mime = strtolower((string)($imageInfo['mime'] ?? $declaredMime ?? ''));
        if ($width < 1 || $height < 1 || $width > self::MAX_SOURCE_DIMENSION || $height > self::MAX_SOURCE_DIMENSION) {
            return null;
        }
        if (!in_array($mime, self::ALLOWED_MIME_TYPES, true)) {
            return null;
        }

        $jpeg = $this->resizeToJpeg($bytes, $width, $height);
        if ($jpeg !== null) {
            return 'data:image/jpeg;base64,' . base64_encode($jpeg);
        }
        if ($mime === 'image/webp') {
            return null;
        }
        return 'data:' . $mime . ';base64,' . base64_encode($bytes);
    }

    private function resizeToJpeg(string $bytes, int $width, int $height): ?string
    {
        if (!function_exists('imagecreatefromstring') || !function_exists('imagejpeg')) {
            return null;
        }
        $source = @imagecreatefromstring($bytes);
        if ($source === false) {
            return null;
        }

        try {
            $scale = min(1.0, self::TARGET_WIDTH / $width, self::TARGET_HEIGHT / $height);
            $targetWidth = max(1, (int)round($width * $scale));
            $targetHeight = max(1, (int)round($height * $scale));
            $target = imagecreatetruecolor($targetWidth, $targetHeight);
            if ($target === false) {
                return null;
            }
            try {
                $background = imagecolorallocate($target, 30, 25, 21);
                imagefill($target, 0, 0, $background);
                imagecopyresampled($target, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);
                ob_start();
                imagejpeg($target, null, self::JPEG_QUALITY);
                $encoded = ob_get_clean();
                return is_string($encoded) && $encoded !== '' ? $encoded : null;
            } finally {
                imagedestroy($target);
            }
        } finally {
            imagedestroy($source);
        }
    }
}
