<?php

declare(strict_types=1);

namespace FoxCMS\Api\Launcher;

use RuntimeException;

final class FileCatalogCache
{
    public function __construct(
        private readonly string $directory,
        private readonly int $ttlSeconds,
    ) {
    }

    /** @param callable(): string $producer @return array{body: string, state: string} */
    public function remember(string $key, callable $producer): array
    {
        $this->ensureDirectory();
        $cacheFile = $this->directory . DIRECTORY_SEPARATOR . $key . '.json';
        $lockFile = $this->directory . DIRECTORY_SEPARATOR . $key . '.lock';

        $cached = $this->readFresh($cacheFile);
        if ($cached !== null) {
            return ['body' => $cached, 'state' => 'HIT'];
        }

        $lock = fopen($lockFile, 'c+b');
        if ($lock === false || !flock($lock, LOCK_EX)) {
            throw new RuntimeException('Unable to lock launcher catalog cache.');
        }
        try {
            clearstatcache(true, $cacheFile);
            $cached = $this->readFresh($cacheFile);
            if ($cached !== null) {
                return ['body' => $cached, 'state' => 'HIT-AFTER-LOCK'];
            }

            $body = $producer();
            if ($body === '') {
                throw new RuntimeException('Launcher catalog producer returned an empty response.');
            }
            $temporary = $cacheFile . '.tmp.' . bin2hex(random_bytes(6));
            if (file_put_contents($temporary, $body, LOCK_EX) === false) {
                throw new RuntimeException('Unable to write launcher catalog cache.');
            }
            @chmod($temporary, 0640);
            if (!rename($temporary, $cacheFile)) {
                @unlink($temporary);
                throw new RuntimeException('Unable to publish launcher catalog cache atomically.');
            }
            return ['body' => $body, 'state' => 'MISS'];
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    private function ensureDirectory(): void
    {
        if (!is_dir($this->directory) && !mkdir($this->directory, 0750, true) && !is_dir($this->directory)) {
            throw new RuntimeException('Unable to create launcher catalog cache directory.');
        }
    }

    private function readFresh(string $cacheFile): ?string
    {
        $mtime = is_file($cacheFile) ? filemtime($cacheFile) : false;
        if ($mtime === false || $mtime + $this->ttlSeconds < time()) {
            return null;
        }
        $body = file_get_contents($cacheFile);
        return is_string($body) && $body !== '' ? $body : null;
    }
}
