<?php

declare(strict_types=1);

/** Runtime filename, version, vendor and archive metadata helpers. */

function runtimeInstallDirectoryName(string $fileName): string
{
    $lower = strtolower($fileName);
    if (substr($lower, -7) === '.tar.gz') {
        $name = substr($fileName, 0, -7);
    } elseif (substr($lower, -4) === '.zip' || substr($lower, -4) === '.tgz') {
        $name = substr($fileName, 0, -4);
    } else {
        throw new RuntimeException('Unsupported runtime archive extension: ' . $fileName . '.');
    }
    if ($name === '' || $name === '.' || $name === '..'
        || preg_match('/^[A-Za-z0-9][A-Za-z0-9._+-]*$/D', $name) !== 1
    ) {
        throw new RuntimeException('Runtime archive has an unsafe installation name: ' . $fileName . '.');
    }
    return $name;
}
function runtimeVersionFromArchiveName(string $name): string
{
    if (preg_match('/(?:jdk|jre|java)[-_]?([0-9]+(?:\.[0-9]+)+)/i', $name, $match) === 1) {
        return $match[1];
    }
    if (preg_match('/^([0-9]+(?:\.[0-9]+)+)(?:$|[-_+])/D', $name, $match) === 1) {
        return $match[1];
    }
    return '';
}
function runtimeVersionCore(string $version): string
{
    if (preg_match('/^[0-9]+(?:\.[0-9]+)*/D', trim($version), $match) !== 1) {
        return '';
    }
    return $match[0];
}
function runtimeMajorFromVersion(string $version): int
{
    if (preg_match('/^(?:1\.)?([0-9]+)/D', trim($version), $match) !== 1) {
        return 0;
    }
    return (int) $match[1];
}
function runtimeIsStableVersion(string $version): bool
{
    return preg_match('/(?:^|[-+._])(ea|alpha|beta|rc|snapshot)(?:$|[-+._0-9])/i', $version) !== 1;
}
function inferRuntimeVendor(string $path): string
{
    $lower = strtolower($path);
    foreach (array(
        'temurin' => 'Eclipse Temurin',
        'liberica' => 'BellSoft Liberica',
        'bellsoft' => 'BellSoft Liberica',
        'corretto' => 'Amazon Corretto',
        'zulu' => 'Azul Zulu',
        'microsoft' => 'Microsoft OpenJDK',
        'graal' => 'GraalVM',
        'oracle' => 'Oracle',
    ) as $needle => $vendor) {
        if (strpos($lower, $needle) !== false) {
            return $vendor;
        }
    }
    return 'OpenJDK-compatible';
}
function detectRuntimeArchiveFormat(string $fileName): string
{
    $lower = strtolower($fileName);
    if (substr($lower, -4) === '.zip') {
        return 'zip';
    }
    if (substr($lower, -7) === '.tar.gz' || substr($lower, -4) === '.tgz') {
        return 'tar.gz';
    }
    throw new RuntimeException('Unsupported runtime archive extension.');
}
function runtimeSlug(string $value): string
{
    $slug = strtolower($value);
    $slug = preg_replace('/[^a-z0-9._-]+/', '-', $slug);
    $slug = is_string($slug) ? trim($slug, '-._') : '';
    return substr($slug, 0, 180);
}
