<?php

declare(strict_types=1);

/** Runtime API request parsing and validation. */

function parseRuntimeRequest(): array
{
    $platform = isset($_GET['platform']) ? trim((string) $_GET['platform']) : 'windows-x86_64';
    if (preg_match('/^(?:(?:windows|linux)-(?:x86|x86_64|aarch64)|macos-(?:x86_64|aarch64))$/D', $platform) !== 1) {
        fail(422, 'runtime_request_platform_invalid', 'The requested runtime platform is unsupported.', array(
            'received' => $platform,
        ));
    }

    $version = isset($_GET['version']) ? trim((string) $_GET['version']) : '';
    if (preg_match('/^[0-9]+(?:\.[0-9]+)*$/D', $version) !== 1) {
        fail(422, 'runtime_request_version_invalid', 'version must be a Java major such as 17 or an exact version such as 17.0.16.', array(
            'received' => $version,
        ));
    }
    $versionMode = strpos($version, '.') === false ? 'major' : 'exact';

    $distribution = isset($_GET['distribution'])
        ? strtolower(trim((string) $_GET['distribution']))
        : 'any';
    if (!in_array($distribution, array('any', 'jdk', 'jre'), true)) {
        fail(422, 'runtime_request_distribution_invalid', 'distribution must be any, jdk or jre.', array(
            'received' => $distribution,
        ));
    }

    $vendor = isset($_GET['vendor']) ? trim((string) $_GET['vendor']) : '';
    if ($vendor !== '' && preg_match('/^[A-Za-z0-9 ._+-]{1,80}$/D', $vendor) !== 1) {
        fail(422, 'runtime_request_vendor_invalid', 'vendor contains unsupported characters.');
    }

    $allowPrereleaseRaw = isset($_GET['allow_prerelease'])
        ? strtolower(trim((string) $_GET['allow_prerelease']))
        : 'false';
    if (!in_array($allowPrereleaseRaw, array('1', '0', 'true', 'false', 'yes', 'no'), true)) {
        fail(422, 'runtime_request_prerelease_invalid', 'allow_prerelease must be boolean.');
    }

    return array(
        'platform' => $platform,
        'version' => $version,
        'version_mode' => $versionMode,
        'java_major' => runtimeMajorFromVersion($version),
        'distribution' => $distribution,
        'vendor' => $vendor,
        'allow_prerelease' => in_array($allowPrereleaseRaw, array('1', 'true', 'yes'), true),
        'client_version' => isset($_GET['client_version'])
            ? trim((string) $_GET['client_version'])
            : 'legacy-or-unknown',
    );
}
