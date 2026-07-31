<?php

declare(strict_types=1);

final class ThemeResolver
{
    public function __construct(private string $templatesDirectory)
    {
    }

    public function resolve(string $name): array
    {
        if (preg_match('/^[A-Za-z0-9][A-Za-z0-9_-]{0,63}$/D', $name) !== 1) {
            throw new RuntimeException('Invalid theme name: ' . $name);
        }

        $directory = rtrim($this->templatesDirectory, '/\\') . DIRECTORY_SEPARATOR . $name;
        $manifestPath = $directory . DIRECTORY_SEPARATOR . 'theme.json';
        $manifest = $this->readManifest($manifestPath);
        if (($manifest['schema'] ?? null) !== 1) {
            throw new RuntimeException('Unsupported theme manifest schema: ' . $manifestPath);
        }

        $shell = $this->resolveFile($directory, (string)($manifest['shell'] ?? 'index.html'));
        $frontend = $this->resolveFile($directory, (string)($manifest['frontend'] ?? 'frontend.json'));
        $assets = is_array($manifest['assets'] ?? null) ? $manifest['assets'] : [];
        return [
            'name' => $name,
            'directory' => $directory,
            'publicBase' => '/templates/' . rawurlencode($name) . '/',
            'shell' => $shell,
            'frontend' => $frontend,
            'mount' => (string)($manifest['mount'] ?? 'foxescraft-app'),
            'styles' => $this->resolveAssets($directory, $name, $assets['styles'] ?? []),
            'scripts' => $this->resolveAssets($directory, $name, $assets['scripts'] ?? []),
            'settings' => is_array($manifest['settings'] ?? null) ? $manifest['settings'] : [],
        ];
    }

    private function resolveAssets(string $directory, string $themeName, mixed $assets): array
    {
        if (!is_array($assets)) {
            return [];
        }
        $resolved = [];
        foreach ($assets as $asset) {
            if (!is_string($asset)) {
                continue;
            }
            $relative = $this->safeRelativePath($asset);
            $this->resolveFile($directory, $relative);
            $resolved[] = '/templates/' . rawurlencode($themeName) . '/'
                . implode('/', array_map('rawurlencode', explode('/', $relative)));
        }
        return $resolved;
    }

    private function resolveFile(string $directory, string $relative): string
    {
        $relative = $this->safeRelativePath($relative);
        $path = $directory . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
        if (!is_file($path) || !is_readable($path)) {
            throw new RuntimeException('Theme file is unavailable: ' . $path);
        }
        return $path;
    }

    private function safeRelativePath(string $path): string
    {
        $path = str_replace('\\', '/', trim($path));
        if (
            $path === ''
            || str_starts_with($path, '/')
            || preg_match('/^[A-Za-z]:/D', $path) === 1
            || preg_match('#(?:^|/)\.{1,2}(?:/|$)#D', $path) === 1
            || preg_match('#^[A-Za-z0-9._/-]+$#D', $path) !== 1
        ) {
            throw new RuntimeException('Unsafe theme path: ' . $path);
        }
        return $path;
    }

    private function readManifest(string $path): array
    {
        $json = is_file($path) ? file_get_contents($path) : false;
        if ($json === false) {
            throw new RuntimeException('Theme manifest is unavailable: ' . $path);
        }
        $manifest = json_decode($json, true, 64, JSON_THROW_ON_ERROR);
        if (!is_array($manifest) || array_is_list($manifest)) {
            throw new RuntimeException('Theme manifest must be a JSON object: ' . $path);
        }
        return $manifest;
    }
}
