<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/api/autoload.php';

use FoxCMS\Api\Bootstrap\Runtime\RuntimeArchive;
use FoxCMS\Api\Bootstrap\Runtime\RuntimeFilesystem;
use FoxCMS\Api\Bootstrap\Runtime\RuntimeMetadata;
use FoxCMS\Api\Bootstrap\Runtime\RuntimePlatform;

final class RuntimeJdkCatalog
{
    private const REQUIRED_PLATFORMS = [
        'windows-x86_64',
        'linux-x86_64',
        'macos-x86_64',
    ];
    private const SUPPORTED_PLATFORMS = [
        'windows-x86',
        'windows-x86_64',
        'windows-aarch64',
        'linux-x86',
        'linux-x86_64',
        'linux-aarch64',
        'macos-x86_64',
        'macos-aarch64',
    ];
    private const SYSTEM_BY_PLATFORM = [
        'windows-x86' => 'windows',
        'windows-x86_64' => 'windows',
        'windows-aarch64' => 'windows',
        'linux-x86' => 'linux',
        'linux-x86_64' => 'linux',
        'linux-aarch64' => 'linux',
        'macos-x86_64' => 'macos',
        'macos-aarch64' => 'macos',
    ];

    private ?array $snapshot = null;
    private array $resolvedArtifacts = [];

    public function __construct(private string $storageDirectory)
    {
        $this->storageDirectory = rtrim(trim($storageDirectory), '/\\');
        if ($this->storageDirectory === '') {
            throw new InvalidArgumentException('Каталог bootstrap runtime не настроен.');
        }
    }

