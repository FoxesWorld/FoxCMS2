<?php

declare(strict_types=1);

final class GetJre implements JsonSerializable
{
    private array $payload;

    public function __construct(string $selector, string $platform, array $config)
    {
        $selector = trim($selector);
        if (!RuntimeJdkCatalog::isValidSelectorSyntax($selector)) {
            throw new InvalidArgumentException('Invalid Java runtime profile.');
        }

        $normalizedPlatform = RuntimeJdkCatalog::normalizePlatform($platform !== '' ? $platform : 'windows-x86_64');
        if ($normalizedPlatform === null) {
            throw new InvalidArgumentException('Invalid Java runtime platform.');
        }

        $storageDirectory = $this->bootstrapStorageDirectory($config);
        $catalog = new RuntimeJdkCatalog($storageDirectory);
        $artifact = $catalog->resolveArtifact($selector, $normalizedPlatform);
        if (!is_array($artifact)) {
            $this->payload = [
                'message' => 'Runtime archive not found for the selected Java profile and platform.',
                'runtimeProfile' => $selector,
                'platform' => $normalizedPlatform,
            ];
            return;
        }

        $absolutePath = (string)($artifact['absolute_path'] ?? '');
        $realFile = realpath($absolutePath);
        $runtimeRoot = realpath($catalog->runtimePath());
        $normalizedRoot = is_string($runtimeRoot)
            ? rtrim(str_replace('\\', '/', $runtimeRoot), '/') . '/'
            : '';
        $normalizedFile = is_string($realFile) ? str_replace('\\', '/', $realFile) : '';
        if ($realFile === false || !is_file($realFile) || is_link($absolutePath)
            || $normalizedRoot === '' || !str_starts_with($normalizedFile, $normalizedRoot)
        ) {
            $this->payload = [
                'message' => 'Resolved runtime archive is unavailable.',
                'runtimeProfile' => (string)($artifact['profile'] ?? $selector),
                'platform' => $normalizedPlatform,
            ];
            return;
        }

        $md5 = md5_file($realFile);
        $sha256 = hash_file('sha256', $realFile);
        $size = filesize($realFile);
        if (!is_string($md5) || !is_string($sha256) || !is_int($size) || $size <= 0) {
            throw new RuntimeException('Runtime archive metadata cannot be calculated.');
        }

        $relativePath = (string)($artifact['path'] ?? '');
        $this->payload = [
            'filename' => $this->publicBootstrapUrl($relativePath),
            'hash' => $md5,
            'sha256' => $sha256,
            'size' => $size,
            'fileName' => (string)($artifact['file_name'] ?? basename($realFile)),
            'runtimeId' => (string)($artifact['runtime_id'] ?? ''),
            'requestedVersion' => $selector,
            'jreVersion' => (string)($artifact['java_major'] ?? ''),
            'runtimeProfile' => (string)($artifact['profile'] ?? $selector),
            'version' => (string)($artifact['version'] ?? ''),
            'versionCore' => (string)($artifact['version_core'] ?? ''),
            'javaMajor' => (int)($artifact['java_major'] ?? 0),
            'platform' => (string)($artifact['platform'] ?? $normalizedPlatform),
            'vendor' => (string)($artifact['vendor'] ?? ''),
            'distribution' => (string)($artifact['distribution'] ?? ''),
            'archive' => (string)($artifact['archive'] ?? ''),
            'installPath' => (string)($artifact['install_path'] ?? ''),
            'javaPath' => (string)($artifact['java_path'] ?? ''),
            'stripComponents' => (int)($artifact['strip_components'] ?? 0),
            'inspection' => (string)($artifact['inspection'] ?? ''),
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->payload;
    }

    private function bootstrapStorageDirectory(array $config): string
    {
        $configured = function_exists('foxEnv')
            ? trim((string)(foxEnv('FOXESCRAFT_BOOTSTRAP_STORAGE_DIRECTORY', '') ?? ''))
            : '';
        if ($configured !== '') {
            return rtrim($configured, '/\\');
        }

        $uploads = trim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, UPLOADS_DIR), DIRECTORY_SEPARATOR);
        return rtrim(ROOT_DIR, '/\\')
            . DIRECTORY_SEPARATOR . $uploads
            . DIRECTORY_SEPARATOR . 'bootstrap';
    }

    private function publicBootstrapUrl(string $relativePath): string
    {
        $normalized = str_replace('\\', '/', trim($relativePath));
        if ($normalized === '' || !str_starts_with($normalized, 'runtime/')) {
            throw new RuntimeException('Runtime archive path is outside bootstrap storage.');
        }
        $segments = explode('/', $normalized);
        foreach ($segments as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                throw new RuntimeException('Runtime archive path is unsafe.');
            }
        }
        return '/uploads/bootstrap/' . implode('/', array_map('rawurlencode', $segments));
    }
}
