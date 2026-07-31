<?php

declare(strict_types=1);

final class RememberToken
{
    private const VERSION = 'v1';

    /** @return array{token:string, digest:string, expiresAt:int} */
    public static function issue(int $ttlSeconds): array
    {
        $issuedAt = time();
        $ttlSeconds = max(3600, $ttlSeconds);
        $random = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        $token = self::VERSION . '.' . dechex($issuedAt) . '.' . $random;

        return [
            'token' => $token,
            'digest' => self::digest($token),
            'expiresAt' => $issuedAt + $ttlSeconds,
        ];
    }

    public static function digest(string $token): string
    {
        return hash('sha256', $token);
    }

    public static function isUsable(string $token, int $ttlSeconds, int $now = 0): bool
    {
        $issuedAt = self::issuedAt($token);
        if ($issuedAt === null) {
            return false;
        }

        $now = $now > 0 ? $now : time();
        $ttlSeconds = max(3600, $ttlSeconds);
        return $issuedAt <= $now + 300 && $issuedAt >= $now - $ttlSeconds;
    }

    public static function issuedAt(string $token): ?int
    {
        if (preg_match('/^v1\.([a-f0-9]{8,16})\.([A-Za-z0-9_-]{40,64})$/D', $token, $matches) !== 1) {
            return null;
        }

        $issuedAt = hexdec($matches[1]);
        return is_int($issuedAt) && $issuedAt > 0 ? $issuedAt : null;
    }
}
