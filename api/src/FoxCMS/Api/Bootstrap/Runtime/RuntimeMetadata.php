<?php

declare(strict_types=1);

namespace FoxCMS\Api\Bootstrap\Runtime;

use InvalidArgumentException;
use RuntimeException;

/** Runtime filename, version, vendor and archive metadata helpers. */

final class RuntimeMetadata
{
    public static function runtimeInstallDirectoryName(string $fileName): string
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
    public static function runtimeVersionFromArchiveName(string $name): string
    {
        $patterns = array(
            '/(?:jdk|jre|java)[-_]?(1\.8\.0[_-][0-9]+|8u[0-9]+)/i',
            '/(?:jdk|jre|java)[-_]?([0-9]+(?:\.[0-9]+)+(?:[+_][0-9]+)?)/i',
            '/(?:^|[-_])(1\.8\.0[_-][0-9]+|8u[0-9]+)(?:$|[-_+])/i',
            '/(?:^|[-_])([0-9]+(?:\.[0-9]+)+(?:[+_][0-9]+)?)(?:$|[-_])/i',
        );
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $name, $match) === 1) {
                return RuntimeMetadata::runtimeNormalizeVersion((string)$match[1]);
            }
        }
        return '';
    }
    public static function runtimeNormalizeVersion(string $version): string
    {
        $version = trim($version, " \t\n\r\0\x0B\"");
        if ($version === '') {
            return '';
        }

        if (preg_match('/^1\.8\.0[_-]([0-9]+)(.*)$/iD', $version, $legacy) === 1) {
            $suffix = trim((string)$legacy[2]);
            $build = '';
            if (preg_match('/(?:[-+_]b?)([0-9]+)(?:[-+._].*)?$/iD', $suffix, $buildMatch) === 1) {
                $build = '+' . (int)$buildMatch[1];
            }
            return '8u' . (int)$legacy[1] . $build;
        }
        if (preg_match('/^8u([0-9]+)(.*)$/iD', $version, $legacy) === 1) {
            $suffix = trim((string)$legacy[2]);
            $build = '';
            if (preg_match('/(?:[-+_]b?)([0-9]+)(?:[-+._].*)?$/iD', $suffix, $buildMatch) === 1) {
                $build = '+' . (int)$buildMatch[1];
            }
            return '8u' . (int)$legacy[1] . $build;
        }

        if (preg_match('/^([0-9]+(?:\.[0-9]+)*)(?:_([0-9]+))?(.*)$/D', $version, $modern) !== 1) {
            return '';
        }
        $base = implode('.', array_map(
            static fn(string $part): string => (string)(int)$part,
            explode('.', (string)$modern[1]),
        ));
        $build = $modern[2] !== '' ? '+' . (int)$modern[2] : '';
        $suffix = trim((string)$modern[3]);
        if ($suffix !== '') {
            if ($build === '' && preg_match('/^\+([0-9]+)(.*)$/D', $suffix, $buildMatch) === 1) {
                $build = '+' . (int)$buildMatch[1];
                $suffix = trim((string)$buildMatch[2]);
            }
            $suffix = preg_replace('/[^A-Za-z0-9.+_-]+/', '-', $suffix);
            $suffix = is_string($suffix) ? trim($suffix, '-') : '';
        }
        return $base . $build . $suffix;
    }
    public static function runtimeVersionCore(string $version): string
    {
        $normalized = RuntimeMetadata::runtimeNormalizeVersion($version);
        if ($normalized === '') {
            return '';
        }
        if (preg_match('/^(8u[0-9]+)/iD', $normalized, $legacy) === 1) {
            return strtolower((string)$legacy[1]);
        }
        if (preg_match('/^([0-9]+(?:\.[0-9]+)*)/D', $normalized, $modern) === 1) {
            return (string)$modern[1];
        }
        return '';
    }
    public static function runtimeMajorFromVersion(string $version): int
    {
        $normalized = RuntimeMetadata::runtimeNormalizeVersion($version);
        if (preg_match('/^8u[0-9]+/iD', $normalized) === 1) {
            return 8;
        }
        if (preg_match('/^(?:1\.)?([0-9]+)/D', $normalized, $match) !== 1) {
            return 0;
        }
        return (int)$match[1];
    }
    public static function runtimeVersionsMatchExact(string $candidate, string $requested): bool
    {
        $candidate = RuntimeMetadata::runtimeNormalizeVersion($candidate);
        $requested = RuntimeMetadata::runtimeNormalizeVersion($requested);
        if ($candidate === '' || $requested === '') {
            return false;
        }
        if (hash_equals(strtolower($candidate), strtolower($requested))) {
            return true;
        }
        if (str_contains($requested, '+') || preg_match('/(?:ea|alpha|beta|rc|snapshot)/i', $requested) === 1) {
            return false;
        }
        return hash_equals(strtolower(RuntimeMetadata::runtimeVersionCore($candidate)), strtolower(RuntimeMetadata::runtimeVersionCore($requested)));
    }
    public static function compareRuntimeVersions(string $left, string $right): int
    {
        $leftNormalized = RuntimeMetadata::runtimeNormalizeVersion($left);
        $rightNormalized = RuntimeMetadata::runtimeNormalizeVersion($right);
        $leftNumbers = array_map('intval', preg_split('/[^0-9]+/', $leftNormalized, -1, PREG_SPLIT_NO_EMPTY) ?: array());
        $rightNumbers = array_map('intval', preg_split('/[^0-9]+/', $rightNormalized, -1, PREG_SPLIT_NO_EMPTY) ?: array());
        $length = max(count($leftNumbers), count($rightNumbers));
        for ($index = 0; $index < $length; ++$index) {
            $difference = ($leftNumbers[$index] ?? 0) <=> ($rightNumbers[$index] ?? 0);
            if ($difference !== 0) {
                return $difference;
            }
        }
        $leftStable = RuntimeMetadata::runtimeIsStableVersion($leftNormalized);
        $rightStable = RuntimeMetadata::runtimeIsStableVersion($rightNormalized);
        if ($leftStable !== $rightStable) {
            return $leftStable ? 1 : -1;
        }
        return strnatcasecmp($leftNormalized, $rightNormalized);
    }
    public static function runtimeBuildProfileSelector(int $javaMajor, array $versionsByPlatform): string
    {
        if ($javaMajor < 1) {
            throw new InvalidArgumentException('Java major must be positive.');
        }
        ksort($versionsByPlatform, SORT_STRING);
        $parts = array('java=' . $javaMajor);
        foreach ($versionsByPlatform as $platform => $version) {
            $platform = trim((string)$platform);
            $version = RuntimeMetadata::runtimeNormalizeVersion((string)$version);
            if (preg_match('/^(?:(?:windows|linux)-(?:x86|x86_64|aarch64)|macos-(?:x86_64|aarch64))$/D', $platform) !== 1
                || $version === ''
            ) {
                throw new InvalidArgumentException('Runtime profile contains an invalid platform or Java version.');
            }
            $parts[] = $platform . '=' . $version;
        }
        return implode(';', $parts);
    }
    public static function runtimeParseProfileSelector(string $selector): ?array
    {
        $selector = trim($selector);
        if (!str_starts_with($selector, 'java=')) {
            return null;
        }
        if ($selector === '' || strlen($selector) > 512) {
            return null;
        }
        $segments = explode(';', $selector);
        $head = array_shift($segments);
        if (!is_string($head) || preg_match('/^java=([0-9]+)$/D', $head, $majorMatch) !== 1) {
            return null;
        }
        $major = (int)$majorMatch[1];
        if ($major < 1) {
            return null;
        }
        $versions = array();
        foreach ($segments as $segment) {
            if (preg_match('/^((?:(?:windows|linux)-(?:x86|x86_64|aarch64)|macos-(?:x86_64|aarch64)))=(.+)$/D', $segment, $match) !== 1) {
                return null;
            }
            $platform = (string)$match[1];
            $version = RuntimeMetadata::runtimeNormalizeVersion((string)$match[2]);
            if ($version === '' || RuntimeMetadata::runtimeMajorFromVersion($version) !== $major || isset($versions[$platform])) {
                return null;
            }
            $versions[$platform] = $version;
        }
        if ($versions === array()) {
            return null;
        }
        ksort($versions, SORT_STRING);
        return array(
            'java_major' => $major,
            'versions' => $versions,
            'selector' => RuntimeMetadata::runtimeBuildProfileSelector($major, $versions),
        );
    }
    public static function runtimeIsStableVersion(string $version): bool
    {
        return preg_match('/(?:^|[-+._])(ea|alpha|beta|rc|snapshot)(?:$|[-+._0-9])/i', $version) !== 1;
    }
    public static function inferRuntimeVendor(string $path): string
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
    public static function detectRuntimeArchiveFormat(string $fileName): string
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
    public static function runtimeSlug(string $value): string
    {
        $slug = strtolower($value);
        $slug = preg_replace('/[^a-z0-9._-]+/', '-', $slug);
        $slug = is_string($slug) ? trim($slug, '-._') : '';
        return substr($slug, 0, 180);
    }
}
