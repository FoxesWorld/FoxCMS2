<?php

declare(strict_types=1);

if (!defined('FOXXEY')) {
    http_response_code(403);
    exit('{"message":"Not in FOXXEY thread"}');
}

final class DatabaseException extends RuntimeException
{
}

/**
 * PDO compatibility facade for the existing FoxCMS modules.
 *
 * The connection is instance-owned. No static/global PDO handle is retained,
 * and database failures are delegated to the application error handler.
 */
final class db
{
    private PDO $pdo;

    public function __construct(
        string $dbUser,
        string $dbPass,
        string $dbName,
        string $dbHost = '127.0.0.1',
        int $dbPort = 3306,
        string $charset = 'utf8mb4',
        int $connectTimeout = 5,
    ) {
        if ($dbName === '' || $dbUser === '') {
            throw new InvalidArgumentException('Database name and user are required.');
        }
        if (preg_match('/^[A-Za-z0-9_]+$/', $charset) !== 1) {
            throw new InvalidArgumentException('Invalid database charset.');
        }

        $dsn = str_starts_with($dbHost, '/')
            ? sprintf('mysql:unix_socket=%s;dbname=%s;charset=%s', $dbHost, $dbName, $charset)
            : sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $dbHost, $dbPort, $dbName, $charset);

        try {
            $this->pdo = new PDO($dsn, $dbUser, $dbPass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_STRINGIFY_FETCHES => false,
                PDO::ATTR_PERSISTENT => false,
                PDO::ATTR_TIMEOUT => max(1, min(30, $connectTimeout)),
            ]);
            $this->pdo->exec(
                "SET SESSION sql_mode = 'STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'"
            );
        } catch (PDOException $exception) {
            throw $this->convertException('connection', $exception);
        }
    }

    public function pdo(): PDO
    {
        return $this->pdo;
    }

    public function prepare(string $statement, array $driverOptions = []): PDOStatement
    {
        try {
            return $this->pdo->prepare($statement, $driverOptions);
        } catch (PDOException $exception) {
            throw $this->convertException('prepare', $exception);
        }
    }

    public function query(string $statement): PDOStatement|false
    {
        try {
            return $this->pdo->query($statement);
        } catch (PDOException $exception) {
            throw $this->convertException('query', $exception);
        }
    }

    public function exec(string $statement): int|false
    {
        try {
            return $this->pdo->exec($statement);
        } catch (PDOException $exception) {
            throw $this->convertException('execution', $exception);
        }
    }

    public function run(string $statement, array $arguments = []): PDOStatement
    {
        $prepared = $this->prepare($statement);
        try {
            $prepared->execute($arguments);
        } catch (PDOException $exception) {
            throw $this->convertException('statement execution', $exception);
        }
        return $prepared;
    }

    public function getRow(string $statement, array $arguments = []): array|false
    {
        return $this->run($statement, $arguments)->fetch(PDO::FETCH_ASSOC);
    }

    /** @return list<array<string, mixed>> */
    public function getRows(string $statement, array $arguments = []): array
    {
        return $this->run($statement, $arguments)->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getValue(string $statement, array $arguments = []): mixed
    {
        $value = $this->run($statement, $arguments)->fetchColumn();
        return $value === false ? null : $value;
    }

    /** @return list<mixed> */
    public function getColumn(string $statement, array $arguments = []): array
    {
        return $this->run($statement, $arguments)->fetchAll(PDO::FETCH_COLUMN);
    }

    public function beginTransaction(): bool
    {
        return $this->pdo->beginTransaction();
    }

    public function commit(): bool
    {
        return $this->pdo->commit();
    }

    public function rollBack(): bool
    {
        return $this->pdo->rollBack();
    }

    public function inTransaction(): bool
    {
        return $this->pdo->inTransaction();
    }

    public function lastInsertId(?string $name = null): string|false
    {
        return $this->pdo->lastInsertId($name);
    }

    public function transactional(callable $operation): mixed
    {
        $this->beginTransaction();
        try {
            $result = $operation($this);
            $this->commit();
            return $result;
        } catch (Throwable $exception) {
            if ($this->inTransaction()) {
                $this->rollBack();
            }
            throw $exception;
        }
    }

    /**
     * Retained only for old call sites while they are migrated to parameters.
     */
    public function safesql(string $value): string
    {
        $quoted = $this->pdo->quote($value);
        return $quoted === false ? "''" : $quoted;
    }

    /** @return never */
    public static function display_error(string $error = 'Database operation failed', int|string $errorNumber = 0): void
    {
        throw new DatabaseException(
            sprintf('Database operation failed [%s]: %s', (string)$errorNumber, trim($error)),
        );
    }

    private function convertException(string $operation, PDOException $exception): DatabaseException
    {
        $sqlState = is_array($exception->errorInfo ?? null)
            ? (string)($exception->errorInfo[0] ?? 'unknown')
            : (string)$exception->getCode();

        return new DatabaseException(
            sprintf('Database %s failed [SQLSTATE %s]: %s', $operation, $sqlState, $exception->getMessage()),
            0,
            $exception,
        );
    }
}
