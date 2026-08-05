<?php

declare(strict_types=1);

use FoxCMS\Api\Content\ContentApiApplication;
use FoxCMS\Api\Core\ApplicationContext;
use FoxCMS\Api\Core\Request;

require_once __DIR__ . '/autoload.php';

$context = ApplicationContext::boot(dirname(__DIR__));
(new ContentApiApplication($context, Request::fromGlobals()))->run();
