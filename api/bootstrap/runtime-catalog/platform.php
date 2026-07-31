<?php

declare(strict_types=1);

/** Platform aliases, catalog branches and Java platform normalization. */

function runtimeCatalogBranchesForPlatform(string $platform): array
{
    $branches = array(
        'windows-x86' => array(
            array('win', 'x32'), array('win', 'x86'),
            array('windows', 'x32'), array('windows', 'x86'),
        ),
        'windows-x86_64' => array(
            array('win', 'x64'), array('win', 'amd64'), array('win', 'x86_64'),
            array('windows', 'x64'), array('windows', 'amd64'), array('windows', 'x86_64'),
        ),
        'windows-aarch64' => array(
            array('win', 'arm64'), array('win', 'aarch64'),
            array('windows', 'arm64'), array('windows', 'aarch64'),
        ),
        'linux-x86' => array(array('linux', 'x32'), array('linux', 'x86')),
        'linux-x86_64' => array(
            array('linux', 'x64'), array('linux', 'amd64'), array('linux', 'x86_64'),
        ),
        'linux-aarch64' => array(array('linux', 'arm64'), array('linux', 'aarch64')),
        'macos-x86_64' => array(
            array('mac', 'x64'), array('macos', 'x64'), array('osx', 'x64'),
            array('mac', 'x86_64'), array('macos', 'x86_64'),
        ),
        'macos-aarch64' => array(
            array('mac', 'arm64'), array('macos', 'arm64'), array('osx', 'arm64'),
            array('mac', 'aarch64'), array('macos', 'aarch64'),
        ),
    );
    if (!isset($branches[$platform])) {
        throw new RuntimeException('No runtime catalog branches are defined for ' . $platform . '.');
    }
    return $branches[$platform];
}
function runtimeCatalogScanRoots(string $runtimeRoot, string $platform): array
{
    $roots = array();
    foreach (runtimeCatalogBranchesForPlatform($platform) as $segments) {
        $path = $runtimeRoot;
        foreach ($segments as $segment) {
            $path .= DIRECTORY_SEPARATOR . $segment;
        }
        if (is_dir($path) && !is_link($path) && is_readable($path)) {
            $roots[implode('/', $segments)] = $path;
        }
    }
    return $roots;
}
function platformFromJavaRelease(array $release): string
{
    $os = isset($release['OS_NAME']) ? strtolower((string) $release['OS_NAME']) : '';
    $arch = isset($release['OS_ARCH']) ? strtolower((string) $release['OS_ARCH']) : '';

    if (strpos($os, 'windows') !== false) {
        $os = 'windows';
    } elseif (strpos($os, 'linux') !== false) {
        $os = 'linux';
    } elseif (strpos($os, 'mac') !== false || strpos($os, 'darwin') !== false || strpos($os, 'os x') !== false) {
        $os = 'macos';
    } else {
        return '';
    }

    if (in_array($arch, array('x86', 'i386', 'i486', 'i586', 'i686', 'x86_32'), true)) {
        $arch = 'x86';
    } elseif (in_array($arch, array('x86_64', 'amd64', 'x64'), true)) {
        $arch = 'x86_64';
    } elseif (in_array($arch, array('aarch64', 'arm64'), true)) {
        $arch = 'aarch64';
    } else {
        return '';
    }
    return $os . '-' . $arch;
}
