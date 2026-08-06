<?php

declare(strict_types=1);

namespace FoxCMS\Api\Bootstrap;

use FoxCMS\Api\Core\HttpException;
use FoxCMS\Api\Core\Request;

final class ArtifactCatalog
{
    private const PLATFORM_PATTERN = '/^(?:(?:windows|linux)-(?:x86|x86_64|aarch64)|macos-(?:x86_64|aarch64))$/D';
    private const FILE_NAME_PATTERN = '/^[A-Za-z0-9._-]+$/D';

    private PublishedArtifactInspector $inspector;
    private VersionedArtifactLocator $locator;

    public function __construct(private readonly string $storageDirectory)
    {
        $this->inspector = new PublishedArtifactInspector($storageDirectory);
        $this->locator = new VersionedArtifactLocator();
    }

    public static function requestPlatform(Request $request, string $default = 'windows-x86_64'): string
    {
        $platform = $request->query('platform') ?? $default;
        if (preg_match(self::PLATFORM_PATTERN, $platform) !== 1) {
            throw new HttpException(422, 'unsupported_platform', 'Unsupported bootstrapper platform.', [
                'platform' => $platform,
            ]);
        }
        return $platform;
    }

    /** @return array<string, mixed> */
    public function discoverBootstrapper(string $platform): array
    {
        $root = $this->storageDirectory . DIRECTORY_SEPARATOR . 'bootstrapper' . DIRECTORY_SEPARATOR . $platform;
        foreach ($this->locator->versionDirectories($root) as $version => $directory) {
            $file = $this->locator->bootstrapperFile($directory, $platform);
            if ($file !== null) {
                return [
                    'version' => $version,
                    'platform' => $platform,
                    'artifact' => $this->inspector->describe($file),
                ];
            }
        }
        throw new HttpException(
            404,
            'bootstrapper_platform_unavailable',
            'No usable bootstrapper is published for the requested platform.',
            ['platform' => $platform],
        );
    }

    /** @return array<string, mixed> */
    public function discoverLauncher(string $fileName = 'launcher.jar'): array
    {
        if (preg_match(self::FILE_NAME_PATTERN, $fileName) !== 1 || in_array($fileName, ['.', '..'], true)) {
            throw new HttpException(500, 'bootstrap_configuration_invalid', 'Launcher artifact filename is invalid.');
        }

        $root = $this->storageDirectory . DIRECTORY_SEPARATOR . 'launcher';
        foreach ($this->locator->versionDirectories($root) as $version => $directory) {
            $candidate = $directory . DIRECTORY_SEPARATOR . $fileName;
            if (!is_file($candidate) || is_link($candidate) || !is_readable($candidate)) {
                continue;
            }
            $size = filesize($candidate);
            if ($size !== false && (int)$size > 0) {
                return [
                    'version' => $version,
                    'file_name' => $fileName,
                    'artifact' => $this->inspector->describe($candidate),
                ];
            }
        }
        throw new HttpException(
            503,
            'launcher_artifact_unavailable',
            'No usable launcher artifact is published.',
            ['file_name' => $fileName],
        );
    }

    /** @return array{path: string, url: string, sha256: string, size: int} */
    public function describeFile(string $absolutePath): array
    {
        return $this->inspector->describe($absolutePath);
    }
}
