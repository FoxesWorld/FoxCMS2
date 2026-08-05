<?php

declare(strict_types=1);

namespace FoxCMS\Api\Core;

use RuntimeException;
use Throwable;

class HttpException extends RuntimeException
{
    /** @param array<string, mixed> $details */
    public function __construct(
        private readonly int $statusCode,
        private readonly string $errorCode,
        string $message,
        private readonly array $details = [],
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public function statusCode(): int
    {
        return $this->statusCode;
    }

    public function errorCode(): string
    {
        return $this->errorCode;
    }

    /** @return array<string, mixed> */
    public function details(): array
    {
        return $this->details;
    }
}
