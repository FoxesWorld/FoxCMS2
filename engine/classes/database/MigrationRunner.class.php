<?php

declare(strict_types=1);

final class MigrationRunner
{
    private const LOCK_NAME = 'foxescraft:migrations';
    private const REPOSITORY_TABLE = 'foxescraft_migrations';

    public function __construct(
        private db $database,
        private string $migrationDirectory,
    ) {
        $this->migrationDirectory = rtrim($migrationDirectory, '/\\');
    }

    /**
     * @return array{applied:list<string>, skipped:list<string>, latest:?string}
     */
    public function migrate(bool $dryRun = false): array
    {
        $files = $this->migrationFiles();
        if ($dryRun) {
            $applied = $this->repositoryExists() ? $this->appliedMigrations() : [];
            $pending = [];
            $skipped = [];
            foreach ($files as $version => $file) {
                if (isset($applied[$version])) {
                    $this->assertChecksum($version, $file, $applied[$version]);
                    $skipped[] = $version;
                } else {
                    $pending[] = $version;
                }
            }
            return [
                'applied' => $pending,
                'skipped' => $skipped,
                'latest' => $files === [] ? null : array_key_last($files),
            ];
        }

        $this->acquireLock();
        try {
            $this->ensureRepository();
            $known = $this->appliedMigrations();
            $applied = [];
            $skipped = [];

            foreach ($files as $version => $file) {
                if (isset($known[$version])) {
                    $this->assertChecksum($version, $file, $known[$version]);
                    $skipped[] = $version;
                    continue;
                }

                $startedAt = hrtime(true);
                $sql = $this->readMigration($file);
                foreach ($this->splitStatements($sql) as $statement) {
                    $this->database->exec($statement);
                }
                $executionMs = (int)round((hrtime(true) - $startedAt) / 1_000_000);

                $record = $this->database->prepare(
                    'INSERT INTO `' . self::REPOSITORY_TABLE . '` '
                    . '(`version`, `checksum`, `execution_ms`) '
                    . 'VALUES (:version, :checksum, :execution_ms)'
                );
                $record->execute([
                    ':version' => $version,
                    ':checksum' => hash_file('sha256', $file),
                    ':execution_ms' => $executionMs,
                ]);
                $applied[] = $version;
            }

            return [
                'applied' => $applied,
                'skipped' => $skipped,
                'latest' => $files === [] ? null : array_key_last($files),
            ];
        } finally {
            $this->releaseLock();
        }
    }

    /**
     * @return array{repository:bool, latestAvailable:?string, latestApplied:?string, pending:list<string>}
     */
    public function status(): array
    {
        $files = $this->migrationFiles();
        if (!$this->repositoryExists()) {
            return [
                'repository' => false,
                'latestAvailable' => $files === [] ? null : array_key_last($files),
                'latestApplied' => null,
                'pending' => array_keys($files),
            ];
        }

        $known = $this->appliedMigrations();
        foreach ($known as $version => $checksum) {
            if (!isset($files[$version])) {
                throw new RuntimeException('Applied migration is missing from source: ' . $version);
            }
            $this->assertChecksum($version, $files[$version], $checksum);
        }

        return [
            'repository' => true,
            'latestAvailable' => $files === [] ? null : array_key_last($files),
            'latestApplied' => $known === [] ? null : array_key_last($known),
            'pending' => array_values(array_diff(array_keys($files), array_keys($known))),
        ];
    }

    private function ensureRepository(): void
    {
        $this->database->exec(
            'CREATE TABLE IF NOT EXISTS `' . self::REPOSITORY_TABLE . '` ('
            . '`version` VARCHAR(96) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,'
            . '`checksum` CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,'
            . '`applied_at` DATETIME(4) NOT NULL DEFAULT CURRENT_TIMESTAMP(4),'
            . '`execution_ms` INT UNSIGNED NOT NULL DEFAULT 0,'
            . 'PRIMARY KEY (`version`)'
            . ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }

    private function repositoryExists(): bool
    {
        $statement = $this->database->prepare(
            'SELECT COUNT(*) FROM information_schema.TABLES '
            . 'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table'
        );
        $statement->execute([':table' => self::REPOSITORY_TABLE]);
        return (int)$statement->fetchColumn() === 1;
    }

    /** @return array<string, string> */
    private function appliedMigrations(): array
    {
        $statement = $this->database->query(
            'SELECT `version`, `checksum` FROM `' . self::REPOSITORY_TABLE . '` ORDER BY `version` ASC'
        );
        if (!$statement instanceof PDOStatement) {
            return [];
        }

        $result = [];
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $result[(string)$row['version']] = (string)$row['checksum'];
        }
        return $result;
    }

