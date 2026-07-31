<?php

declare(strict_types=1);

if (!defined('FOXXEY')) {
    http_response_code(403);
    exit('Forbidden');
}

final class GameScanner implements JsonSerializable
{
    private const MAX_FILES = 100_000;

    /** @var array<int, list<string>> */
    private array $platformExtensions = [
        0 => ['so', 'zip', 'jar', 'toml', 'txt', 'cfg', 'recipe', 'dat', 'properties', 'json', 'git', 'sha1', '', 'cache', 'tsrg', 'mcmeta', 'png', 'wav', 'ogg', 'js', 'local', 'ks', 'nbt'],
        1 => ['dll', 'zip', 'jar', 'toml', 'txt', 'cfg', 'recipe', 'dat', 'properties', 'git', 'sha1', 'json', 'mcmeta', 'png', 'wav', 'ogg', 'js', 'local', 'ks', 'nbt'],
        2 => ['dylib', 'zip', 'jar', 'toml', 'txt', 'cfg', 'recipe', 'dat', 'properties', 'git', 'sha1', 'json'],
        3 => ['so', 'zip', 'jar', 'toml', 'txt', 'cfg', 'recipe', 'dat', 'properties', 'git', 'sha1', 'json'],
        4 => ['so', 'zip', 'jar', 'toml', 'txt', 'cfg', 'recipe', 'dat', 'properties', 'git', 'sha1', 'json'],
    ];

    private string $clientRoot;
    /** @var list<string> */
    private array $directories;
    /** @var list<array{filename:string,hash:string,sha256:string,size:string}> */
    private array $fileList = [];

    public function __construct(
        private string $client,
        private string $version,
        private int $platform,
        array $config,
    ) {
        $this->client = $this->safeIdentifier($client, 'client');
        $this->version = $this->safeIdentifier($version, 'version');
        if (!array_key_exists($platform, $this->platformExtensions)) {
            throw new InvalidArgumentException('Unsupported launcher platform.');
        }

        $launcher = is_array($config['launcherSettings'] ?? null)
            ? $config['launcherSettings']
            : [];
        $gameFiles = (string)($launcher['gameFiles'] ?? 'files/clients/');
        $root = realpath(ROOT_DIR . UPLOADS_DIR . $gameFiles);
        if ($root === false || !is_dir($root)) {
            throw new RuntimeException('Game files directory is unavailable.');
        }
        $this->clientRoot = rtrim($root, '/\\');
        $this->directories = $this->resolveDirectories();
    }

    public function scan(): void
    {
        $seen = [];
        foreach ($this->directories as $directory) {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator(
                    $directory,
                    FilesystemIterator::SKIP_DOTS | FilesystemIterator::CURRENT_AS_FILEINFO,
                ),
                RecursiveIteratorIterator::LEAVES_ONLY,
                RecursiveIteratorIterator::CATCH_GET_CHILD,
            );

            foreach ($iterator as $fileInfo) {
                if (!$fileInfo instanceof SplFileInfo || !$fileInfo->isFile() || $fileInfo->isLink()) {
                    continue;
                }
                $extension = strtolower($fileInfo->getExtension());
                if (!$this->isAllowedExtension($extension)) {
                    continue;
                }

                $realPath = $fileInfo->getRealPath();
                if ($realPath === false || !str_starts_with($realPath, $this->clientRoot . DIRECTORY_SEPARATOR)) {
                    continue;
                }
                $relativePath = $this->relativePath($realPath);
                if (isset($seen[$relativePath])) {
                    continue;
                }
                $seen[$relativePath] = true;

                $md5 = hash_file('md5', $realPath);
                $sha256 = hash_file('sha256', $realPath);
                $size = $fileInfo->getSize();
                if (!is_string($md5) || !is_string($sha256) || $size < 0) {
                    throw new RuntimeException('Unable to fingerprint game file: ' . $relativePath);
                }

                $this->fileList[] = [
                    'filename' => $relativePath,
                    'hash' => $md5,
                    'sha256' => $sha256,
                    'size' => (string)$size,
                ];
                if (count($this->fileList) > self::MAX_FILES) {
                    throw new RuntimeException('Game manifest exceeds the configured file limit.');
                }
            }
        }

        usort(
            $this->fileList,
            static fn (array $left, array $right): int => strcmp($left['filename'], $right['filename']),
        );
    }

    public function jsonSerialize(): array
    {
        return $this->fileList;
    }

    public function toJson(int $options = JSON_UNESCAPED_SLASHES): string
    {
        return json_encode($this, $options | JSON_THROW_ON_ERROR);
    }

    /** @return list<string> */
    private function resolveDirectories(): array
    {
        $relativeDirectories = [
            'versions/' . $this->version . '/assets/indexes',
            'versions/' . $this->version . '/assets/objects',
            'clients/' . $this->client,
            'versions/' . $this->version,
        ];
        $resolved = [];
        foreach ($relativeDirectories as $relative) {
            $directory = realpath($this->clientRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative));
            if (
                $directory !== false
                && is_dir($directory)
                && str_starts_with($directory, $this->clientRoot . DIRECTORY_SEPARATOR)
            ) {
                $resolved[] = $directory;
            }
        }
        return array_values(array_unique($resolved));
    }

    private function isAllowedExtension(string $extension): bool
    {
        return in_array($extension, $this->platformExtensions[$this->platform], true);
    }

    private function relativePath(string $absolutePath): string
    {
        return str_replace('\\', '/', substr($absolutePath, strlen(ROOT_DIR)));
    }

    private function safeIdentifier(string $value, string $name): string
    {
        $value = trim($value);
        if (preg_match('/^[A-Za-z0-9._-]{1,64}$/D', $value) !== 1) {
            throw new InvalidArgumentException('Invalid ' . $name . ' identifier.');
        }
        return $value;
    }
}
