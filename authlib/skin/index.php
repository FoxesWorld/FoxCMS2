<?php

declare(strict_types=1);

/** @var AuthlibRuntime $runtime */
$runtime = require dirname(__DIR__) . '/config.php';
$request = $runtime->request();
if ($request->method() !== 'GET') {
    $runtime->problem('Method not allowed.', 405, ['Allow' => 'GET']);
}

$profileId = strtolower($request->queryString('user'));
if (!Uuid::isValid($profileId)) {
    $runtime->problem('Invalid profile identifier.', 400);
}

$profileRow = (new AuthlibSessionRepository($runtime->database()))->findProfile($profileId);
if ($profileRow === null) {
    $runtime->noContent();
}

$profile = (new AuthlibProfileService(
    $runtime->rootDirectory(),
    $runtime->publicBaseUrl(),
))->profile($profileRow['userUuid'], $profileRow['username']);

$runtime->json($profile, 200, [
    'Cache-Control' => 'public, max-age=60, stale-while-revalidate=30',
]);
