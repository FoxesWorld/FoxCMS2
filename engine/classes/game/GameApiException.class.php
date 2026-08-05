<?php

declare(strict_types=1);

final class GameApiException extends RuntimeException
{
    public function __construct(
        private string $errorCode,
        string $message,
        private int $statusCode,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public function errorCode(): string
    {
        return $this->errorCode;
    }

    public function statusCode(): int
    {
        return $this->statusCode;
    }
}