    public function scan(): array
    {
        if (is_array($this->snapshot)) {
            return $this->snapshot;
        }

        $runtimePath = $this->runtimePath();
        $runtimeRoot = realpath($runtimePath);
        if (!is_string($runtimeRoot) || !is_dir($runtimeRoot) || is_link($runtimePath)) {
            throw new RuntimeException('Каталог Java runtime не найден: ' . $runtimePath . '.');
        }
        if (!is_readable($runtimeRoot)) {
            throw new RuntimeException('Каталог Java runtime недоступен для чтения: ' . $runtimeRoot . '.');
        }

        $storageRoot = realpath($this->storageDirectory);
        if (!is_string($storageRoot) || !is_dir($storageRoot) || !is_readable($storageRoot)) {
            throw new RuntimeException('Каталог bootstrap недоступен для чтения: ' . $this->storageDirectory . '.');
        }

        $families = [];
        $scannedArchives = 0;
        $matchedArchives = 0;
        $ignoredArchives = 0;
        $ignoredCandidates = [];
        $seenFiles = [];

        foreach (self::SUPPORTED_PLATFORMS as $platform) {
            foreach (RuntimePlatform::runtimeCatalogScanRoots($runtimeRoot, $platform) as $branch => $scanRoot) {
                foreach (RuntimeFilesystem::runtimeArchiveFiles($scanRoot) as $absolutePath) {
                    $resolvedPath = realpath($absolutePath);
                    if (!is_string($resolvedPath)) {
                        continue;
                    }
                    $fileKey = strtolower(str_replace('\\', '/', $resolvedPath));
                    if (isset($seenFiles[$fileKey])) {
                        continue;
                    }
                    $seenFiles[$fileKey] = true;
                    ++$scannedArchives;

                    $relativePath = '';
                    try {
                        $relativePath = RuntimeFilesystem::runtimeCatalogRelativePath($storageRoot, $resolvedPath);
                        $candidate = RuntimeArchive::inspectRuntimeArchive(
                            $resolvedPath,
                            $relativePath,
                            $platform,
                            $branch,
                        );
                        $major = (int)($candidate['java_major'] ?? 0);
                        $version = RuntimeMetadata::runtimeNormalizeVersion((string)($candidate['version'] ?? ''));
                        if ($major < 1 || $version === '') {
                            throw new RuntimeException('Полная версия Java не определена.');
                        }

                        $size = filesize($resolvedPath);
                        if (!is_int($size) || $size <= 0) {
                            throw new RuntimeException('Размер runtime-архива не определён.');
                        }
                        $candidate['version'] = $version;
                        $candidate['version_core'] = RuntimeMetadata::runtimeVersionCore($version);
                        $candidate['size'] = $size;
                        $candidate['modified_at'] = (int)(filemtime($resolvedPath) ?: 0);
                        $candidate['system'] = self::SYSTEM_BY_PLATFORM[$platform];
                        $families[$major]['platforms'][$platform][] = $candidate;
                        ++$matchedArchives;
                    } catch (Throwable $error) {
                        $inspectionUnavailable = str_contains($error->getMessage(), 'requires the PHP ZipArchive extension')
                            || str_contains($error->getMessage(), 'requires PharData support');
                        $installName = '';
                        $fallbackVersion = '';
                        try {
                            $installName = RuntimeMetadata::runtimeInstallDirectoryName(basename($absolutePath));
                            $fallbackVersion = RuntimeMetadata::runtimeVersionFromArchiveName($installName);
                        } catch (Throwable) {
                            $fallbackVersion = '';
                        }
                        if ($inspectionUnavailable && $fallbackVersion !== '') {
                            $size = filesize($resolvedPath);
                            $major = RuntimeMetadata::runtimeMajorFromVersion($fallbackVersion);
                            if (is_int($size) && $size > 0 && $major > 0) {
                                $archive = RuntimeMetadata::detectRuntimeArchiveFormat(basename($absolutePath));
                                $javaName = str_starts_with($platform, 'windows-') ? 'java.exe' : 'java';
                                $candidate = [
                                    'absolute_path' => $resolvedPath,
                                    'path' => $relativePath,
                                    'catalog_branch' => $branch,
                                    'runtime_id' => RuntimeMetadata::runtimeSlug(implode('-', [
                                        'filename-fallback', 'jdk', $fallbackVersion, $platform, $installName,
                                    ])),
                                    'file_name' => basename($absolutePath),
                                    'archive' => $archive,
                                    'platform' => $platform,
                                    'version' => $fallbackVersion,
                                    'version_core' => RuntimeMetadata::runtimeVersionCore($fallbackVersion),
                                    'java_major' => $major,
                                    'vendor' => RuntimeMetadata::inferRuntimeVendor($relativePath),
                                    'distribution' => 'jdk',
                                    'name' => $installName,
                                    'install_path' => 'runtime/' . $installName,
                                    'java_path' => 'bin/' . $javaName,
                                    'strip_components' => 1,
                                    'inspection' => 'archive-file-name-fallback',
                                    'stable' => RuntimeMetadata::runtimeIsStableVersion($fallbackVersion),
                                    'size' => $size,
                                    'modified_at' => (int)(filemtime($resolvedPath) ?: 0),
                                    'system' => self::SYSTEM_BY_PLATFORM[$platform],
                                ];
                                $families[$major]['platforms'][$platform][] = $candidate;
                                ++$matchedArchives;
                                continue;
                            }
                        }
                        ++$ignoredArchives;
                        if (count($ignoredCandidates) < 100) {
                            $ignoredCandidates[] = [
                                'path' => $relativePath !== '' ? $relativePath : str_replace('\\', '/', $absolutePath),
                                'name' => basename($absolutePath),
                                'version' => RuntimeMetadata::runtimeVersionFromArchiveName(RuntimeMetadata::runtimeInstallDirectoryName(basename($absolutePath))),
                                'platform' => $platform,
                                'system' => self::SYSTEM_BY_PLATFORM[$platform],
                                'reason' => $error->getMessage(),
                            ];
                        }
                    }
                }
            }
        }

        $options = [];
        foreach ($families as $major => $family) {
            $selectedByPlatform = [];
            foreach (($family['platforms'] ?? []) as $platform => $candidates) {
                usort($candidates, static function (array $left, array $right): int {
                    $versionOrder = RuntimeMetadata::compareRuntimeVersions(
                        (string)$right['version'],
                        (string)$left['version'],
                    );
                    if ($versionOrder !== 0) {
                        return $versionOrder;
                    }
                    $metadataOrder = (int)str_contains((string)$right['inspection'], 'metadata')
                        <=> (int)str_contains((string)$left['inspection'], 'metadata');
                    if ($metadataOrder !== 0) {
                        return $metadataOrder;
                    }
                    return strnatcasecmp((string)$left['path'], (string)$right['path']);
                });
                if (isset($candidates[0])) {
                    $selectedByPlatform[$platform] = $candidates[0];
                }
            }

            // Do not hide a Java family merely because one target platform is missing.
            // The admin panel must expose every parsed JDK family and report incomplete coverage.
            if ($selectedByPlatform === []) {
                continue;
            }
            ksort($selectedByPlatform, SORT_STRING);
            $availablePlatforms = array_keys($selectedByPlatform);
            $missingPlatforms = array_values(array_diff(self::REQUIRED_PLATFORMS, $availablePlatforms));
            $complete = $missingPlatforms === [];

            $profileVersions = [];
            foreach ($selectedByPlatform as $platform => $candidate) {
                $profileVersions[$platform] = (string)$candidate['version'];
            }
            $profile = RuntimeMetadata::runtimeBuildProfileSelector((int)$major, $profileVersions);
            $artifacts = [];
            $versions = [];
            $versionsBySystem = ['windows' => [], 'linux' => [], 'macos' => []];
            $versionsByPlatform = [];
            $files = ['windows' => [], 'linux' => [], 'macos' => []];
            $names = [];
            $formats = [];
            $selectors = [$profile, (string)$major, 'jdk-' . $major, 'jre-' . $major, 'java-' . $major];
            $archiveCount = 0;

            // Keep every parsed patch/build in searchable metadata. Only artifacts below are the
            // selected newest runtime per concrete platform.
            foreach (($family['platforms'] ?? []) as $platform => $candidates) {
                $system = self::SYSTEM_BY_PLATFORM[$platform];
                foreach ($candidates as $candidate) {
                    ++$archiveCount;
                    $version = (string)$candidate['version'];
                    $versions[$version] = true;
                    $versions[(string)$candidate['version_core']] = true;
                    $versionsBySystem[$system][$version] = true;
                    $files[$system][(string)$candidate['path']] = true;
                    $names[(string)$candidate['file_name']] = true;
                    $formats[(string)$candidate['archive']] = true;
                    $selectors[] = $version;
                    $selectors[] = (string)$candidate['version_core'];
                }
            }

            foreach ($selectedByPlatform as $platform => $candidate) {
                $system = self::SYSTEM_BY_PLATFORM[$platform];
                $version = (string)$candidate['version'];
                $publicArtifact = [
                    'runtimeId' => (string)$candidate['runtime_id'],
                    'platform' => $platform,
                    'system' => $system,
                    'version' => $version,
                    'versionCore' => (string)$candidate['version_core'],
                    'javaMajor' => (int)$candidate['java_major'],
                    'vendor' => (string)$candidate['vendor'],
                    'distribution' => (string)$candidate['distribution'],
                    'fileName' => (string)$candidate['file_name'],
                    'path' => (string)$candidate['path'],
                    'archive' => (string)$candidate['archive'],
                    'size' => (int)$candidate['size'],
                    'installPath' => (string)$candidate['install_path'],
                    'javaPath' => (string)$candidate['java_path'],
                    'stripComponents' => (int)$candidate['strip_components'],
                    'inspection' => (string)$candidate['inspection'],
                ];
                $artifacts[$platform] = $publicArtifact;
                $this->resolvedArtifacts[$profile][$platform] = $candidate;
                $versionsByPlatform[$platform] = $version;
            }

            $selectedVersions = ['windows' => '', 'linux' => '', 'macos' => ''];
            $preferredPlatforms = [
                'windows' => 'windows-x86_64',
                'linux' => 'linux-x86_64',
                'macos' => 'macos-x86_64',
            ];
            foreach ($preferredPlatforms as $system => $preferredPlatform) {
                if (isset($profileVersions[$preferredPlatform])) {
                    $selectedVersions[$system] = $profileVersions[$preferredPlatform];
                    continue;
                }
                foreach ($selectedByPlatform as $platform => $candidate) {
                    if (self::SYSTEM_BY_PLATFORM[$platform] === $system) {
                        $selectedVersions[$system] = (string)$candidate['version'];
                        break;
                    }
                }
            }
            $availableSystems = [];
            foreach ($availablePlatforms as $platform) {
                $availableSystems[self::SYSTEM_BY_PLATFORM[$platform]] = true;
            }
            $availableSystems = array_keys($availableSystems);
            $missingSystems = array_values(array_diff(['windows', 'linux', 'macos'], $availableSystems));
            $versionList = array_keys($versions);
            usort($versionList, static fn(string $left, string $right): int => RuntimeMetadata::compareRuntimeVersions($right, $left));
            foreach ($versionsBySystem as $system => $systemVersions) {
                $list = array_keys($systemVersions);
                usort($list, static fn(string $left, string $right): int => RuntimeMetadata::compareRuntimeVersions($right, $left));
                $versionsBySystem[$system] = $list;
            }
            foreach ($files as $system => $systemFiles) {
                $list = array_keys($systemFiles);
                natcasesort($list);
                $files[$system] = array_values($list);
            }
            $nameList = array_keys($names);
            natcasesort($nameList);
            $formatList = array_keys($formats);
            natcasesort($formatList);
            $selectors = array_values(array_unique(array_filter(array_map('trim', $selectors))));

            $options[] = [
                // Persist only the Java major. The exact cross-platform profile is diagnostic
                // metadata and is resolved to one concrete archive for the launcher platform.
                'value' => (string)$major,
                'profile' => $profile,
                'label' => sprintf(
                    'JDK %d — Windows %s / Linux %s / macOS %s',
                    $major,
                    $selectedVersions['windows'] !== '' ? $selectedVersions['windows'] : 'нет архива',
                    $selectedVersions['linux'] !== '' ? $selectedVersions['linux'] : 'нет архива',
                    $selectedVersions['macos'] !== '' ? $selectedVersions['macos'] : 'нет архива',
                ),
                'version' => (string)$major,
                'javaMajor' => (int)$major,
                'complete' => $complete,
                'systems' => $availableSystems,
                'missingSystems' => $missingSystems,
                'platforms' => $availablePlatforms,
                'missingPlatforms' => $missingPlatforms,
                'versions' => $versionList,
                'versionsBySystem' => $versionsBySystem,
                'versionsByPlatform' => $versionsByPlatform,
                'selectedVersions' => $selectedVersions,
                'selectors' => $selectors,
                'artifacts' => $artifacts,
                'names' => array_values($nameList),
                'files' => $files,
                'archives' => $archiveCount,
                'archiveFormats' => array_values($formatList),
            ];
        }

        usort($options, static fn(array $left, array $right): int => (int)$right['javaMajor'] <=> (int)$left['javaMajor']);

        return $this->snapshot = [
            'available' => true,
            'root' => $runtimeRoot,
            'requiredSystems' => ['windows', 'linux', 'macos'],
            'requiredPlatforms' => self::REQUIRED_PLATFORMS,
            'supportedPlatforms' => self::SUPPORTED_PLATFORMS,
            'scannedArchives' => $scannedArchives,
            'matchedArchives' => $matchedArchives,
            'ignoredArchives' => $ignoredArchives,
            'ignoredCandidates' => $ignoredCandidates,
            'mode' => 'exact-runtime-profiles',
            'versionSource' => 'archive-release-metadata-or-file-name',
            'systemSource' => 'catalog-branch-and-release-metadata',
            'options' => $options,
        ];
    }

