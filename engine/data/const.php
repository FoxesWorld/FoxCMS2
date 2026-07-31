<?php

declare(strict_types=1);

$documentRoot = dirname(__DIR__, 2);
$networkContext = $GLOBALS['foxNetworkContext'] ?? null;
if (!$networkContext instanceof NetworkContext) {
    throw new RuntimeException('Network context must be initialized before engine constants.');
}

define('ROOT_DIR', $documentRoot);
define('ENGINE_DIR', ROOT_DIR . DIRECTORY_SEPARATOR . 'engine' . DIRECTORY_SEPARATOR);
define('CACHE_DIR', ENGINE_DIR . 'cache' . DIRECTORY_SEPARATOR);
define('TEMPLATE_DIR', ROOT_DIR . DIRECTORY_SEPARATOR . 'templates' . DIRECTORY_SEPARATOR);
define('MODULES_DIR', ENGINE_DIR . 'classes' . DIRECTORY_SEPARATOR . 'modules' . DIRECTORY_SEPARATOR);
define('UTILS_DIR', ENGINE_DIR . 'classes' . DIRECTORY_SEPARATOR . 'utils' . DIRECTORY_SEPARATOR);
define('SYSLIB_DIR', ENGINE_DIR . 'classes' . DIRECTORY_SEPARATOR . 'syslib' . DIRECTORY_SEPARATOR);
define('UPLOADS_DIR', '/uploads/');
define('USR_SUBFOLDER', 'users/');
define('CURRENT_TIME', time());
define('CURRENT_DATE', date('d.m.Y'));
define('REMOTE_IP', $networkContext->clientIp());

$localePath = __DIR__ . DIRECTORY_SEPARATOR . 'locale.ru.php';
$lang = require $localePath;
if (!is_array($lang)) {
    throw new RuntimeException('Invalid locale data: ' . $localePath);
}
$langtranslit = [];
