<?php

declare(strict_types=1);

namespace FoxCMS\Api\Bootstrap;

use FoxCMS\Api\Bootstrap\Runtime\RuntimeResolver;

final class RuntimeCatalog
{
    /** @return array<string, mixed> */
    public function resolve(string $storageDirectory): array
    {
        return RuntimeResolver::resolveRuntimeForRequest($storageDirectory);
    }
}