    public function normalizeVersion(string $selector): ?string
    {
        $major = self::normalizeMajorSelector($selector);
        if ($major === null) {
            return null;
        }
        foreach ($this->scan()['options'] as $option) {
            if (hash_equals((string)$option['value'], $major)) {
                return $major;
            }
        }
        return null;
    }

    public function normalizeProfile(string $selector): ?string
    {
        $selector = trim($selector);
        if ($selector === '') {
            return null;
        }
        $parsedProfile = RuntimeMetadata::runtimeParseProfileSelector($selector);
        $canonicalProfile = is_array($parsedProfile) ? (string)$parsedProfile['selector'] : '';
        $normalizedAlias = self::normalizeSelectorAlias($selector);
        $major = (int)(self::normalizeMajorSelector($selector) ?? 0);

        foreach ($this->scan()['options'] as $option) {
            $profile = (string)$option['profile'];
            if (($canonicalProfile !== '' && hash_equals($profile, $canonicalProfile))
                || hash_equals($profile, $selector)
            ) {
                return $profile;
            }
            foreach ($option['selectors'] as $alias) {
                if (strcasecmp((string)$alias, $selector) === 0
                    || ($normalizedAlias !== '' && strcasecmp((string)$alias, $normalizedAlias) === 0)
                ) {
                    return $profile;
                }
            }
            // Any valid legacy/exact selector resolves through its Java major. The archive
            // remains platform-specific and exact after resolveArtifact() selects it.
            if ($major > 0 && (int)$option['javaMajor'] === $major) {
                return $profile;
            }
        }
        return null;
    }

