<?php

declare(strict_types=1);

final class RuntimeJdkCatalog
{
    private const REQUIRED_SYSTEMS = ['windows', 'linux', 'macos'];
    private const ARCHIVE_EXTENSIONS = ['.tar.gz', '.zip', '.tgz'];

    private ?array $snapshot = null;

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

        $families = [];
        $scannedArchives = 0;
        $matchedArchives = 0;
        $ignoredArchives = 0;
        $ignoredCandidates = [];

        foreach ($this->archiveFiles($runtimeRoot) as $absolutePath) {
            ++$scannedArchives;
            $relativePath = $this->relativePath($runtimeRoot, $absolutePath);
            $fileName = basename($absolutePath);
            $archiveName = $this->archiveName($fileName);
            $version = $this->versionFromName($archiveName);
            $major = $this->majorVersion($version);
            $system = $this->systemFromPath($relativePath);

            if ($version === '' || $major <= 0 || $system === null) {
                ++$ignoredArchives;
                if (count($ignoredCandidates) < 100) {
                    $ignoredCandidates[] = [
                        'path' => $relativePath,
                        'name' => $fileName,
                        'version' => $version,
                        'system' => $system,
                        'reason' => $version === '' || $major <= 0
                            ? 'Версия JDK не найдена в имени архива.'
                            : 'Тип системы не найден в пути или имени архива.',
                    ];
                }
                continue;
            }

            ++$matchedArchives;
            $family = (string)$major;
            if (!isset($families[$family])) {
                $families[$family] = [
                    'javaMajor' => $major,
                    'systems' => [],
                    'versions' => [],
                    'versionsBySystem' => [],
                    'names' => [],
                    'files' => [],
                    'formats' => [],
                ];
            }
            $families[$family]['systems'][$system] = true;
            $families[$family]['versions'][$version] = true;
            $families[$family]['versionsBySystem'][$system][$version] = true;
            $families[$family]['names'][$fileName] = true;
            $families[$family]['files'][$system][$relativePath] = true;
            $families[$family]['formats'][$this->archiveFormat($fileName)] = true;
        }

        $options = [];
        foreach ($families as $family => $metadata) {
            if (array_diff(self::REQUIRED_SYSTEMS, array_keys($metadata['systems'])) !== []) {
                continue;
            }

            $names = array_keys($metadata['names']);
            natcasesort($names);
            $versions = array_keys($metadata['versions']);
            usort($versions, static fn(string $left, string $right): int => version_compare($right, $left));
            $files = [];
            $versionsBySystem = [];
            $selectedVersions = [];
            foreach (self::REQUIRED_SYSTEMS as $system) {
                $systemFiles = array_keys($metadata['files'][$system] ?? []);
                natcasesort($systemFiles);
                $files[$system] = array_values($systemFiles);

                $systemVersions = array_keys($metadata['versionsBySystem'][$system] ?? []);
                usort($systemVersions, static fn(string $left, string $right): int => version_compare($right, $left));
                $versionsBySystem[$system] = $systemVersions;
                $selectedVersions[$system] = $systemVersions[0] ?? '';
            }
            $formats = array_keys($metadata['formats']);
            natcasesort($formats);
            $displayNames = array_values($names);

            $options[] = [
                'value' => $family,
                'label' => sprintf(
                    'JDK %s — Windows %s / Linux %s / macOS %s',
                    $family,
                    $selectedVersions['windows'],
                    $selectedVersions['linux'],
                    $selectedVersions['macos'],
                ),
                'version' => $family,
                'javaMajor' => (int)$metadata['javaMajor'],
                'systems' => self::REQUIRED_SYSTEMS,
                'versions' => $versions,
                'versionsBySystem' => $versionsBySystem,
                'selectedVersions' => $selectedVersions,
                'names' => $displayNames,
                'files' => $files,
                'archives' => array_sum(array_map('count', $files)),
                'archiveFormats' => array_values($formats),
            ];
        }

        usort($options, static fn(array $left, array $right): int => (int)$right['javaMajor'] <=> (int)$left['javaMajor']);

