<?php

declare(strict_types=1);

use FoxCMS\Shared\Environment\Environment;

require_once dirname(__DIR__, 2) . '/autoload.php';

/** @deprecated Inject Environment into new code. */
function foxLoadEnv(string $path): void
{
    try {
        $environment = Environment::current();
    } catch (RuntimeException) {
        $environment = Environment::boot(dirname(__DIR__, 2));
    }
    $environment->loadFile($path);
}

/** @deprecated Inject Environment into new code. */
function foxEnv(string $name, ?string $default = null): ?string
{
    return Environment::current()->string($name, $default);
}

/** @deprecated Inject Environment into new code. */
function foxEnvBool(string $name, bool $default = false): bool
{
    return Environment::current()->boolean($name, $default);
}

/** @deprecated Inject Environment into new code. */
function foxEnvInt(string $name, int $default): int
{
    return Environment::current()->integer($name, $default);
}
