<?php

declare(strict_types=1);

namespace FoxCMS\Api\Bootstrap;

final class ManifestBuilder
{
    private const SCHEMA_VERSION = 1;

    public function __construct(private readonly BootstrapSettings $settings)
    {
    }

    /** @return array<string, mixed> */
    public function build(string $platform): array
    {
        $storageDirectory = $this->settings->storageDirectory();
        $catalog = new ArtifactCatalog($storageDirectory);
        $bootstrapper = $catalog->discoverBootstrapper($platform);
        $launcher = $catalog->discoverLauncher($this->settings->launcherFileName());

        return [
            'schema_version' => self::SCHEMA_VERSION,
            'bootstrapper' => [
                'version' => $bootstrapper['version'],
                'artifacts' => [$platform => $this->publicArtifact($bootstrapper['artifact'])],
            ],
            'jvm' => (new RuntimeCatalog())->resolve($storageDirectory),
            'launcher' => [
                'version' => $launcher['version'],
                'file_name' => $launcher['file_name'],
                'artifact' => $this->publicArtifact($launcher['artifact']),
                'jvm_args' => $this->settings->launcherJvmArgs(),
                'launcher_args' => $this->settings->launcherArgs(),
            ],
        ];
    }

    /** @param array<string, mixed> $artifact */
    private function publicArtifact(array $artifact): array
    {
        return [
            'url' => $artifact['url'],
            'sha256' => $artifact['sha256'],
            'size' => $artifact['size'],
        ];
    }
}
