<?php

declare(strict_types=1);

use FoxCMS\Api\Core\Request;
use FoxCMS\Api\Launcher\FileCatalogController;

$foxRootDirectory = dirname(__DIR__, 2);
require_once $foxRootDirectory . '/engine/EmergencyRuntimeHandler.php';
EmergencyRuntimeHandler::register($foxRootDirectory);
require_once dirname(__DIR__) . '/autoload.php';

(new FileCatalogController($foxRootDirectory, Request::fromGlobals()))->run();