        return $this->snapshot = [
            'available' => true,
            'root' => $runtimeRoot,
            'requiredSystems' => self::REQUIRED_SYSTEMS,
            'scannedArchives' => $scannedArchives,
            'matchedArchives' => $matchedArchives,
            'ignoredArchives' => $ignoredArchives,
            'ignoredCandidates' => $ignoredCandidates,
            'mode' => 'major-families-by-file-name',
            'versionSource' => 'archive-file-name-major',
            'systemSource' => 'relative-path-or-file-name',
            'options' => $options,
        ];
    }

    public function normalizeVersion(string $version): ?string
    {
        $version = trim($version);
        if ($version === '') {
            return null;
        }
        $major = $this->majorVersion($version);
        if ($major <= 0) {
            return null;
        }
        $family = (string)$major;
        foreach ($this->scan()['options'] as $option) {
            if (hash_equals((string)$option['value'], $family)) {
                return $family;
            }
        }
        return null;
    }

    public function hasVersion(string $version): bool
    {
        return $this->normalizeVersion($version) !== null;
    }

    public function runtimePath(): string
    {
        return $this->storageDirectory . DIRECTORY_SEPARATOR . 'runtime';
    }

    private function archiveFiles(string $runtimeRoot): array
    {
        $files = [];
        try {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($runtimeRoot, FilesystemIterator::SKIP_DOTS),
                RecursiveIteratorIterator::LEAVES_ONLY,
            );
            foreach ($iterator as $fileInfo) {
                if (!$fileInfo instanceof SplFileInfo
                    || !$fileInfo->isFile()
                    || $fileInfo->isLink()
                    || !$fileInfo->isReadable()
                ) {
                    continue;
                }
                if ($this->isArchiveName($fileInfo->getFilename())) {
                    $files[] = $fileInfo->getPathname();
                }
            }
        } catch (UnexpectedValueException $error) {
            throw new RuntimeException(
                'Не удалось перечислить архивы Java runtime в каталоге ' . $runtimeRoot
                . ': ' . $error->getMessage(),
                0,
                $error,
            );
        }
        natcasesort($files);
        return array_values($files);
    }

    private function isArchiveName(string $fileName): bool
    {
        $lower = strtolower(trim($fileName));
        if ($lower === '' || str_starts_with($lower, '.')) {
            return false;
        }
        foreach (self::ARCHIVE_EXTENSIONS as $extension) {
            if (str_ends_with($lower, $extension)) {
                return true;
            }
        }
        return false;
    }

    private function archiveName(string $fileName): string
    {
        $lower = strtolower($fileName);
        foreach (self::ARCHIVE_EXTENSIONS as $extension) {
            if (str_ends_with($lower, $extension)) {
                return substr($fileName, 0, -strlen($extension));
            }
        }
        return $fileName;
    }

    private function archiveFormat(string $fileName): string
    {
        $lower = strtolower($fileName);
        if (str_ends_with($lower, '.tar.gz') || str_ends_with($lower, '.tgz')) {
            return 'tar.gz';
        }
        return 'zip';
    }

    private function versionFromName(string $archiveName): string
    {
        if (preg_match('/(?:jdk|jre|java)[-_]?([0-9]+(?:\.[0-9]+)*)/i', $archiveName, $match) === 1) {
            return $match[1];
        }
        if (preg_match('/(?:^|[-_])([0-9]+(?:\.[0-9]+)*)(?:$|[-_+])/i', $archiveName, $match) === 1) {
            return $match[1];
        }
        return '';
    }

    private function majorVersion(string $version): int
    {
        if (preg_match('/^(?:1\.)?([0-9]+)/D', $version, $match) !== 1) {
            return 0;
        }
        return (int)$match[1];
    }

    private function systemFromPath(string $relativePath): ?string
    {
        $tokens = preg_split(
            '/[^a-z0-9]+/',
            strtolower(str_replace('\\', '/', $relativePath)),
            -1,
            PREG_SPLIT_NO_EMPTY,
        ) ?: [];
        $tokenSet = array_fill_keys($tokens, true);

        if (isset($tokenSet['windows']) || isset($tokenSet['win'])
            || isset($tokenSet['win32']) || isset($tokenSet['win64'])) {
            return 'windows';
        }
        if (isset($tokenSet['linux']) || isset($tokenSet['unix'])) {
            return 'linux';
        }
        if (isset($tokenSet['mac']) || isset($tokenSet['macos'])
            || isset($tokenSet['osx']) || isset($tokenSet['darwin'])) {
            return 'macos';
        }
        return null;
    }

    private function relativePath(string $runtimeRoot, string $absolutePath): string
    {
        $root = rtrim(str_replace('\\', '/', $runtimeRoot), '/') . '/';
        $path = str_replace('\\', '/', $absolutePath);
        if (!str_starts_with($path, $root)) {
            throw new RuntimeException('Архив расположен вне каталога runtime: ' . $absolutePath . '.');
        }
        return substr($path, strlen($root));
    }
}
