<?php

declare(strict_types=1);

final class LogQueryService
{
    private const MAXIMUM_LIMIT = 500;
    private const MAXIMUM_SCAN_LINES = 5_000;
    private const LEVELS = ['DEBUG', 'INFO', 'NOTICE', 'WARNING', 'WARN', 'ERROR', 'CRITICAL', 'FATAL'];

    /** @param list<string> $allowedLogNames */
    public function __construct(private array $allowedLogNames)
    {
        $this->allowedLogNames = array_values(array_unique(array_filter(
            array_map('strval', $allowedLogNames),
            static fn (string $name): bool => preg_match('/^[A-Za-z0-9_-]{1,48}$/D', $name) === 1,
        )));
    }

    /**
     * @param array<string, mixed> $filters
     * @return array{file:string,entries:list<array<string,mixed>>,summary:array<string,mixed>,filters:array<string,mixed>}
     */
    public function read(string $name, int $limit = 100, array $filters = []): array
    {
        $name = $this->validatedName($name);
        $limit = max(1, min(self::MAXIMUM_LIMIT, $limit));
        $filters = $this->normalizeFilters($filters);
        $scanLines = min(self::MAXIMUM_SCAN_LINES, max(500, $limit * 20));
        $path = $this->path($name);
        $lines = is_file($path) ? $this->tail($path, $scanLines) : [];

        $entries = [];
        $levelCounts = [];
        $deviationCount = 0;
        $malformedCount = 0;
        foreach (array_reverse($lines) as $line) {
            $entry = $this->parse($line);
            if (($entry['malformed'] ?? false) === true) {
                $malformedCount++;
            }
            if (!$this->matches($entry, $filters)) {
                continue;
            }

            $level = (string)($entry['level'] ?? 'LOG');
            $levelCounts[$level] = ($levelCounts[$level] ?? 0) + 1;
            if (is_array($entry['deviation'] ?? null)) {
                $deviationCount++;
            }
            $entries[] = $entry;
            if (count($entries) >= $limit) {
                break;
            }
        }
        $entries = array_reverse($entries);

        return [
            'file' => $name,
            'entries' => $entries,
            'summary' => [
                'returnedCount' => count($entries),
                'scannedCount' => count($lines),
                'deviationCount' => $deviationCount,
                'malformedCount' => $malformedCount,
                'levels' => $levelCounts,
                'firstTimestamp' => $entries[0]['timestamp'] ?? '',
                'lastTimestamp' => $entries !== [] ? ($entries[array_key_last($entries)]['timestamp'] ?? '') : '',
            ],
            'filters' => $filters,
        ];
    }

    public function clear(string $name): void
    {
        $name = $this->validatedName($name);
        (new Logger($name))->clear();
    }

    private function validatedName(string $name): string
    {
        $name = trim($name);
        if (!in_array($name, $this->allowedLogNames, true)) {
            throw new HttpException('Недопустимый log-файл.', 400);
        }
        return $name;
    }

    private function path(string $name): string
    {
        return ENGINE_DIR . 'cache' . DIRECTORY_SEPARATOR . 'logs'
            . DIRECTORY_SEPARATOR . $name . '.log';
    }

    /** @param array<string, mixed> $filters @return array<string, mixed> */
    private function normalizeFilters(array $filters): array
    {
        $normalized = [
            'requestId' => $this->safeIdentifier((string)($filters['requestId'] ?? '')),
            'correlationId' => $this->safeIdentifier((string)($filters['correlationId'] ?? '')),
            'event' => $this->safeEvent((string)($filters['event'] ?? '')),
            'component' => $this->safeEvent((string)($filters['component'] ?? '')),
            'level' => strtoupper(trim((string)($filters['level'] ?? ''))),
            'deviationOnly' => filter_var(
                $filters['deviationOnly'] ?? false,
                FILTER_VALIDATE_BOOLEAN,
            ),
            'search' => mb_substr(trim((string)($filters['search'] ?? '')), 0, 160),
        ];
        if ($normalized['level'] !== '' && !in_array($normalized['level'], self::LEVELS, true)) {
            throw new HttpException('Недопустимый уровень журнала.', 400);
        }
        return $normalized;
    }

