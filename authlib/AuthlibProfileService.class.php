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
        $userUuid = Uuid::normalize($userUuid);
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
        $userUuid = Uuid::normalize($userUuid);
        $this->assertUsername($username);
        $profileId = Uuid::compact($userUuid);
        $uuidDirectory = $this->rootDirectory . '/uploads/users/' . $userUuid . '/';
        $uuidPublic = $this->publicBaseUrl . '/uploads/users/' . rawurlencode($userUuid) . '/';
        $legacyDirectory = $this->rootDirectory . '/uploads/users/' . $username . '/';
        $legacyPublic = $this->publicBaseUrl . '/uploads/users/' . rawurlencode($username) . '/';
        $legacyStem = md5($username);

        $skin = $this->resolveTexture(
            $uuidDirectory . $profileId . '-skin.png',
            $uuidPublic . $profileId . '-skin.png',
            $legacyDirectory . $legacyStem . '-skin.png',
            $legacyPublic . $legacyStem . '-skin.png',
        );
        $textures = [
            'SKIN' => ['url' => $skin ?? $this->publicBaseUrl . '/uploads/users/default_skin.png'],
        ];

        $cape = $this->resolveTexture(
            $uuidDirectory . $profileId . '-cape.png',
            $uuidPublic . $profileId . '-cape.png',
            $legacyDirectory . $legacyStem . '-cape.png',
            $legacyPublic . $legacyStem . '-cape.png',
        );
        if ($cape !== null) {
            $textures['CAPE'] = ['url' => $cape];
        }

        return $textures;
    }

    private function resolveTexture(
        string $uuidPath,
        string $uuidUrl,
        string $legacyPath,
        string $legacyUrl,
    ): ?string {
        if (is_file($uuidPath)) {
            return $uuidUrl;
        }
        return is_file($legacyPath) ? $legacyUrl : null;
    }

    private function assertUsername(string $username): void
    {
        if (preg_match('/^[A-Za-z0-9_.-]{1,64}$/D', $username) !== 1) {
            throw new InvalidArgumentException('Invalid username.');
        }
    }
}
