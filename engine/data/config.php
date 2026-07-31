<?php

declare(strict_types=1);

require_once ENGINE_DIR . 'classes/config/AppConfigFactory.class.php';

return AppConfigFactory::fromEnvironment();
