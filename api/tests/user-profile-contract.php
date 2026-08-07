<?php

declare(strict_types=1);

use FoxCMS\Api\User\UserProfilePresenter;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$rootDirectory = dirname(__DIR__, 2);
require_once dirname(__DIR__) . '/autoload.php';
require_once $rootDirectory . '/engine/classes/identity/Uuid.class.php';

$presenter = new UserProfilePresenter('https://foxescraft.ru/');
$profile = $presenter->present([
    'uuid' => '0198de8f-1e14-7bf7-8ef8-b8ed6c2bd4d1',
    'login' => 'Kayla',
    'realname' => 'Kayla Verner',
    'userStatus' => 'Architecting autonomous intelligence',
    'land' => 'Amsterdam',
    'colorScheme' => '#5BD08B',
    'profilePhoto' => '/uploads/users/kayla/profile.webp',
    'reg_date' => '1700000000',
    'last_date' => '1700000100',
    'groupTag' => 'admin',
    'groupName' => 'Администраторы',
    'groupColor' => '#E85D5D',
    'badges' => '[{"badgeName":"Раннее Возрождение"}]',
    'serversOnline' => '{}',
    'email' => 'must-not-leak@example.com',
    'balance' => '{"units":1000}',
    'userPerms' => '{"admin":true}',
]);

assertSame('0198de8f-1e14-7bf7-8ef8-b8ed6c2bd4d1', $profile['uuid'], 'UUID must be canonical');
assertSame(false, $profile['isAnonymous'], 'Persisted users must not be marked anonymous');
assertSame('Kayla Verner', $profile['fullName'], 'Full name must be mapped from realname');
assertSame('Kayla Verner', $profile['displayName'], 'Full name must be the preferred display name');
assertSame('#5bd08b', $profile['colorScheme'], 'Profile color must be normalized');
assertSame('https://foxescraft.ru/uploads/users/kayla/profile.webp', $profile['profilePhoto'], 'Photo URL must be absolute');
assertSame('/uploads/users/kayla/profile.webp', $profile['profilePhotoPath'], 'Photo path must remain available');
assertSame('#e85d5d', $profile['group']['color'], 'Group color must be normalized');
assertSame('{}', json_encode($profile['serversOnline']), 'Empty server activity must remain a JSON object');

foreach (['email', 'balance', 'userPerms', 'password', 'token'] as $privateField) {
    if (array_key_exists($privateField, $profile)) {
        throw new RuntimeException('Private field leaked into public profile: ' . $privateField);
    }
}

$invalidPhoto = $presenter->present([
    'uuid' => '0198de8f-1e14-7bf7-8ef8-b8ed6c2bd4d2',
    'login' => 'Fox',
    'profilePhoto' => 'javascript:alert(1)',
]);
assertSame(null, $invalidPhoto['profilePhoto'], 'Unsafe photo schemes must be rejected');
assertSame('Fox', $invalidPhoto['displayName'], 'Login must be the display-name fallback');

$anonymous = $presenter->anonymous('0198de8f1e147bf78ef8b8ed6c2bd4d3');
assertSame('0198de8f-1e14-7bf7-8ef8-b8ed6c2bd4d3', $anonymous['uuid'], 'Anonymous must preserve the requested UUID');
assertSame(true, $anonymous['isAnonymous'], 'Fallback profile must be marked anonymous');
assertSame('Anonymous', $anonymous['login'], 'Anonymous login must be stable');
assertSame('Anonymous', $anonymous['fullName'], 'Anonymous full name must be stable');
assertSame('Anonymous', $anonymous['displayName'], 'Anonymous display name must be stable');
assertSame('guest', $anonymous['group']['tag'], 'Anonymous must use the guest group');
assertSame('Гостевичок', $anonymous['group']['name'], 'Anonymous must use the guest group name');
assertSame(
    'https://foxescraft.ru/uploads/users/anonymous/avatar.jpg',
    $anonymous['profilePhoto'],
    'Anonymous must use the standard guest avatar',
);
assertSame([], $anonymous['badges'], 'Anonymous must not have badges');
assertSame('{}', json_encode($anonymous['serversOnline']), 'Anonymous server activity must be an object');

fwrite(STDOUT, "FoxCMS user profile API contract test passed.\n");

function assertSame(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException(
            $message . '; expected=' . var_export($expected, true)
            . '; actual=' . var_export($actual, true),
        );
    }
}
