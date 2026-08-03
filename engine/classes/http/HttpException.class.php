<?php

declare(strict_types=1);

final class HttpException extends RuntimeException
{
    /** @var array<string, scalar> */
    private array $headers;

    /**
     * @param array<string, scalar> $headers
     */
    public function __construct(
        string $message,
        private int $status,
        array $headers = [],
        ?Throwable $previous = null,
    ) {
        if ($status < 400 || $status > 599) {
            throw new InvalidArgumentException('HTTP exception status must be between 400 and 599.');
        }

        parent::__construct($message, $status, $previous);
        $this->headers = $headers;
    }

    public function status(): int
    {
        return $this->status;
    }

    /** @return array<string, scalar> */
    public function headers(): array
    {
        return $this->headers;
    }
}
