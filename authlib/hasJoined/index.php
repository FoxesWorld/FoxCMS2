<?php

declare(strict_types=1);

/** @var AuthlibRuntime $runtime */
$runtime = require dirname(__DIR__) . '/config.php';
$request = $runtime->request();
if ($request->method() !== 'GET') {
    $runtime->problem('Method not allowed.', 405, ['Allow' => 'GET']);
}

$username = $request->queryString('username');
$serverId = strtolower($request->queryString('serverId'));
if (preg_match('/^[A-Za-z0-9_.-]{1,64}$/D', $username) !== 1) {
    $runtime->problem('Invalid username.', 400);
}
if (preg_match('/^-?[a-f0-9]{1,40}$/D', $serverId) !== 1) {
    $runtime->problem('Invalid server identifier.', 400);
}

$session = (new AuthlibSessionRepository($runtime->database()))->findJoined($username, $serverId);
if ($session === null) {
    $runtime->noContent();
}

$profile = (new AuthlibProfileService(
    $runtime->rootDirectory(),
    $runtime->publicBaseUrl(),
))->profile($session['userUuid'], $session['username']);

$runtime->logEvent('launcher_join_verified', [
    'userUuid' => $session['userUuid'],
    'ip' => $request->clientIp(),
]);
$runtime->json($profile);
