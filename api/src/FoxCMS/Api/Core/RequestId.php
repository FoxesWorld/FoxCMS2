<?php

declare(strict_types=1);

namespace FoxCMS\Api\Core;

use Throwable;

final class RequestId
{
    public static function create(string $prefix = ''): string
    {
        try {
            return $prefix . bin2hex(random_bytes(8));
        } catch (Throwable) {
            return str_replace('.', '', uniqid($prefix, true));
        }
    }
}