    public function profile(string $selector): ?array
    {
        $profile = $this->normalizeProfile($selector);
        if ($profile === null) {
            return null;
        }
        foreach ($this->scan()['options'] as $option) {
            if (hash_equals((string)$option['profile'], $profile)) {
                return $option;
            }
        }
        return null;
    }

    public function resolveArtifact(string $selector, string $platform): ?array
    {
        $profile = $this->normalizeProfile($selector);
        $platform = self::normalizePlatform($platform);
        if ($profile === null || $platform === null) {
            return null;
        }
        $this->scan();
        $artifact = $this->resolvedArtifacts[$profile][$platform] ?? null;
        if (!is_array($artifact)) {
            return null;
        }
        $artifact['profile'] = $profile;
        return $artifact;
    }

    public function hasVersion(string $version): bool
    {
        return $this->normalizeVersion($version) !== null;
    }

    public function runtimePath(): string
    {
        return $this->storageDirectory . DIRECTORY_SEPARATOR . 'runtime';
    }

    public static function isValidSelectorSyntax(string $selector): bool
    {
        return self::normalizeMajorSelector($selector) !== null;
    }

    public static function normalizeMajorSelector(string $selector): ?string
    {
        $selector = trim($selector);
        if ($selector === '' || strlen($selector) > 512) {
            return null;
        }
        $profile = RuntimeMetadata::runtimeParseProfileSelector($selector);
        if (is_array($profile)) {
            $major = (int)($profile['java_major'] ?? 0);
            return $major > 0 && $major <= 100 ? (string)$major : null;
        }
        $normalized = self::normalizeSelectorAlias($selector);
        $major = RuntimeMetadata::runtimeMajorFromVersion($normalized);
        return $major > 0 && $major <= 100 ? (string)$major : null;
    }

