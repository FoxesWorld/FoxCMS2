<?php

declare(strict_types=1);

final class AuthFailure extends RuntimeException
{
    /** @var array<string, scalar> */
    private array $headers;
    /** @var array<string, mixed> */
    private array $expected;
    /** @var array<string, mixed> */
    private array $actual;
    /** @var array<string, mixed> */
    private array $auditContext;

    /**
     * @param array<string, scalar> $headers
     * @param array<string, mixed> $expected
     * @param array<string, mixed> $actual
     * @param array<string, mixed> $auditContext
     */
    public function __construct(
        string $message,
        private string $publicCode,
        private int $status = 400,
        private ?string $field = null,
        private string $responseType = 'error',
        array $headers = [],
        private string $severity = 'notice',
        array $expected = [],
        array $actual = [],
        array $auditContext = [],
        ?Throwable $previous = null,
    ) {
        if ($status < 400 || $status > 499) {
            throw new InvalidArgumentException('Authentication failures must use an HTTP 4xx status.');
        }
        if (preg_match('/^[a-z][a-z0-9_]{2,63}$/D', $publicCode) !== 1) {
            throw new InvalidArgumentException('Invalid authentication failure code.');
        }
        if ($field !== null && preg_match('/^[A-Za-z][A-Za-z0-9_.-]{0,63}$/D', $field) !== 1) {
            throw new InvalidArgumentException('Invalid authentication failure field.');
        }
        if (!in_array($responseType, ['error', 'warning', 'warn'], true)) {
            throw new InvalidArgumentException('Invalid authentication response type.');
        }
        if (!in_array($severity, ['notice', 'warning', 'critical'], true)) {
            throw new InvalidArgumentException('Invalid authentication failure severity.');
        }

        parent::__construct($message, $status, $previous);
        $this->headers = $headers;
        $this->expected = $expected;
        $this->actual = $actual;
        $this->auditContext = $auditContext;
    }

    public function publicCode(): string
    {
        return $this->publicCode;
    }

    public function status(): int
    {
        return $this->status;
    }

    public function field(): ?string
    {
        return $this->field;
    }

    public function severity(): string
    {
        return $this->severity;
    }

    /** @return array<string, scalar> */
    public function headers(): array
    {
        return $this->headers;
    }

    /** @return array<string, mixed> */
    public function expected(): array
    {
        return $this->expected;
    }

    /** @return array<string, mixed> */
    public function actual(): array
    {
        return $this->actual;
    }

    /** @return array<string, mixed> */
    public function auditContext(): array
    {
        return $this->auditContext;
    }

    /** @return array<string, mixed> */
    public function payload(): array
    {
        $payload = [
            'type' => $this->responseType,
            'code' => $this->publicCode,
            'message' => $this->getMessage(),
        ];
        if ($this->field !== null) {
            $payload['field'] = $this->field;
        }
        return $payload;
    }
}
