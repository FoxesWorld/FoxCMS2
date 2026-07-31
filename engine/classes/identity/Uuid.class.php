<?php

declare(strict_types=1);

/**
 * FoxCMS 128-bit user identity helper.
 *
 * Historical FoxEngine installations stored md5(login) as 32 hexadecimal
 * characters. Modern installations store canonical RFC-compatible UUIDv7
 * values. normalize() deliberately preserves the storage representation so a
 * legacy database key remains usable until migrations 003/004 replace it.
 */
final class Uuid
{
    private const CANONICAL_PATTERN = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/D';
    private const RFC_PATTERN = '/^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/D';
    private const COMPACT_PATTERN = '/^[0-9a-f]{32}$/D';

    public static function v7(): string
    {
        $milliseconds = (int)floor(microtime(true) * 1000);
        $bytes = array_values(unpack('C16', random_bytes(16)));

        for ($index = 5; $index >= 0; $index--) {
            $bytes[$index] = $milliseconds & 0xff;
            $milliseconds >>= 8;
        }

        $bytes[6] = ($bytes[6] & 0x0f) | 0x70;
        $bytes[8] = ($bytes[8] & 0x3f) | 0x80;

        $hex = implode('', array_map(
            static fn (int $byte): string => str_pad(dechex($byte), 2, '0', STR_PAD_LEFT),
            $bytes,
        ));

        return self::fromCompact($hex);
    }

    public static function isValid(string $value): bool
    {
        try {
            self::normalize($value);
            return true;
        } catch (InvalidArgumentException) {
            return false;
        }
    }

    public static function isRfcCompatible(string $value): bool
    {
        try {
            return preg_match(self::RFC_PATTERN, self::canonical($value)) === 1;
        } catch (InvalidArgumentException) {
            return false;
        }
    }

    /**
     * Normalizes case and whitespace while preserving the database key shape.
     */
    public static function normalize(string $value): string
    {
        $value = strtolower(trim($value));
        if (preg_match(self::CANONICAL_PATTERN, $value) === 1
            || preg_match(self::COMPACT_PATTERN, $value) === 1) {
            return $value;
        }

        throw new InvalidArgumentException('Invalid UUID identity.');
    }

    /**
     * Converts either supported representation to 8-4-4-4-12 form.
     */
    public static function canonical(string $value): string
    {
        $value = self::normalize($value);
        return preg_match(self::COMPACT_PATTERN, $value) === 1
            ? self::fromCompact($value)
            : $value;
    }

    public static function compact(string $value): string
    {
        return str_replace('-', '', self::normalize($value));
    }

    /** @return list<string> */
    public static function databaseCandidates(string $value): array
    {
        $normalized = self::normalize($value);
        return array_values(array_unique([
            $normalized,
            self::canonical($normalized),
            self::compact($normalized),
        ]));
    }

    public static function equals(string $left, string $right): bool
    {
        try {
            return hash_equals(self::compact($left), self::compact($right));
        } catch (InvalidArgumentException) {
            return false;
        }
    }

    private static function fromCompact(string $value): string
    {
        return substr($value, 0, 8)
            . '-' . substr($value, 8, 4)
            . '-' . substr($value, 12, 4)
            . '-' . substr($value, 16, 4)
            . '-' . substr($value, 20, 12);
    }
}
