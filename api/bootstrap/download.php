<?php

declare(strict_types=1);

use FoxCMS\Api\Bootstrap\BootstrapConfig;
use FoxCMS\Api\Bootstrap\DownloadController;

require_once dirname(__DIR__) . '/autoload.php';

$config = BootstrapConfig::load(dirname(__DIR__, 2));
(new DownloadController($config))->run();
