<?php

declare(strict_types=1);

use FoxCMS\Api\Bootstrap\BootstrapConfig;

$foxRootDirectory = dirname(__DIR__, 2);
require_once $foxRootDirectory . '/engine/EmergencyRuntimeHandler.php';
EmergencyRuntimeHandler::register($foxRootDirectory);
require_once dirname(__DIR__) . '/autoload.php';

return BootstrapConfig::load($foxRootDirectory);
