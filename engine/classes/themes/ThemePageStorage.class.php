<?php

declare(strict_types=1);

/** Resolves the single theme-owned page domain and its typed document directories. */
final class ThemePageStorage
{
    private string $rootDirectory;

    public function __construct(string $templatesDirectory, string $themeName)
    {
        if (preg_match('/^[A-Za-z0-9][A-Za-z0-9_-]{0,63}$/D', $themeName) !== 1) {
            throw new InvalidArgumentException('Invalid theme name.');
        }
        $templatesRoot = realpath($templatesDirectory);
        $themeDirectory = is_string($templatesRoot)
            ? realpath($templatesRoot . DIRECTORY_SEPARATOR . $themeName)
            : false;
        if (!is_string($templatesRoot) || !is_string($themeDirectory) || !is_dir($themeDirectory)
            || !str_starts_with($themeDirectory, rtrim($templatesRoot, '/\\') . DIRECTORY_SEPARATOR)) {
            throw new RuntimeException('Theme directory is unavailable.');
        }
        $this->rootDirectory = rtrim($themeDirectory, '/\\') . DIRECTORY_SEPARATOR . 'pages';
    }

    public function rootDirectory(): string
    {
        return $this->rootDirectory;
    }

    public function contentDirectory(): string
    {
        return $this->rootDirectory . DIRECTORY_SEPARATOR . 'content';
    }

    public function templatesDirectory(): string
    {
        return $this->rootDirectory . DIRECTORY_SEPARATOR . 'templates';
    }
}
