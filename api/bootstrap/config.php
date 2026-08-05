<?php

declare(strict_types=1);

use FoxCMS\Api\Bootstrap\BootstrapConfig;

require_once dirname(__DIR__) . '/autoload.php';

return BootstrapConfig::load(dirname(__DIR__, 2));
