<?php

declare(strict_types=1);

/** Shared parser and atomic storage primitives for theme-owned Vue-compatible TPL files. */
final class ThemeRuntimeTplDocument
{
    public const MAXIMUM_BYTES = 1_048_576;

    public static function resolveDirectory(string $templatesDirectory, string $themeName, string $relativeDirectory): string
    {
        if (preg_match('/^[A-Za-z0-9][A-Za-z0-9_-]{0,63}$/D', $themeName) !== 1) {
            throw new InvalidArgumentException('Invalid theme name.');
        }
        if (preg_match('~^[A-Za-z0-9][A-Za-z0-9_/-]{0,127}$~D', $relativeDirectory) !== 1
            || str_contains($relativeDirectory, '..')) {
            throw new InvalidArgumentException('Invalid runtime TPL directory.');
        }
        $templatesRoot = realpath($templatesDirectory);
        $themeDirectory = is_string($templatesRoot)
            ? realpath($templatesRoot . DIRECTORY_SEPARATOR . $themeName)
            : false;
        if (!is_string($templatesRoot) || !is_string($themeDirectory) || !is_dir($themeDirectory)
            || !str_starts_with($themeDirectory, rtrim($templatesRoot, '/\\') . DIRECTORY_SEPARATOR)) {
            throw new RuntimeException('Theme directory is unavailable.');
        }
        return rtrim($themeDirectory, '/\\') . DIRECTORY_SEPARATOR
            . str_replace('/', DIRECTORY_SEPARATOR, $relativeDirectory);
    }

    public static function readSource(string $path, string $label): string
    {
        if (!is_file($path) || is_link($path)) {
            throw new RuntimeException($label . ' is missing: ' . basename($path));
        }
        $source = file_get_contents($path);
        if (!is_string($source) || trim($source) === '' || strlen($source) > self::MAXIMUM_BYTES) {
            throw new RuntimeException($label . ' is empty or exceeds 1 MiB: ' . basename($path));
        }
        return $source;
    }

    /**
     * @param list<string> $allowedComponents
     * @return array{id:string,file:string,revision:int,updatedAt:string,html:string,source?:string,_inner:string,_attributes:array<string,string>}
     */
    public static function parse(
        string $source,
        string $rootTag,
        string $expectedId,
        string $file,
        array $allowedComponents,
        bool $includeSource,
        bool $allowVHtml = false,
    ): array {
        if ($source === '' || strlen($source) > self::MAXIMUM_BYTES || str_contains($source, "\0")) {
            throw new InvalidArgumentException('Runtime TPL is empty, contains NUL, or exceeds 1 MiB.');
        }
        if (preg_match(
            '/^\s*<' . preg_quote($rootTag, '/') . '\b([^>]*)>([\s\S]*)<\/' . preg_quote($rootTag, '/') . '>\s*$/u',
            $source,
            $root,
        ) !== 1) {
            throw new InvalidArgumentException('TPL must contain exactly one <' . $rootTag . '> root.');
        }
        $attributes = self::attributes((string)$root[1]);
        $id = self::id((string)($attributes['id'] ?? ''), 'TPL');
        if ($id !== $expectedId || (int)($attributes['schema'] ?? 0) !== 1) {
            throw new InvalidArgumentException('TPL has an unexpected id or schema: ' . $expectedId);
        }
        $inner = (string)$root[2];
        $body = self::block($inner, 'fox-template-body');
        self::validateBody($body, $allowedComponents, $allowVHtml);
        $result = [
            'id' => $id,
            'file' => $file,
            'revision' => max(1, min(2_147_483_647, (int)($attributes['revision'] ?? 1))),
            'updatedAt' => self::timestamp((string)($attributes['updated-at'] ?? '')),
            'html' => trim($body) . PHP_EOL,
            '_inner' => $inner,
            '_attributes' => $attributes,
        ];
        if ($includeSource) $result['source'] = rtrim($source) . PHP_EOL;
        return $result;
    }

