<?php

declare(strict_types=1);

namespace FoxCMS\Api\Bootstrap;

/** Immutable, privacy-bounded hardware capability report. */
final class HardwareReport
{
    /** @param array<string, mixed> $payload */
    public function __construct(private readonly array $payload)
    {
    }

    public function systemHwid(): string
    {
        return (string)$this->payload['systemHWID'];
    }

    public function schemaVersion(): int
    {
        return (int)$this->payload['schemaVersion'];
    }

    public function platform(): string
    {
        return (string)$this->payload['platform'];
    }

    public function updaterVersion(): string
    {
        return (string)$this->payload['updaterVersion'];
    }

    public function osName(): string
    {
        return (string)$this->payload['systemInformation']['os']['name'];
    }

    public function osVersion(): ?string
    {
        return $this->payload['systemInformation']['os']['version'];
    }

    public function kernelVersion(): ?string
    {
        return $this->payload['systemInformation']['os']['kernel'];
    }

    public function architecture(): string
    {
        return (string)$this->payload['systemInformation']['os']['architecture'];
    }

    public function cpuBrand(): ?string
    {
        return $this->payload['systemInformation']['cpu']['brand'];
    }

    public function logicalCpuCount(): int
    {
        return (int)$this->payload['systemInformation']['cpu']['logicalCores'];
    }

    public function memoryBytes(): int
    {
        return (int)$this->payload['systemInformation']['memory']['totalBytes'];
    }

    /** @return list<string> */
    public function gpuAdapters(): array
    {
        return $this->payload['systemInformation']['gpu']['adapters'];
    }

    /** @return array<string, mixed> */
    public function payload(): array
    {
        return $this->payload;
    }
}
