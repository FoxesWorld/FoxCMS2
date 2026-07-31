<?php

declare(strict_types=1);

$rootDirectory = dirname(__DIR__);
require_once $rootDirectory . '/engine/data/environment.php';
require_once $rootDirectory . '/engine/classes/support/RuntimeErrorHandler.class.php';
RuntimeErrorHandler::register($rootDirectory, false);
foxLoadEnv($rootDirectory . '/.env');
RuntimeErrorHandler::setDebug(foxEnvBool('FOXESCRAFT_DEBUG', false));

if (!defined('FOXXEY')) {
    define('FOXXEY', true);
}

require_once $rootDirectory . '/engine/classes/http/NetworkContext.class.php';
$trustedProxies = array_values(array_filter(array_map(
    'trim',
    explode(',', foxEnv('FOXESCRAFT_TRUSTED_PROXIES', '') ?? ''),
), static fn (string $value): bool => $value !== ''));
$network = NetworkContext::fromGlobals($trustedProxies);
$GLOBALS['foxNetworkContext'] = $network;

require_once $rootDirectory . '/engine/data/const.php';
$config = require $rootDirectory . '/engine/data/config.php';
require_once $rootDirectory . '/engine/classes/http/SecurityHeaders.class.php';
SecurityHeaders::apply($network, false);

require_once $rootDirectory . '/engine/classes/http/HttpRequest.class.php';
$request = HttpRequest::fromGlobals($network);
$healthToken = (string)($config['other']['healthToken'] ?? '');
if ($healthToken !== '') {
    $authorization = $request->header('Authorization');
    $provided = str_starts_with($authorization, 'Bearer ')
        ? substr($authorization, 7)
        : '';
    if ($provided === '' || !hash_equals($healthToken, $provided)) {
        http_response_code(404);
        exit;
    }
}

require_once $rootDirectory . '/engine/classes/syslib/database.php';
require_once $rootDirectory . '/engine/classes/services/HealthCheckService.class.php';
$databaseConfig = is_array($config['database'] ?? null) ? $config['database'] : [];

try {
    $database = new db(
        (string)($databaseConfig['dbUser'] ?? ''),
        (string)($databaseConfig['dbPass'] ?? ''),
        (string)($databaseConfig['dbName'] ?? ''),
        (string)($databaseConfig['dbHost'] ?? '127.0.0.1'),
        (int)($databaseConfig['dbPort'] ?? 3306),
        (string)($databaseConfig['dbCharset'] ?? 'utf8mb4'),
        (int)($databaseConfig['connectTimeout'] ?? 5),
    );
    $payload = (new HealthCheckService($database, $config, $rootDirectory))->inspect();
} catch (Throwable $exception) {
    error_log('[FoxCMS health] ' . $exception->getMessage());
    $payload = [
        'ok' => false,
        'service' => (string)($config['other']['webserviceName'] ?? 'FoxesCraft'),
        'version' => (string)($config['siteSettings']['ServiceVersion'] ?? 'unknown'),
        'timestamp' => gmdate('c'),
        'checks' => [
            'database' => ['ok' => false],
        ],
    ];
}

http_response_code(($payload['ok'] ?? false) === true ? 200 : 503);
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, max-age=0');
header('Retry-After: 30');
$encoded = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
if (!is_string($encoded)) {
    throw new RuntimeException('Unable to encode health response.');
}
exit($encoded);