    /** @return array<string,string> */
    public static function attributes(string $source): array
    {
        preg_match_all('~([A-Za-z_:][A-Za-z0-9_.:-]*)\s*=\s*(?:"([^"]*)"|&quot;([^&]*)&quot;)~u', $source, $matches, PREG_SET_ORDER);
        $result = [];
        foreach ($matches as $match) {
            $raw = array_key_exists(2, $match) && $match[2] !== '' ? (string)$match[2] : (string)($match[3] ?? '');
            $result[strtolower((string)$match[1])] = html_entity_decode($raw, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }
        return $result;
    }

    public static function block(string $source, string $tag): string
    {
        if (preg_match('/<' . preg_quote($tag, '/') . '\b[^>]*>([\s\S]*?)<\/' . preg_quote($tag, '/') . '>/u', $source, $match) !== 1) {
            throw new InvalidArgumentException('TPL is missing the required <' . $tag . '> block.');
        }
        return (string)$match[1];
    }

    /** @param list<string> $allowedComponents */
    private static function validateBody(string $body, array $allowedComponents, bool $allowVHtml): void
    {
        if (trim($body) === '' || strlen($body) > self::MAXIMUM_BYTES) {
            throw new InvalidArgumentException('TPL HTML is empty or exceeds 1 MiB.');
        }
        $structuralPatterns = [
            '/<(?:script|style|iframe|object|embed|link|meta|base)\b/iu',
            '/\son[a-z]+\s*=/iu',
            '/(?:javascript|vbscript|data\s*:\s*text\/html)\s*:/iu',
        ];
        if (!$allowVHtml) array_splice($structuralPatterns, 1, 0, ['/\bv-html\s*=/iu']);
        foreach ($structuralPatterns as $pattern) {
            if (preg_match($pattern, $body) === 1) {
                throw new InvalidArgumentException('TPL contains a forbidden HTML construct.');
            }
        }

        $expressionPatterns = [
            '/\b(?:window|document|globalThis|Function|eval|fetch|XMLHttpRequest|WebSocket|localStorage|sessionStorage)\b/u',
            '/\b(?:constructor|prototype|__proto__)\b/u',
            '/\bimport\s*\(/u',
        ];
        foreach (self::expressions($body) as $expression) {
            foreach ($expressionPatterns as $pattern) {
                if (preg_match($pattern, $expression) === 1) {
                    throw new InvalidArgumentException('TPL contains a forbidden Vue expression.');
                }
            }
        }
        preg_match_all('/<([A-Z][A-Za-z0-9]*)\b/u', $body, $components);
        $allowed = array_fill_keys($allowedComponents, true);
        foreach (array_unique($components[1] ?? []) as $component) {
            if (!isset($allowed[$component])) {
                throw new InvalidArgumentException('TPL uses an unknown Vue component: ' . $component);
            }
        }
    }

    /** @return list<string> */
    private static function expressions(string $body): array
    {
        $result = [];
        preg_match_all('/\{\{([\s\S]*?)\}\}/u', $body, $interpolations);
        foreach ($interpolations[1] ?? [] as $expression) $result[] = (string)$expression;

        preg_match_all(
            '/\s(?:v-[A-Za-z0-9_.:-]+|[:@#][A-Za-z0-9_.:\-\[\]]+)\s*=\s*(["\'])([\s\S]*?)\1/u',
            $body,
            $directives,
            PREG_SET_ORDER,
        );
        foreach ($directives as $directive) $result[] = (string)($directive[2] ?? '');
        return $result;
    }

    public static function replaceRootAttribute(string $source, string $rootTag, string $attribute, string $value): string
    {
        $escaped = htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
        return preg_replace_callback('/^\s*<' . preg_quote($rootTag, '/') . '\b([^>]*)>/u', static function(array $match) use ($rootTag, $attribute, $escaped): string {
            $attributes = (string)($match[1] ?? '');
            $pattern = '/\s+' . preg_quote($attribute, '/') . '\s*=\s*(["\']).*?\1/u';
            $attributes = preg_match($pattern, $attributes) === 1
                ? (preg_replace($pattern, ' ' . $attribute . '="' . $escaped . '"', $attributes, 1) ?? $attributes)
                : $attributes . ' ' . $attribute . '="' . $escaped . '"';
            return '<' . $rootTag . $attributes . '>';
        }, $source, 1) ?? $source;
    }

    public static function path(string $directory, string $relativeFile): string
    {
        if (preg_match('~^[A-Za-z0-9][A-Za-z0-9_./-]{0,159}\.tpl$~D', $relativeFile) !== 1
            || str_contains($relativeFile, '..')) {
            throw new InvalidArgumentException('Invalid runtime TPL path: ' . $relativeFile);
        }
        return rtrim($directory, '/\\') . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativeFile);
    }

    /** @param list<string> $relativeFiles */
    public static function storageReady(string $directory, array $relativeFiles): bool
    {
        if (!is_dir($directory) || is_link($directory) || !is_writable($directory)) return false;
        foreach ($relativeFiles as $relativeFile) {
            $path = self::path($directory, $relativeFile);
            if (!is_file($path) || is_link($path) || !is_readable($path) || !is_writable($path)) return false;
        }
        return true;
    }

    public static function write(string $directory, string $path, string $source, string $prefix): void
    {
        $targetDirectory = dirname($path);
        if (!is_dir($directory) || is_link($directory) || !is_writable($directory)
            || !is_dir($targetDirectory) || is_link($targetDirectory) || !is_writable($targetDirectory)
            || !str_starts_with($targetDirectory, rtrim($directory, '/\\'))) {
            throw new RuntimeException('Runtime TPL directory is not writable.');
        }
        $temporary = $targetDirectory . DIRECTORY_SEPARATOR . '.' . $prefix . '-' . bin2hex(random_bytes(12)) . '.tmp';
        $backup = null;
        try {
            if (@file_put_contents($temporary, $source, LOCK_EX) !== strlen($source)) {
                throw new RuntimeException('Unable to write the temporary runtime TPL.');
            }
            @chmod($temporary, 0640);
            if (is_file($path)) {
                $backup = $targetDirectory . DIRECTORY_SEPARATOR . '.' . $prefix . '-backup-' . bin2hex(random_bytes(12)) . '.tmp';
                if (!rename($path, $backup)) throw new RuntimeException('Unable to move the previous runtime TPL.');
            }
            if (!rename($temporary, $path)) {
                if (is_string($backup) && is_file($backup)) @rename($backup, $path);
                throw new RuntimeException('Unable to replace the runtime TPL.');
            }
            @chmod($path, 0644);
            if (is_string($backup) && is_file($backup)) @unlink($backup);
        } catch (Throwable $error) {
            if (is_file($temporary)) @unlink($temporary);
            if (is_string($backup) && is_file($backup) && !is_file($path)) @rename($backup, $path);
            throw $error;
        }
    }

    private static function id(string $value, string $context): string
    {
        $value = trim($value);
        if (preg_match('/^[a-z][a-z0-9-]{1,63}$/D', $value) !== 1) {
            throw new InvalidArgumentException('Invalid ' . $context . ' id: ' . $value);
        }
        return $value;
    }

    private static function timestamp(string $value): string
    {
        $value = trim($value);
        if ($value === '') return '';
        $timestamp = strtotime($value);
        return $timestamp === false ? '' : gmdate('c', $timestamp);
    }
}