    /** @return array<string, mixed> */
    private function parse(string $line): array
    {
        $record = json_decode($line, true);
        if (!is_array($record)) {
            return [
                'timestamp' => '',
                'time' => '—',
                'level' => 'LOG',
                'event' => 'legacy.unparsed',
                'message' => mb_substr($line, 0, 4_096),
                'tone' => 'default',
                'requestId' => '',
                'correlationId' => '',
                'component' => '',
                'operation' => '',
                'outcome' => '',
                'httpMethod' => '',
                'httpPath' => '',
                'actorUuid' => '',
                'actorLogin' => '',
                'actorGroup' => '',
                'requestChannel' => '',
                'action' => '',
                'handler' => '',
                'authenticated' => null,
                'sessionState' => '',
                'durationMs' => null,
                'httpStatus' => null,
                'deviation' => null,
                'exception' => null,
                'context' => [],
                'malformed' => true,
            ];
        }

        $timestamp = is_string($record['timestamp'] ?? null) ? $record['timestamp'] : '';
        $level = is_string($record['level'] ?? null) ? strtoupper(trim($record['level'])) : 'LOG';
        $time = $timestamp;
        if ($timestamp !== '') {
            try {
                $time = (new DateTimeImmutable($timestamp))->format('d.m.Y H:i:s');
            } catch (Throwable) {
                $time = $timestamp;
            }
        }

        $context = is_array($record['context'] ?? null) ? $record['context'] : [];
        foreach ([
            'requestChannel', 'actionField', 'action', 'handler', 'authenticated', 'sessionState',
            'moduleName', 'moduleClass', 'modulePriority', 'loadedCount', 'loadedModules',
            'skippedCount', 'skippedModules',
        ] as $field) {
            if (array_key_exists($field, $record)) {
                $context[$field] = $record[$field];
            }
        }

        return [
            'timestamp' => $timestamp,
            'time' => $time,
            'level' => $level !== '' ? $level : 'LOG',
            'event' => (string)($record['event'] ?? 'application.log'),
            'message' => (string)($record['message'] ?? ''),
            'tone' => $this->tone($level),
            'requestId' => (string)($record['requestId'] ?? ''),
            'correlationId' => (string)($record['correlationId'] ?? ''),
            'component' => (string)($record['component'] ?? ''),
            'operation' => (string)($record['operation'] ?? ''),
            'outcome' => (string)($record['outcome'] ?? ''),
            'httpMethod' => (string)($record['httpMethod'] ?? ''),
            'httpPath' => (string)($record['httpPath'] ?? ''),
            'actorUuid' => (string)($record['actorUuid'] ?? ''),
            'actorLogin' => (string)($record['actorLogin'] ?? ''),
            'actorGroup' => (string)($record['actorGroup'] ?? ''),
            'requestChannel' => (string)($record['requestChannel'] ?? ''),
            'action' => (string)($record['action'] ?? ''),
            'handler' => (string)($record['handler'] ?? ''),
            'authenticated' => is_bool($record['authenticated'] ?? null) ? $record['authenticated'] : null,
            'sessionState' => (string)($record['sessionState'] ?? ''),
            'durationMs' => is_numeric($record['durationMs'] ?? null) ? (float)$record['durationMs'] : null,
            'httpStatus' => is_numeric($record['httpStatus'] ?? null) ? (int)$record['httpStatus'] : null,
            'deviation' => is_array($record['deviation'] ?? null) ? $record['deviation'] : null,
            'exception' => is_array($record['exception'] ?? null)
                ? $record['exception']
                : (is_array($context['exception'] ?? null) ? $context['exception'] : null),
            'context' => $context,
            'malformed' => false,
        ];
    }

    /** @param array<string, mixed> $entry @param array<string, mixed> $filters */
    private function matches(array $entry, array $filters): bool
    {
        foreach (['requestId', 'correlationId', 'level', 'component'] as $field) {
            $expected = (string)($filters[$field] ?? '');
            if ($expected !== '' && strcasecmp((string)($entry[$field] ?? ''), $expected) !== 0) {
                return false;
            }
        }
        $event = (string)($filters['event'] ?? '');
        if ($event !== '' && !str_starts_with((string)($entry['event'] ?? ''), $event)) {
            return false;
        }
        if (($filters['deviationOnly'] ?? false) === true && !is_array($entry['deviation'] ?? null)) {
            return false;
        }
        $search = mb_strtolower((string)($filters['search'] ?? ''));
        if ($search !== '') {
            $haystack = json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if (!is_string($haystack) || !str_contains(mb_strtolower($haystack), $search)) {
                return false;
            }
        }
        return true;
    }

    private function safeIdentifier(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }
        if (preg_match('/^[A-Za-z0-9_.:-]{8,96}$/D', $value) !== 1) {
            throw new HttpException('Некорректный идентификатор трассировки.', 400);
        }
        return $value;
    }

    private function safeEvent(string $value): string
    {
        $value = strtolower(trim($value));
        if ($value === '') {
            return '';
        }
        if (preg_match('/^[a-z0-9_.-]{1,120}$/D', $value) !== 1) {
            throw new HttpException('Некорректный фильтр события.', 400);
        }
        return $value;
    }

    private function tone(string $level): string
    {
        return match (strtoupper($level)) {
            'ERROR', 'CRITICAL', 'FATAL' => 'error',
            'WARNING', 'WARN' => 'warning',
            'INFO', 'NOTICE' => 'info',
            'DEBUG', 'TRACE' => 'debug',
            default => 'default',
        };
    }

    /** @return list<string> */
    private function tail(string $path, int $count): array
    {
        $file = new SplFileObject($path, 'r');
        $file->seek(PHP_INT_MAX);
        $lastLine = $file->key();
        $start = max(0, $lastLine - $count);
        $lines = [];
        $file->seek($start);
        while (!$file->eof()) {
            $line = rtrim((string)$file->current(), "\r\n");
            if ($line !== '') {
                $lines[] = $line;
            }
            $file->next();
        }
        return array_slice($lines, -$count);
    }
}
