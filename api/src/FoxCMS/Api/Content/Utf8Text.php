<?php

declare(strict_types=1);

namespace FoxCMS\Api\Content;

use UnexpectedValueException;

final class Utf8Text
{
    public static function normalize(mixed $value): string
    {
        $text = str_replace("\0", '', (string)$value);
        if (preg_match('//u', $text) !== 1) {
            throw new UnexpectedValueException('Content data must be valid UTF-8.');
        }
        return $text;
    }
}
