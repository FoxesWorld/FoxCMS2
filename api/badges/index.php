<?php

declare(strict_types=1);

use FoxCMS\Api\Badge\BadgeApiApplication;
use FoxCMS\Api\Core\ApplicationContext;
use FoxCMS\Api\Core\Request;

$rootDirectory = dirname(__DIR__, 2);
require_once $rootDirectory . '/engine/EmergencyRuntimeHandler.php';
EmergencyRuntimeHandler::register($rootDirectory);
require_once dirname(__DIR__) . '/autoload.php';

$context = ApplicationContext::boot($rootDirectory);
(new BadgeApiApplication($context, Request::fromGlobals()))->run();
