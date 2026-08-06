<?php

declare(strict_types=1);

require_once __DIR__ . '/engine/EmergencyRuntimeHandler.php';
EmergencyRuntimeHandler::register(__DIR__);

/** @var Application $application */
$application = require __DIR__ . '/engine/bootstrap.php';
$application->run();
