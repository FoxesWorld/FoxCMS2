<?php

declare(strict_types=1);

namespace FoxCMS\Engine\Application;

final class ApplicationContext
{
    public function __construct(
        public readonly \db $db,
        public readonly \Logger $logger,
        public readonly \HttpRequest $request,
        public readonly \UserSession $session,
        public readonly \ModulesLoader $modules,
        public readonly array $config,
    ) {
    }
}
