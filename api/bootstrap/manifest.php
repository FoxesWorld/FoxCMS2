<?php

declare(strict_types=1);

use FoxCMS\Api\Bootstrap\BootstrapConfig;
use FoxCMS\Api\Bootstrap\ManifestController;
use FoxCMS\Api\Core\Request;

require_once dirname(__DIR__) . '/autoload.php';

$config = BootstrapConfig::load(dirname(__DIR__, 2));
(new ManifestController($config, Request::fromGlobals()))->run();
