<?php

declare(strict_types=1);

/** @var AuthlibRuntime $runtime */
$runtime = require dirname(__DIR__) . '/config.php';
$requested = $runtime->jsonList(16384, 100);

$profileIds = [];
foreach ($requested as $profileId) {
    if (!is_string($profileId) || !Uuid::isValid($profileId)) {
        $runtime->problem('Invalid profile identifier.', 400);
    }
    $profileIds[] = Uuid::compact($profileId);
}

$profiles = (new AuthlibSessionRepository($runtime->database()))->findProfiles($profileIds);
$result = [];
foreach ($profileIds as $profileId) {
    $profile = $profiles[$profileId] ?? null;
    if ($profile === null) {
        continue;
    }
    $result[] = [
        'id' => $profile['profileId'],
        'name' => $profile['username'],
    ];
}

$runtime->json($result);
