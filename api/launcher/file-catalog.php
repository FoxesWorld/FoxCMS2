<?php

declare(strict_types=1);

use FoxCMS\Api\Core\Request;
use FoxCMS\Api\Launcher\FileCatalogController;

require_once dirname(__DIR__) . '/autoload.php';

(new FileCatalogController(dirname(__DIR__, 2), Request::fromGlobals()))->run();
