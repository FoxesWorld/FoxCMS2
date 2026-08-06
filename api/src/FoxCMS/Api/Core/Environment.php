<?php

declare(strict_types=1);

namespace FoxCMS\Api\Core;

use FoxCMS\Shared\Environment\Environment as SharedEnvironment;

/** @deprecated Inject SharedEnvironment through ApplicationContext. */
final class Environment
{
    public static function load(string $rootDirectory): string
    {
        return SharedEnvironment::boot($rootDirectory)->rootDirectory();
    }
}
