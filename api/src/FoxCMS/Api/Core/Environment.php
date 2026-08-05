<?php

declare(strict_types=1);

namespace FoxCMS\Api\Core;

final class Environment
{
    public static function load(string $rootDirectory): string
    {
        $rootDirectory = rtrim($rootDirectory, '/\\');
        require_once $rootDirectory . '/engine/data/environment.php';
        \foxLoadEnv($rootDirectory . '/.env');
        return $rootDirectory;
    }
}
