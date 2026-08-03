<?php

declare(strict_types=1);

final class TraceContext implements JsonSerializable
{
    private const ID_PATTERN = '/^[A-Za-z0-9_.:-]{8,96}$/D';

    private function __construct(
        private string $requestId,
        private string $correlationId,
        private int $startedAtNanoseconds,
        private string $method,
        private string $path,
        private string $clientIpHash,
        private string $userAgentHash,
        private string $actorUuid,
        private string $actorLogin,
        private string $actorGroup,
    ) {
    }

    public static function create(
        HttpRequest $request,
        UserSession $session,
        string $fingerprintKey = '',
    ): self {
        $requestId = self::generateId('req');
        $correlationId = self::acceptedId($request->header('X-Correlation-ID'))
            ?? self::acceptedId($request->header('X-Request-ID'))
            ?? $requestId;
        $clientIp = trim($request->clientIp());
        $userAgent = trim($request->userAgent());

        return new self(
            $requestId,
            $correlationId,
            hrtime(true),
            $request->method(),
            self::safePath($request->path()),
            self::fingerprint($clientIp, $fingerprintKey),
            self::fingerprint($userAgent, $fingerprintKey),
            $session->uuid(),
            $session->login(),
            $session->group(),
        );
    }

    public function requestId(): string
    {
        return $this->requestId;
    }

    public function correlationId(): string
    {
        return $this->correlationId;
    }

    public function durationMilliseconds(): float
    {
        return round(max(0, hrtime(true) - $this->startedAtNanoseconds) / 1_000_000, 3);
    }

    /** @return array<string, scalar> */
    public function logContext(): array
    {
        $context = [
            'requestId' => $this->requestId,
            'correlationId' => $this->correlationId,
            'httpMethod' => $this->method,
            'httpPath' => $this->path,
            'actorUuid' => $this->actorUuid,
            'actorLogin' => $this->actorLogin,
            'actorGroup' => $this->actorGroup,
        ];
        if ($this->clientIpHash !== '') {
            $context['clientIpHash'] = $this->clientIpHash;
        }
        if ($this->userAgentHash !== '') {
            $context['userAgentHash'] = $this->userAgentHash;
        }
        return $context;
    }

    public function jsonSerialize(): array
    {
        return $this->logContext();
    }

    private static function acceptedId(string $candidate): ?string
    {
        $candidate = trim($candidate);
        return preg_match(self::ID_PATTERN, $candidate) === 1 ? $candidate : null;
    }

    private static function generateId(string $prefix): string
    {
        try {
            return $prefix . '-' . bin2hex(random_bytes(12));
        } catch (Throwable) {
            return $prefix . '-' . substr(hash('sha256', uniqid($prefix . '-', true)), 0, 24);
        }
    }

    private static function fingerprint(string $value, string $key): string
    {
        $key = trim($key);
        return $value === '' || strlen($key) < 16
            ? ''
            : substr(hash_hmac('sha256', $value, $key), 0, 20);
    }

    private static function safePath(string $path): string
    {
        $path = trim($path);
        if ($path === '' || str_contains($path, "\0")) {
            return '/';
        }
        return mb_substr($path, 0, 512);
    }
}
