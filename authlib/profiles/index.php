<?php

declare(strict_types=1);

/** @var AuthlibRuntime $runtime */
$runtime = require dirname(__DIR__) . '/config.php';
$requested = $runtime->jsonList(16384, 100);

$names = [];
foreach ($requested as $username) {
    if (!is_string($username) || preg_match('/^[A-Za-z0-9_.-]{1,64}$/D', $username) !== 1) {
        $runtime->problem('Invalid profile name.', 400);
    }
    $names[] = $username;
}

$profiles = (new AuthlibSessionRepository($runtime->database()))->findProfilesByNames($names);
$result = [];
foreach ($names as $username) {
    $profile = $profiles[strtolower($username)] ?? null;
    if ($profile === null) {
        continue;
    }
    $result[] = [
        'id' => $profile['profileId'],
        'name' => $profile['username'],
    ];
}

$runtime->json($result);
