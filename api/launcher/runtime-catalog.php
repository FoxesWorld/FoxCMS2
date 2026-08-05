<?php

declare(strict_types=1);

use FoxCMS\Api\Core\Request;
use FoxCMS\Api\Launcher\RuntimeCatalogController;

require_once dirname(__DIR__) . '/autoload.php';

(new RuntimeCatalogController(dirname(__DIR__, 2), Request::fromGlobals()))->run();
