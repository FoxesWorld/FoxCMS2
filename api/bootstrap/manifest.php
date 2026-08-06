<?php

declare(strict_types=1);

use FoxCMS\Api\Bootstrap\BootstrapConfig;
use FoxCMS\Api\Bootstrap\ManifestController;
use FoxCMS\Api\Core\Request;

$foxRootDirectory = dirname(__DIR__, 2);
require_once $foxRootDirectory . '/engine/EmergencyRuntimeHandler.php';
EmergencyRuntimeHandler::register($foxRootDirectory);
require_once dirname(__DIR__) . '/autoload.php';

$config = BootstrapConfig::load($foxRootDirectory);
(new ManifestController($config, Request::fromGlobals()))->run();
