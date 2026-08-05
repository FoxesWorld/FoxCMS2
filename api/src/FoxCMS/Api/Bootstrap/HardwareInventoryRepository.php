<?php

declare(strict_types=1);

namespace FoxCMS\Api\Bootstrap;

use PDO;
use RuntimeException;

/** Idempotent persistence for anonymous bootstrap hardware reports. */
final class HardwareInventoryRepository
{
    /** @var array<string, mixed> */
    private array $databaseConfig;
    private ?PDO $connection = null;

    /** @param array<string, mixed> $databaseConfig */
    public function __construct(array $databaseConfig)
    {
        $this->databaseConfig = $databaseConfig;
    }

    /**
     * Insert exactly once for a systemHWID. Existing records are intentionally
     * left unchanged so the first-seen inventory remains auditable.
     */
    public function insertIfMissing(HardwareReport $report): bool
    {
        $payloadJson = json_encode(
            $report->payload(),
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
        );
        $gpuJson = json_encode(
            $report->gpuAdapters(),
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
        );

        $statement = $this->connection()->prepare(
            'INSERT IGNORE INTO `system_hardware_inventory` '
            . '(`systemHWID`, `schemaVersion`, `updaterVersion`, `platform`, `osName`, `osVersion`, '
            . '`kernelVersion`, `architecture`, `cpuBrand`, `logicalCpuCount`, `memoryBytes`, '
            . '`gpuAdapters`, `report`) '
            . 'VALUES (:systemHWID, :schemaVersion, :updaterVersion, :platform, :osName, :osVersion, '
            . ':kernelVersion, :architecture, :cpuBrand, :logicalCpuCount, :memoryBytes, '
            . ':gpuAdapters, :report)'
        );
        $statement->bindValue(':systemHWID', $report->systemHwid(), PDO::PARAM_STR);
        $statement->bindValue(':schemaVersion', $report->schemaVersion(), PDO::PARAM_INT);
        $statement->bindValue(':updaterVersion', $report->updaterVersion(), PDO::PARAM_STR);
        $statement->bindValue(':platform', $report->platform(), PDO::PARAM_STR);
        $statement->bindValue(':osName', $report->osName(), PDO::PARAM_STR);
        $statement->bindValue(':osVersion', $report->osVersion(), $report->osVersion() === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $statement->bindValue(':kernelVersion', $report->kernelVersion(), $report->kernelVersion() === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $statement->bindValue(':architecture', $report->architecture(), PDO::PARAM_STR);
        $statement->bindValue(':cpuBrand', $report->cpuBrand(), $report->cpuBrand() === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $statement->bindValue(':logicalCpuCount', $report->logicalCpuCount(), PDO::PARAM_INT);
        $statement->bindValue(':memoryBytes', (string)$report->memoryBytes(), PDO::PARAM_STR);
        $statement->bindValue(':gpuAdapters', $gpuJson, PDO::PARAM_STR);
        $statement->bindValue(':report', $payloadJson, PDO::PARAM_STR);
        $statement->execute();

        return $statement->rowCount() === 1;
    }

    private function connection(): PDO
    {
        if ($this->connection instanceof PDO) {
            return $this->connection;
        }

        $host = $this->requiredConfigString('host');
        $name = $this->requiredConfigString('name');
        $user = $this->requiredConfigString('user');
        $password = (string)($this->databaseConfig['password'] ?? '');
        $port = (int)($this->databaseConfig['port'] ?? 3306);
        $timeout = (int)($this->databaseConfig['connect_timeout'] ?? 5);
        if ($port < 1 || $port > 65535 || $timeout < 1 || $timeout > 30) {
            throw new RuntimeException('Bootstrap hardware inventory database configuration is invalid.');
        }

        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $host, $port, $name);
        $this->connection = new PDO($dsn, $user, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_STRINGIFY_FETCHES => false,
            PDO::ATTR_TIMEOUT => $timeout,
        ]);

        return $this->connection;
    }

    private function requiredConfigString(string $field): string
    {
        $value = trim((string)($this->databaseConfig[$field] ?? ''));
        if ($value === '' || str_contains($value, "\0")) {
            throw new RuntimeException('Bootstrap hardware inventory database configuration is incomplete.');
        }
        return $value;
    }
}
