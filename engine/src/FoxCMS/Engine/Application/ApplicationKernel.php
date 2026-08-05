<?php

declare(strict_types=1);

namespace FoxCMS\Engine\Application;

final class ApplicationKernel
{
    private ApplicationContext $context;

    public function __construct(
        array $config,
        \NetworkContext $network,
        private array $levels,
    ) {
        (new PhpSessionStarter())->start($config, $network);
        \FoxCMS\Engine\Bootstrap\LegacyLibraries::load(SYSLIB_DIR);
        $this->context = (new ApplicationContextFactory())->create($config, $network);
    }

    public function run(): void
    {
        \RequestTelemetry::annotate(['applicationPhase' => 'dispatch']);
        $maintenance = (new \MaintenanceModeRepository($this->context->db))->current();

        // Identity restoration must precede the maintenance policy so allowed
        // groups can establish their authenticated session before the gate.
        $this->context->modules->loadPriority(MODULES_DIR, 'preInit', ['AuthReg']);
        (new MaintenanceGate())->enforce($maintenance, $this->context);
        $this->context->modules->loadPriority(MODULES_DIR, 'preInit', null, ['AuthReg']);

        (new \SystemRequests(
            $this->context->db,
            $this->context->logger,
            $this->context->request,
            $this->context->session,
            $this->context->config,
        ))->requestListener();

        if (($this->levels['init'] ?? false) === true) {
            $this->context->modules->loadPriority(MODULES_DIR, 'primary');
        }
        if (($this->levels['postInit'] ?? false) === true) {
            $this->context->modules->loadPriority(MODULES_DIR, 'secondary');
        }
        if (($this->levels['GFX'] ?? false) === true) {
            (new FrontendResponder())->render($this->context);
        }
    }
}
