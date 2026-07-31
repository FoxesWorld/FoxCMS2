<?php

declare(strict_types=1);

$rootDirectory = dirname(__DIR__);

require_once __DIR__ . '/data/environment.php';
require_once __DIR__ . '/classes/support/RuntimeErrorHandler.class.php';

RuntimeErrorHandler::register($rootDirectory, false);
foxLoadEnv($rootDirectory . DIRECTORY_SEPARATOR . '.env');
RuntimeErrorHandler::setDebug(foxEnvBool('FOXESCRAFT_DEBUG', false));

if (class_exists('init', false) || defined('CURRENT_TEMPLATE') || defined('RT_DIR')) {
    http_response_code(503);
    throw new RuntimeException(
        'Mixed FoxCMS deployment detected: legacy engine/init.php loaded the modern bootstrap. '
        . 'Replace index.php and synchronize the complete project with deletion of stale files.'
    );
}

if (!defined('FOXXEY')) {
    define('FOXXEY', true);
}

require_once __DIR__ . '/classes/http/NetworkContext.class.php';
$trustedProxies = array_values(array_filter(array_map(
    'trim',
    explode(',', foxEnv('FOXESCRAFT_TRUSTED_PROXIES', '') ?? ''),
), static fn (string $value): bool => $value !== ''));
$network = NetworkContext::fromGlobals($trustedProxies);
$GLOBALS['foxNetworkContext'] = $network;

require_once __DIR__ . '/data/const.php';
$config = require __DIR__ . '/data/config.php';
if (!is_array($config)) {
    throw new RuntimeException('FoxCMS configuration did not return an array.');
}

$GLOBALS['config'] = $config;
date_default_timezone_set((string)($config['other']['timezone'] ?? 'Europe/Amsterdam'));

define('CURRENT_TEMPLATE', TEMPLATE_DIR . (string)$config['siteSettings']['siteTpl'] . DIRECTORY_SEPARATOR);
define('RT_DIR', CURRENT_TEMPLATE . 'randTexts' . DIRECTORY_SEPARATOR);

require_once __DIR__ . '/classes/http/SecurityHeaders.class.php';
SecurityHeaders::apply(
    $network,
    (string)($config['environment']['name'] ?? 'production') === 'development',
);

require_once __DIR__ . '/Application.class.php';

return new Application($config, $network, [
    'init' => true,
    'postInit' => true,
    'GFX' => true,
]);
