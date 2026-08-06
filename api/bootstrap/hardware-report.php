<?php

declare(strict_types=1);

/** @deprecated Use FoxCMS\Api\Bootstrap\HardwareReport through api/autoload.php. */
$foxRootDirectory = dirname(__DIR__, 2);
require_once $foxRootDirectory . '/engine/EmergencyRuntimeHandler.php';
EmergencyRuntimeHandler::register($foxRootDirectory);
require_once dirname(__DIR__) . '/autoload.php';
