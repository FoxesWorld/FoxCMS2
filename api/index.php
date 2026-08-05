<?php

declare(strict_types=1);

use FoxCMS\Api\Core\ApplicationContext;
use FoxCMS\Api\Core\Request;
use FoxCMS\Api\Game\GameApiApplication;

require_once __DIR__ . '/autoload.php';

$context = ApplicationContext::boot(dirname(__DIR__));
(new GameApiApplication($context, Request::fromGlobals()))->run();