    /** @return array<string, string> */
    private function migrationFiles(): array
    {
        if (!is_dir($this->migrationDirectory)) {
            throw new RuntimeException('Migration directory not found: ' . $this->migrationDirectory);
        }

        $files = [];
        foreach (new DirectoryIterator($this->migrationDirectory) as $entry) {
            if (!$entry->isFile()) {
                continue;
            }
            $name = $entry->getFilename();
            if (preg_match('/^(\d{3,6}_[a-z0-9_]+)\.sql$/D', $name, $matches) !== 1) {
                if (str_ends_with(strtolower($name), '.sql')) {
                    throw new RuntimeException('Invalid migration filename: ' . $name);
                }
                continue;
            }
            $version = $matches[1];
            if (isset($files[$version])) {
                throw new RuntimeException('Duplicate migration version: ' . $version);
            }
            $files[$version] = $entry->getPathname();
        }
        ksort($files, SORT_STRING);
        return $files;
    }

    private function readMigration(string $file): string
    {
        $sql = file_get_contents($file);
        if (!is_string($sql) || trim($sql) === '') {
            throw new RuntimeException('Migration is empty or unreadable: ' . basename($file));
        }
        return $sql;
    }

    private function assertChecksum(string $version, string $file, string $expected): void
    {
        $actual = hash_file('sha256', $file);
        if (!is_string($actual) || !hash_equals($expected, $actual)) {
            throw new RuntimeException('Applied migration checksum mismatch: ' . $version);
        }
    }

    private function acquireLock(): void
    {
        $statement = $this->database->prepare('SELECT GET_LOCK(:name, 15)');
        $statement->execute([':name' => self::LOCK_NAME]);
        if ((int)$statement->fetchColumn() !== 1) {
            throw new RuntimeException('Unable to acquire the migration lock.');
        }
    }

    private function releaseLock(): void
    {
        try {
            $statement = $this->database->prepare('SELECT RELEASE_LOCK(:name)');
            $statement->execute([':name' => self::LOCK_NAME]);
        } catch (Throwable) {
            // The connection closing also releases the advisory lock.
        }
    }

    /** @return list<string> */
    private function splitStatements(string $sql): array
    {
        $statements = [];
        $buffer = '';
        $quote = null;
        $lineComment = false;
        $blockComment = false;
        $length = strlen($sql);

        for ($index = 0; $index < $length; $index++) {
            $character = $sql[$index];
            $next = $index + 1 < $length ? $sql[$index + 1] : '';

            if ($lineComment) {
                if ($character === "\n") {
                    $lineComment = false;
                    $buffer .= $character;
                }
                continue;
            }
            if ($blockComment) {
                if ($character === '*' && $next === '/') {
                    $blockComment = false;
                    $index++;
                }
                continue;
            }

            if ($quote === null) {
                if ($character === '-' && $next === '-' && ($index + 2 >= $length || ctype_space($sql[$index + 2]))) {
                    $lineComment = true;
                    $index++;
                    continue;
                }
                if ($character === '#') {
                    $lineComment = true;
                    continue;
                }
                if ($character === '/' && $next === '*') {
                    $blockComment = true;
                    $index++;
                    continue;
                }
                if ($character === "'" || $character === '"' || $character === '`') {
                    $quote = $character;
                    $buffer .= $character;
                    continue;
                }
                if ($character === ';') {
                    $statement = trim($buffer);
                    if ($statement !== '') {
                        $statements[] = $statement;
                    }
                    $buffer = '';
                    continue;
                }
            } else {
                if ($character === '\\' && $quote !== '`' && $next !== '') {
                    $buffer .= $character . $next;
                    $index++;
                    continue;
                }
                if ($character === $quote) {
                    if ($next === $quote) {
                        $buffer .= $character . $next;
                        $index++;
                        continue;
                    }
                    $quote = null;
                }
            }

            $buffer .= $character;
        }

        if ($quote !== null || $blockComment) {
            throw new RuntimeException('Unterminated quote or comment in migration SQL.');
        }

        $tail = trim($buffer);
        if ($tail !== '') {
            $statements[] = $tail;
        }
        if ($statements === []) {
            throw new RuntimeException('Migration contains no executable SQL statements.');
        }
        return $statements;
    }
}
