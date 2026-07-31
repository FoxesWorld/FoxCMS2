<?php

declare(strict_types=1);

/** @var AuthlibRuntime $runtime */
$runtime = require dirname(__DIR__) . '/config.php';
$payload = $runtime->jsonBody(8192);

$accessToken = is_string($payload['accessToken'] ?? null) ? trim($payload['accessToken']) : '';
$selectedProfile = is_string($payload['selectedProfile'] ?? null) ? strtolower(trim($payload['selectedProfile'])) : '';
$serverId = is_string($payload['serverId'] ?? null) ? strtolower(trim($payload['serverId'])) : '';

if (preg_match('/^[a-f0-9]{32,128}$/D', $accessToken) !== 1) {
    $runtime->problem('Invalid access token.', 403);
}
if (!Uuid::isValid($selectedProfile)) {
    $runtime->problem('Invalid selected profile.', 400);
}
if (preg_match('/^-?[a-f0-9]{1,40}$/D', $serverId) !== 1) {
    $runtime->problem('Invalid server identifier.', 400);
}

$repository = new AuthlibSessionRepository($runtime->database());
$session = $repository->join($selectedProfile, $accessToken);
if ($session === null || !$repository->attachServer($session['userUuid'], $serverId)) {
    $runtime->logEvent('launcher_join_rejected', [
        'profile' => substr(Uuid::compact($selectedProfile), 0, 8),
        'ip' => $runtime->request()->clientIp(),
    ]);
    $runtime->problem('Invalid token or profile.', 403);
}

$runtime->logEvent('launcher_join_accepted', [
    'userUuid' => $session['userUuid'],
    'ip' => $runtime->request()->clientIp(),
]);
$runtime->noContent();
