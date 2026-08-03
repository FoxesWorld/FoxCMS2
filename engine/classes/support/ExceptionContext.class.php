<?php

declare(strict_types=1);

final class ExceptionContext
{
    public static function requestId(string $prefix = 'error'): string
    {
        try {
            return bin2hex(random_bytes(8));
        } catch (Throwable) {
            return substr(hash('sha256', uniqid($prefix . '-', true)), 0, 16);
        }
    }

    public static function detail(Throwable $error, int $maximumLength = 3000): string
    {
        $detail = trim(str_replace(["\r", "\n", "\t"], ' ', $error->getMessage()));
        $detail = preg_replace('/\s+/u', ' ', $detail) ?? $detail;
        if ($detail === '') {
            $detail = 'Exception does not contain a message.';
        }
        return mb_substr($detail, 0, max(1, $maximumLength), 'UTF-8');
    }
}
