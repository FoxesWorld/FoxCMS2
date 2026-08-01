<?php

declare(strict_types=1);

final class UploadException extends RuntimeException
{
    public function __construct(
        string $message,
        private int $httpStatus = 400,
        private array $auditContext = [],
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
        $this->httpStatus = max(400, min(599, $httpStatus));
    }

    public function httpStatus(): int
    {
        return $this->httpStatus;
    }

    public function auditContext(): array
    {
        return $this->auditContext;
    }
}