    public static function normalizePlatform(string $platform): ?string
    {
        $value = strtolower(trim($platform));
        $value = str_replace([' ', '/'], '-', $value);
        $aliases = [
            'win' => 'windows-x86_64',
            'windows' => 'windows-x86_64',
            'win64' => 'windows-x86_64',
            'windows-x64' => 'windows-x86_64',
            'windows-amd64' => 'windows-x86_64',
            'win32' => 'windows-x86',
            'windows-x32' => 'windows-x86',
            'linux' => 'linux-x86_64',
            'unix' => 'linux-x86_64',
            'linux-x64' => 'linux-x86_64',
            'linux-amd64' => 'linux-x86_64',
            'mac' => 'macos-x86_64',
            'osx' => 'macos-x86_64',
            'macos' => 'macos-x86_64',
            'mac-x64' => 'macos-x86_64',
            'macos-x64' => 'macos-x86_64',
            'win-arm64' => 'windows-aarch64',
            'windows-arm64' => 'windows-aarch64',
            'linux-arm64' => 'linux-aarch64',
            'mac-arm64' => 'macos-aarch64',
            'macos-arm64' => 'macos-aarch64',
        ];
        $value = $aliases[$value] ?? $value;
        return in_array($value, self::SUPPORTED_PLATFORMS, true) ? $value : null;
    }

    private static function normalizeSelectorAlias(string $selector): string
    {
        $selector = trim($selector);
        $selector = preg_replace('/^(?:jdk|jre|java)[-_]?/i', '', $selector);
        if (!is_string($selector)) {
            return '';
        }
        return RuntimeMetadata::runtimeNormalizeVersion($selector);
    }
}
