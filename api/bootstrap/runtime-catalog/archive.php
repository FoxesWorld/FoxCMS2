<?php

declare(strict_types=1);

/** Archive-independent Java home inspection and metadata analysis. */

function inspectRuntimeArchive(
    string $absolutePath,
    string $relativePath,
    string $branchPlatform,
    string $catalogBranch
): array {
    if (!is_file($absolutePath) || is_link($absolutePath) || !is_readable($absolutePath)) {
        throw new RuntimeException('Runtime archive is not a readable regular file.');
    }
    $size = filesize($absolutePath);
    if ($size === false || (int) $size <= 0) {
        throw new RuntimeException('Runtime archive is empty or unreadable.');
    }

    $fileName = basename($absolutePath);
    $archive = detectRuntimeArchiveFormat($fileName);
    $installName = runtimeInstallDirectoryName($fileName);
    $inspection = $archive === 'zip'
        ? inspectZipRuntime($absolutePath, $branchPlatform)
        : inspectTarRuntime($absolutePath, $branchPlatform);

    $fileVersion = runtimeVersionFromArchiveName($installName);
    $metadataVersion = trim((string)($inspection['version'] ?? ''));
    $metadataVersionCore = runtimeVersionCore($metadataVersion);
    if ($metadataVersionCore !== '' && $fileVersion !== '' && $fileVersion !== $metadataVersionCore) {
        throw new RuntimeException(sprintf(
            'Archive filename version %s disagrees with contained Java version %s.',
            $fileVersion,
            $metadataVersionCore
        ));
    }

    $version = $metadataVersion !== '' ? $metadataVersion : $fileVersion;
    $versionCore = runtimeVersionCore($version);
    if ($versionCore === '') {
        throw new RuntimeException('The Java version cannot be derived from release metadata or the archive filename.');
    }

    $platform = $inspection['platform'] !== '' ? $inspection['platform'] : $branchPlatform;
    if ($platform !== $branchPlatform) {
        throw new RuntimeException(sprintf(
            'Catalog branch platform %s disagrees with archive metadata %s.',
            $branchPlatform,
            $platform
        ));
    }

    $vendor = $inspection['vendor'] !== ''
        ? $inspection['vendor']
        : inferRuntimeVendor($relativePath);
    $distribution = $inspection['distribution'];
    $runtimeId = runtimeSlug(implode('-', array(
        $vendor,
        $distribution,
        $version,
        $platform,
        $installName,
    )));
    if ($runtimeId === '') {
        throw new RuntimeException('A stable runtime identifier cannot be generated.');
    }

    return array(
        'absolute_path' => $absolutePath,
        'path' => $relativePath,
        'catalog_branch' => $catalogBranch,
        'runtime_id' => $runtimeId,
        'file_name' => $fileName,
        'archive' => $archive,
        'platform' => $platform,
        'version' => $version,
        'version_core' => $versionCore,
        'java_major' => runtimeMajorFromVersion($versionCore),
        'vendor' => $vendor,
        'distribution' => $distribution,
        'name' => $installName,
        'install_path' => 'runtime/' . $installName,
        'java_path' => $inspection['java_path'],
        'strip_components' => $inspection['strip_components'],
        'inspection' => $inspection['inspection'],
        'stable' => runtimeIsStableVersion($version),
    );
}
function analyzeRuntimeArchiveEntries(
    array $entries,
    callable $readEntry,
    string $branchPlatform,
    string $inspection
): array {
    $expectedJava = strpos($branchPlatform, 'windows-') === 0 ? 'java.exe' : 'java';
    $expectedJavac = strpos($branchPlatform, 'windows-') === 0 ? 'javac.exe' : 'javac';
    $javaCandidates = array();
    $filesByLowerName = array();

    foreach ($entries as $entry) {
        if ($entry['directory']) {
            continue;
        }
        $lower = strtolower($entry['name']);
        if (isset($filesByLowerName[$lower])) {
            throw new RuntimeException('Runtime archive contains a duplicate file path: ' . $entry['name'] . '.');
        }
        $filesByLowerName[$lower] = $entry;
        $segments = explode('/', $entry['name']);
        $count = count($segments);
        if ($count >= 2
            && strtolower($segments[$count - 2]) === 'bin'
            && strtolower($segments[$count - 1]) === $expectedJava
        ) {
            $javaCandidates[] = array(
                'entry' => $entry,
                'root' => array_slice($segments, 0, $count - 2),
            );
        }
    }

    if (count($javaCandidates) === 0) {
        throw new RuntimeException('Runtime archive does not contain the expected bin/' . $expectedJava . '.');
    }

    $roots = array();
    foreach ($javaCandidates as $candidate) {
        $roots[implode('/', array_map('strtolower', $candidate['root']))] = $candidate;
    }

    $selected = null;
    if (count($roots) === 1) {
        $selected = reset($roots);
    } else {
        // Java 8 JDK archives legitimately contain both <jdk>/bin/java and
        // <jdk>/jre/bin/java. Prefer the unique Java home that also owns javac;
        // it is the complete JDK root, while the nested home is its bundled JRE.
        $jdkRoots = array();
        foreach ($roots as $rootKey => $candidate) {
            $candidatePrefix = $rootKey !== '' ? $rootKey . '/' : '';
            if (isset($filesByLowerName[$candidatePrefix . 'bin/' . $expectedJavac])) {
                $jdkRoots[$rootKey] = $candidate;
            }
        }
        if (count($jdkRoots) === 1) {
            $selected = reset($jdkRoots);
        } else {
            // A unique release file is a safe secondary discriminator for JRE-only
            // archives that happen to contain helper Java homes.
            $releaseRoots = array();
            foreach ($roots as $rootKey => $candidate) {
                $candidatePrefix = $rootKey !== '' ? $rootKey . '/' : '';
                if (isset($filesByLowerName[$candidatePrefix . 'release'])) {
                    $releaseRoots[$rootKey] = $candidate;
                }
            }
            if (count($releaseRoots) === 1) {
                $selected = reset($releaseRoots);
            }
        }
    }
    if (!is_array($selected)) {
        throw new RuntimeException('Runtime archive contains multiple ambiguous Java homes.');
    }
    $rootSegments = $selected['root'];
    $rootPrefix = count($rootSegments) > 0 ? implode('/', $rootSegments) . '/' : '';
    $release = array();
    $releaseKey = strtolower($rootPrefix . 'release');
    if (isset($filesByLowerName[$releaseKey])) {
        $releaseEntry = $filesByLowerName[$releaseKey];
        if ((int) $releaseEntry['size'] > 1024 * 1024) {
            throw new RuntimeException('Runtime release metadata is unexpectedly large.');
        }
        $release = parseJavaReleaseFile($readEntry($releaseEntry['locator']));
    }

    $version = isset($release['JAVA_RUNTIME_VERSION'])
        ? trim((string)$release['JAVA_RUNTIME_VERSION'])
        : (isset($release['JAVA_VERSION']) ? trim((string)$release['JAVA_VERSION']) : '');
    $platform = $release !== array() ? platformFromJavaRelease($release) : '';
    $javacKey = strtolower($rootPrefix . 'bin/' . $expectedJavac);
    return array(
        'vendor' => isset($release['IMPLEMENTOR'])
            ? trim((string) $release['IMPLEMENTOR'])
            : (isset($release['JAVA_VENDOR']) ? trim((string) $release['JAVA_VENDOR']) : ''),
        'version' => $version,
        'platform' => $platform,
        'distribution' => isset($filesByLowerName[$javacKey]) ? 'jdk' : 'jre',
        'strip_components' => count($rootSegments),
        'java_path' => 'bin/' . $expectedJava,
        'inspection' => $release === array()
            ? str_replace('-metadata', '-layout', $inspection)
            : $inspection,
    );
}
function normalizeRuntimeArchiveEntry(string $name): string
{
    if (strpos($name, "\0") !== false) {
        throw new RuntimeException('Archive entry contains a NUL byte.');
    }
    $name = str_replace('\\', '/', $name);
    while (strpos($name, './') === 0) {
        $name = substr($name, 2);
    }
    if ($name === '' || strlen($name) > 4096 || substr($name, 0, 1) === '/') {
        throw new RuntimeException('Archive entry path is empty, absolute or too long.');
    }
    if (preg_match('/^[A-Za-z]:\//D', $name) === 1) {
        throw new RuntimeException('Archive entry contains a Windows absolute path.');
    }
    foreach (explode('/', rtrim($name, '/')) as $segment) {
        if ($segment === '' || $segment === '.' || $segment === '..') {
            throw new RuntimeException('Archive entry contains an unsafe path component: ' . $name . '.');
        }
    }
    return $name;
}
function parseJavaReleaseFile(string $content): array
{
    $result = array();
    foreach (preg_split('/\R/', $content) as $line) {
        if (!is_string($line) || strpos($line, '=') === false) {
            continue;
        }
        list($key, $value) = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);
        if ($key === '') {
            continue;
        }
        if (strlen($value) >= 2 && substr($value, 0, 1) === '"' && substr($value, -1) === '"') {
            $value = stripcslashes(substr($value, 1, -1));
        }
        $result[$key] = $value;
    }
    return $result;
}
