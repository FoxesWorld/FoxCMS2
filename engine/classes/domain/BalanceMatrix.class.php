<?php

declare(strict_types=1);

final class BalanceMatrix
{
    public const VERSION = 1;
    public const MAX_AMOUNT = 9_007_199_254_740_991;

    private const CURRENCIES = [
        'units' => [
            'name' => 'Units',
            'symbol' => 'U',
            'primary' => true,
        ],
        'crystals' => [
            'name' => 'Crystals',
            'symbol' => 'C',
            'primary' => false,
        ],
    ];

    public static function defaults(): array
    {
        return self::matrix([]);
    }

    public static function normalize(mixed $value, bool $strict = false): array
    {
        $decoded = self::decode($value, $strict);
        if (!is_array($decoded)) {
            if ($strict) {
                throw new InvalidArgumentException('Balance matrix must be a JSON object or array.');
            }
            return self::defaults();
        }

        $source = $decoded['currencies'] ?? $decoded['matrix'] ?? $decoded;
        if (!is_array($source)) {
            if ($strict) {
                throw new InvalidArgumentException('Balance matrix must contain a currencies collection.');
            }
            return self::defaults();
        }

        $amounts = [];
        if (array_is_list($source)) {
            foreach ($source as $entry) {
                if (!is_array($entry)) {
                    continue;
                }
                $code = self::normalizeCode($entry['code'] ?? $entry['id'] ?? $entry['name'] ?? $entry['label'] ?? '');
                if ($code !== '') {
                    $amounts[$code] = self::amount(
                        $entry['amount'] ?? $entry['value'] ?? $entry['balance'] ?? 0,
                        $strict,
                        $code,
                    );
                    continue;
                }

                // Historical FoxCMS balances used singleton objects:
                // [{"crystals": 200}, {"units": 1000}].
                foreach ($entry as $legacyCode => $legacyAmount) {
                    $code = self::normalizeCode($legacyCode);
                    if ($code === '') {
                        continue;
                    }
                    $amounts[$code] = self::amount($legacyAmount, $strict, $code);
                }
            }
        } else {
            foreach ($source as $key => $entry) {
                $code = self::normalizeCode(is_string($key) ? $key : '');
                if (is_array($entry)) {
                    $code = self::normalizeCode($entry['code'] ?? $entry['id'] ?? $entry['name'] ?? $code);
                    $entry = $entry['amount'] ?? $entry['value'] ?? $entry['balance'] ?? 0;
                }
                if ($code === '') {
                    continue;
                }
                $amounts[$code] = self::amount($entry, $strict, $code);
            }
        }

        return self::matrix($amounts);
    }

    public static function encode(mixed $value): string
    {
        return json_encode(
            self::normalize($value, true),
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
        );
    }

    private static function decode(mixed $value, bool $strict): mixed
    {
        if (!is_string($value)) {
            return $value;
        }

        $value = trim($value);
        if ($value === '') {
            return [];
        }

        try {
            return json_decode($value, true, 64, JSON_THROW_ON_ERROR);
        } catch (JsonException $error) {
            if ($strict) {
                throw new InvalidArgumentException('Balance matrix contains invalid JSON.', 0, $error);
            }
            return [];
        }
    }

    private static function normalizeCode(mixed $value): string
    {
        if (!is_string($value) && !is_numeric($value)) {
            return '';
        }
        $code = strtolower(trim((string)$value));
        $code = preg_replace('/[^a-z0-9]+/', '', $code) ?? '';
        return match ($code) {
            'unit', 'units' => 'units',
            'crystal', 'crystals' => 'crystals',
            default => '',
        };
    }

    private static function amount(mixed $value, bool $strict, string $code): int
    {
        if (is_string($value) && preg_match('/^\d+$/D', trim($value)) === 1) {
            $value = (int)trim($value);
        }
        if (is_float($value) && floor($value) === $value) {
            $value = (int)$value;
        }
        if (!is_int($value) || $value < 0 || $value > self::MAX_AMOUNT) {
            if ($strict) {
                throw new InvalidArgumentException('Balance amount for ' . $code . ' must be a non-negative safe integer.');
            }
            return 0;
        }
        return $value;
    }

    private static function matrix(array $amounts): array
    {
        $currencies = [];
        foreach (self::CURRENCIES as $code => $definition) {
            $currencies[] = [
                'code' => $code,
                'name' => $definition['name'],
                'amount' => $amounts[$code] ?? 0,
                'symbol' => $definition['symbol'],
                'primary' => $definition['primary'],
            ];
        }
        return [
            'version' => self::VERSION,
            'currencies' => $currencies,
        ];
    }
}
