<?php

declare(strict_types=1);

final class OperationTrace
{
    private int $startedAtNanoseconds;
    private bool $finished = false;

    private function __construct(
        private Logger $logger,
        private string $event,
        private array $context,
        private int $normalDurationMilliseconds,
    ) {
        $this->startedAtNanoseconds = hrtime(true);
    }

    public static function begin(
        Logger $logger,
        string $event,
        array $context = [],
        int $normalDurationMilliseconds = 500,
    ): self {
        return new self(
            $logger,
            $event,
            $context,
            max(1, $normalDurationMilliseconds),
        );
    }

    public function success(string $message = 'Operation completed.', array $context = []): void
    {
        if ($this->finished) {
            return;
        }
        $this->finished = true;
        $duration = $this->durationMilliseconds();
        $payload = array_merge($this->context, $context, [
            'operation' => $this->event,
            'durationMs' => $duration,
        ]);

        if ($duration > $this->normalDurationMilliseconds) {
            $this->logger->deviation(
                $this->event . '.slow',
                'operation_duration_exceeded',
                $message,
                'warning',
                ['maximumDurationMs' => $this->normalDurationMilliseconds],
                ['durationMs' => $duration],
                $payload,
            );
        }
        $this->logger->event(
            $this->event . '.completed',
            $message,
            $payload,
            'INFO',
            'success',
        );
    }

    public function rejected(
        string $code,
        string $message,
        string $severity = 'notice',
        array $expected = [],
        array $actual = [],
        array $context = [],
    ): void {
        if ($this->finished) {
            return;
        }
        $this->finished = true;
        $this->logger->deviation(
            $this->event . '.rejected',
            $code,
            $message,
            $severity,
            $expected,
            $actual,
            array_merge($this->context, $context, [
                'operation' => $this->event,
                'durationMs' => $this->durationMilliseconds(),
            ]),
        );
    }

    public function failed(Throwable $error, string $message = 'Operation failed.', array $context = []): void
    {
        if ($this->finished) {
            return;
        }
        $this->finished = true;
        $this->logger->exception(
            $this->event . '.failed',
            $error,
            $message,
            array_merge($this->context, $context, [
                'operation' => $this->event,
                'durationMs' => $this->durationMilliseconds(),
            ]),
        );
    }

    public function durationMilliseconds(): float
    {
        return round(max(0, hrtime(true) - $this->startedAtNanoseconds) / 1_000_000, 3);
    }
}
