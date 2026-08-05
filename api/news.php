<?php

declare(strict_types=1);

use FoxCMS\Api\Core\ApplicationContext;
use FoxCMS\Api\Core\Request;
use FoxCMS\Api\News\NewsApiApplication;

require_once __DIR__ . '/autoload.php';

$context = ApplicationContext::boot(dirname(__DIR__));
(new NewsApiApplication($context, Request::fromGlobals()))->run();
