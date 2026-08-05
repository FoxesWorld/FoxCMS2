<?php

declare(strict_types=1);

namespace FoxCMS\Api\Bootstrap;

use FoxCMS\Api\Core\HttpException;
use JsonException;

final class HardwareReportRequestReader
{
    /** @return array<string, mixed> */
    public function read(int $maxBytes): array
    {
        $contentType = strtolower(trim((string)($_SERVER['CONTENT_TYPE'] ?? '')));
        if ($contentType !== '' && !str_starts_with($contentType, 'application/json')) {
            throw new HttpException(
                415,
                'hardware_report_content_type_invalid',
                'Hardware report requests must use application/json.',
            );
        }

        $declaredLength = filter_var($_SERVER['CONTENT_LENGTH'] ?? null, FILTER_VALIDATE_INT);
        if (is_int($declaredLength) && $declaredLength > $maxBytes) {
            throw new HttpException(413, 'hardware_report_too_large', 'Hardware report exceeds the permitted request size.');
        }

        $body = file_get_contents('php://input', false, null, 0, $maxBytes + 1);
        if (!is_string($body)) {
            throw new HttpException(400, 'hardware_report_unreadable', 'Hardware report request body cannot be read.');
        }
        if (strlen($body) > $maxBytes) {
            throw new HttpException(413, 'hardware_report_too_large', 'Hardware report exceeds the permitted request size.');
        }
        if (trim($body) === '') {
            throw new HttpException(422, 'hardware_report_required', 'Hardware report request body is required.');
        }

        try {
            $decoded = json_decode($body, true, 32, JSON_THROW_ON_ERROR);
        } catch (JsonException $error) {
            throw new HttpException(
                400,
                'hardware_report_json_invalid',
                'Hardware report contains invalid JSON.',
                previous: $error,
            );
        }
        if (!is_array($decoded) || array_is_list($decoded)) {
            throw new HttpException(422, 'hardware_report_object_required', 'Hardware report must be a JSON object.');
        }
        return $decoded;
    }
}
