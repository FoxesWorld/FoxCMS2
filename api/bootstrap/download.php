<?php

declare(strict_types=1);

use FoxCMS\Api\Bootstrap\BootstrapConfig;
use FoxCMS\Api\Bootstrap\DownloadController;

$foxRootDirectory = dirname(__DIR__, 2);
require_once $foxRootDirectory . '/engine/EmergencyRuntimeHandler.php';
EmergencyRuntimeHandler::register($foxRootDirectory);
require_once dirname(__DIR__) . '/autoload.php';

$config = BootstrapConfig::load($foxRootDirectory);
(new DownloadController($config))->run();
