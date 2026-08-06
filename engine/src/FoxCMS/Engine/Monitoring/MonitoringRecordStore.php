<?php

declare(strict_types=1);

namespace FoxCMS\Engine\Monitoring;

use RuntimeException;

/** Concurrency-safe storage for monitoring maximum-player records. */
final class MonitoringRecordStore
{
    private string $absolutePath;
    private string $dayPath;

    /** @param array<string, mixed> $config */
    public function __construct(array $config)
    {
        $this->absolutePath = $this->preparePath((string)($config['absoluteRecordPath'] ?? ''), 'absolute');
        $this->dayPath = $this->preparePath((string)($config['dayRecordPath'] ?? ''), 'daily');
    }

    /** @return array{all:int,day:int} */
    public function load(): array
    {
        return [
            'all' => $this->read($this->absolutePath),
            'day' => $this->read($this->dayPath),
        ];
    }

    public function updateAbsolute(int $candidate): int
    {
        return $this->update($this->absolutePath, $candidate);
    }

    public function updateDay(int $candidate): int
    {
        return $this->update($this->dayPath, $candidate);
    }

    private function preparePath(string $path, string $label): string
    {
        $path = trim($path);
        if ($path === '' || str_contains($path, "\0")) {
            throw new RuntimeException('Monitoring ' . $label . ' record path is invalid.');
        }
        $directory = dirname($path);
        if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) {
            throw new RuntimeException('Monitoring record directory cannot be created.');
        }
        if (is_link($directory) || (file_exists($path) && is_link($path))) {
            throw new RuntimeException('Monitoring record storage must not use symbolic links.');
        }
        if (!is_writable($directory)) {
            throw new RuntimeException('Monitoring record directory is not writable.');
        }
        return $path;
    }

    private function read(string $path): int
    {
        $stream = $this->open($path);
        try {
            if (!flock($stream, LOCK_SH)) {
                throw new RuntimeException('Monitoring record cannot be locked for reading.');
            }
            rewind($stream);
            $raw = stream_get_contents($stream, 64);
            flock($stream, LOCK_UN);
            return is_string($raw) ? max(0, (int)trim($raw)) : 0;
        } finally {
            fclose($stream);
        }
    }

    private function update(string $path, int $candidate): int
    {
        $candidate = max(0, $candidate);
        $stream = $this->open($path);
        try {
            if (!flock($stream, LOCK_EX)) {
                throw new RuntimeException('Monitoring record cannot be locked for update.');
            }
            rewind($stream);
            $raw = stream_get_contents($stream, 64);
            $current = is_string($raw) ? max(0, (int)trim($raw)) : 0;
            if ($candidate > $current) {
                rewind($stream);
                if (!ftruncate($stream, 0) || fwrite($stream, (string)$candidate) === false || !fflush($stream)) {
                    throw new RuntimeException('Monitoring record cannot be persisted.');
                }
                $current = $candidate;
            }
            flock($stream, LOCK_UN);
            return $current;
        } finally {
            fclose($stream);
        }
    }

    /** @return resource */
    private function open(string $path)
    {
        $created = !file_exists($path);
        $stream = fopen($path, 'c+b');
        if ($stream === false) {
            throw new RuntimeException('Monitoring record file cannot be opened.');
        }
        if ($created) {
            @chmod($path, 0640);
        }
        return $stream;
    }
}
