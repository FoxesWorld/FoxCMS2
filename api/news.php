<?php

declare(strict_types=1);

use FoxCMS\Api\Core\ApplicationContext;
use FoxCMS\Api\Core\Request;
use FoxCMS\Api\News\NewsApiApplication;

$rootDirectory = dirname(__DIR__);
require_once $rootDirectory . '/engine/EmergencyRuntimeHandler.php';
EmergencyRuntimeHandler::register($rootDirectory);
require_once __DIR__ . '/autoload.php';

$context = ApplicationContext::boot($rootDirectory);
(new NewsApiApplication($context, Request::fromGlobals()))->run();
