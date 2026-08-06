<?php

declare(strict_types=1);

$rootDirectory = dirname(__DIR__);
require_once $rootDirectory . '/engine/EmergencyRuntimeHandler.php';
EmergencyRuntimeHandler::register($rootDirectory);

require_once __DIR__ . '/AuthlibRuntime.class.php';
require_once __DIR__ . '/AuthlibSessionRepository.class.php';
require_once __DIR__ . '/AuthlibProfileService.class.php';

return AuthlibRuntime::boot();
