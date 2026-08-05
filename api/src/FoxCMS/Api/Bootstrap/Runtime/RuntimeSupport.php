<?php

declare(strict_types=1);

namespace FoxCMS\Api\Bootstrap\Runtime;

use FoxCMS\Api\Bootstrap\ArtifactCatalog;
use FoxCMS\Api\Core\HttpException;

/** @param array<string, mixed> $details */

final class RuntimeSupport
{
    public static function fail(int $statusCode, string $errorCode, string $message, array $details = []): never
    {
        throw new HttpException($statusCode, $errorCode, $message, $details);
    }

    /** @return array<string, mixed> */
    public static function describeCatalogFile(string $storageDirectory, string $absolutePath): array
    {
        return (new ArtifactCatalog($storageDirectory))->describeFile($absolutePath);
    }
}
