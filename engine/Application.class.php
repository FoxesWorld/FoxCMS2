<?php

declare(strict_types=1);

/**
 * Backward-compatible entry point for the legacy bootstrap.
 * Runtime responsibilities live in the namespaced application kernel.
 */
final class Application
{
    private FoxCMS\Engine\Application\ApplicationKernel $kernel;

    public function __construct(array $config, NetworkContext $network, array $levels)
    {
        $this->kernel = new FoxCMS\Engine\Application\ApplicationKernel($config, $network, $levels);
    }

    public function run(): void
    {
        $this->kernel->run();
    }
}
