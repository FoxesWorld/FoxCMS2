<?php

declare(strict_types=1);

use FoxCMS\Api\Content\ContentApiApplication;
use FoxCMS\Api\Core\ApplicationContext;
use FoxCMS\Api\Core\Request;

$rootDirectory = dirname(__DIR__);
require_once $rootDirectory . '/engine/EmergencyRuntimeHandler.php';
EmergencyRuntimeHandler::register($rootDirectory);
require_once __DIR__ . '/autoload.php';

$context = ApplicationContext::boot($rootDirectory);
(new ContentApiApplication($context, Request::fromGlobals()))->run();
