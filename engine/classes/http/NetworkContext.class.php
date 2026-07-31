<?php

declare(strict_types=1);

final class NetworkContext
{
    /**
     * @param list<string> $trustedProxies
     */
    public function __construct(
        private array $server,
        private array $trustedProxies = [],
    ) {
    }

    /**
     * @param list<string> $trustedProxies
     */
    public static function fromGlobals(array $trustedProxies = []): self
    {
        return new self($_SERVER, $trustedProxies);
    }

    public function remoteAddress(): string
    {
        $remote = trim((string)($this->server['REMOTE_ADDR'] ?? ''));
        return filter_var($remote, FILTER_VALIDATE_IP) !== false ? $remote : '127.0.0.1';
    }

    public function clientIp(): string
    {
        $remote = $this->remoteAddress();
        if (!$this->isTrustedProxy($remote)) {
            return $remote;
        }

        $forwarded = (string)($this->server['HTTP_X_FORWARDED_FOR'] ?? '');
        if ($forwarded === '') {
            return $remote;
        }

        $chain = [];
        foreach (explode(',', $forwarded) as $candidate) {
            $candidate = trim($candidate);
            if (filter_var($candidate, FILTER_VALIDATE_IP) !== false) {
                $chain[] = $candidate;
            }
        }
        $chain[] = $remote;

        for ($index = count($chain) - 1; $index >= 0; $index--) {
            if (!$this->isTrustedProxy($chain[$index])) {
                return $chain[$index];
            }
        }

        return $remote;
    }

    public function isSecure(): bool
    {
        $https = strtolower((string)($this->server['HTTPS'] ?? ''));
        if ($https === 'on' || $https === '1' || (int)($this->server['SERVER_PORT'] ?? 0) === 443) {
            return true;
        }

        if (!$this->isTrustedProxy($this->remoteAddress())) {
            return false;
        }

        $forwardedProto = strtolower(trim(explode(',', (string)($this->server['HTTP_X_FORWARDED_PROTO'] ?? ''))[0] ?? ''));
        if ($forwardedProto === 'https') {
            return true;
        }

        $forwarded = strtolower((string)($this->server['HTTP_FORWARDED'] ?? ''));
        return preg_match('/(?:^|[;,]\s*)proto=https(?:[;,]|$)/', $forwarded) === 1;
    }

    public function host(): string
    {
        $host = trim((string)($this->server['HTTP_HOST'] ?? $this->server['SERVER_NAME'] ?? 'localhost'));
        if (preg_match('/^[A-Za-z0-9.-]+(?::\d{1,5})?$/', $host) !== 1) {
            return 'localhost';
        }
        return strtolower($host);
    }

    public function scheme(): string
    {
        return $this->isSecure() ? 'https' : 'http';
    }

    public function origin(): string
    {
        return $this->scheme() . '://' . $this->host();
    }

    private function isTrustedProxy(string $ip): bool
    {
        foreach ($this->trustedProxies as $network) {
            if ($this->ipMatchesNetwork($ip, $network)) {
                return true;
            }
        }
        return false;
    }

    private function ipMatchesNetwork(string $ip, string $network): bool
    {
        $network = trim($network);
        if ($network === '') {
            return false;
        }
        if (!str_contains($network, '/')) {
            return hash_equals($network, $ip);
        }

        [$subnet, $prefixValue] = array_pad(explode('/', $network, 2), 2, '');
        $ipBinary = @inet_pton($ip);
        $subnetBinary = @inet_pton($subnet);
        if ($ipBinary === false || $subnetBinary === false || strlen($ipBinary) !== strlen($subnetBinary)) {
            return false;
        }

        $maxPrefix = strlen($ipBinary) * 8;
        if (filter_var($prefixValue, FILTER_VALIDATE_INT) === false) {
            return false;
        }
        $prefix = (int)$prefixValue;
        if ($prefix < 0 || $prefix > $maxPrefix) {
            return false;
        }

        $wholeBytes = intdiv($prefix, 8);
        $remainingBits = $prefix % 8;
        if ($wholeBytes > 0 && substr($ipBinary, 0, $wholeBytes) !== substr($subnetBinary, 0, $wholeBytes)) {
            return false;
        }
        if ($remainingBits === 0) {
            return true;
        }

        $mask = (0xFF << (8 - $remainingBits)) & 0xFF;
        return (ord($ipBinary[$wholeBytes]) & $mask) === (ord($subnetBinary[$wholeBytes]) & $mask);
    }
}
