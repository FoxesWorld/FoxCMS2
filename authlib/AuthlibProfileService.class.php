<?php

declare(strict_types=1);

final class AuthlibProfileService
{
    public function __construct(
        private string $rootDirectory,
        private string $publicBaseUrl,
    ) {
        $this->rootDirectory = rtrim($rootDirectory, '/\\');
        $this->publicBaseUrl = rtrim($publicBaseUrl, '/');
    }

    /** @return array<string, mixed> */
    public function profile(string $userUuid, string $username): array
    {
        $userUuid = Uuid::canonical($userUuid);
        $this->assertUsername($username);
        $profileId = Uuid::compact($userUuid);
        $property = [
            'timestamp' => (int)floor(microtime(true) * 1000),
            'profileId' => $profileId,
            'profileName' => $username,
            'signatureRequired' => false,
            'textures' => $this->textures($userUuid, $username),
        ];
        $encodedProperty = json_encode(
            $property,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
        );

        return [
            'id' => $profileId,
            'name' => $username,
            'properties' => [[
                'name' => 'textures',
                'value' => base64_encode($encodedProperty),
            ]],
        ];
    }

    /** @return array<string, array{url:string}> */
    public function textures(string $userUuid, string $username): array
    {
        $userUuid = Uuid::canonical($userUuid);
        $this->assertUsername($username);
        $profileId = Uuid::compact($userUuid);
        $textureId = $userUuid;
        $uuidDirectory = $this->rootDirectory . '/uploads/users/' . $userUuid . '/';
        $uuidPublic = $this->publicBaseUrl . '/uploads/users/' . rawurlencode($userUuid) . '/';
        $compactDirectory = $this->rootDirectory . '/uploads/users/' . $profileId . '/';
        $compactPublic = $this->publicBaseUrl . '/uploads/users/' . rawurlencode($profileId) . '/';
        $skin = $this->resolveTexture([
            [$uuidDirectory . $textureId . '-skin.png', $uuidPublic . $textureId . '-skin.png'],
            [$uuidDirectory . $profileId . '-skin.png', $uuidPublic . $profileId . '-skin.png'],
            [$compactDirectory . $textureId . '-skin.png', $compactPublic . $textureId . '-skin.png'],
            [$compactDirectory . $profileId . '-skin.png', $compactPublic . $profileId . '-skin.png'],
        ]);
        $textures = [
            'SKIN' => ['url' => $skin ?? $this->publicBaseUrl . '/uploads/users/default_skin.png'],
        ];

        $cape = $this->resolveTexture([
            [$uuidDirectory . $textureId . '-cape.png', $uuidPublic . $textureId . '-cape.png'],
            [$uuidDirectory . $profileId . '-cape.png', $uuidPublic . $profileId . '-cape.png'],
            [$compactDirectory . $textureId . '-cape.png', $compactPublic . $textureId . '-cape.png'],
            [$compactDirectory . $profileId . '-cape.png', $compactPublic . $profileId . '-cape.png'],
        ]);
        if ($cape !== null) {
            $textures['CAPE'] = ['url' => $cape];
        }

        return $textures;
    }

    /** @param list<array{0:string,1:string}> $candidates */
    private function resolveTexture(array $candidates): ?string
    {
        foreach ($candidates as [$path, $url]) {
            if (is_file($path)) {
                return $url;
            }
        }
        return null;
    }

    private function assertUsername(string $username): void
    {
        if (preg_match('/^[A-Za-z0-9_.-]{1,64}$/D', $username) !== 1) {
            throw new InvalidArgumentException('Invalid username.');
        }
    }
}
