<?php

declare(strict_types=1);

final class UploadPolicyFactory
{
    private const IMAGE_EXTENSIONS = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
        'image/avif' => 'avif',
    ];

    public function __construct(private UserSession $session)
    {
    }

    public function create(string $purpose, array $context = [], bool $authorize = true): UploadPolicy
    {
        $purpose = UploadPurpose::assert($purpose);
        $ownerUuid = null;
        if (in_array($purpose, [
            UploadPurpose::PROFILE_PHOTO,
            UploadPurpose::MINECRAFT_SKIN,
            UploadPurpose::MINECRAFT_CAPE,
        ], true)) {
            $ownerUuid = $this->ownerUuid($context);
            if ($authorize) {
                $this->assertOwnerOrPermission($ownerUuid, match ($purpose) {
                    UploadPurpose::PROFILE_PHOTO => UploadPermission::PROFILE_ANY,
                    default => UploadPermission::MINECRAFT_ANY,
                });
            }
        }

        return match ($purpose) {
            UploadPurpose::NEWS_COVER => $this->adminImagePolicy(
                $purpose,
                'news',
                8_388_608,
                64,
                8192,
                24_000_000,
                UploadPermission::NEWS_COVER,
                $authorize,
            ),
            UploadPurpose::SLIDER_IMAGE => $this->adminImagePolicy(
                $purpose,
                'slides',
                12_582_912,
                320,
                8192,
                67_108_864,
                UploadPermission::SLIDER_IMAGE,
                $authorize,
            ),
            UploadPurpose::SERVER_IMAGE => $this->serverImagePolicy($authorize),
            UploadPurpose::PROFILE_PHOTO => new UploadPolicy(
                purpose: $purpose,
                directory: 'users/' . Uuid::canonical((string)$ownerUuid),
                createDirectory: true,
                maximumBytes: 5_242_880,
                mimeExtensions: array_diff_key(self::IMAGE_EXTENSIONS, ['image/avif' => true]),
                minimumWidth: 64,
                minimumHeight: 64,
                maximumWidth: 4096,
                maximumHeight: 4096,
                maximumPixels: 12_000_000,
                image: true,
            ),
            UploadPurpose::MINECRAFT_SKIN, UploadPurpose::MINECRAFT_CAPE => new UploadPolicy(
                purpose: $purpose,
                directory: 'users/' . Uuid::canonical((string)$ownerUuid),
                createDirectory: true,
                maximumBytes: 2_097_152,
                mimeExtensions: ['image/png' => 'png'],
                minimumWidth: 16,
                minimumHeight: 16,
                maximumWidth: 1024,
                maximumHeight: 1024,
                maximumPixels: 1_048_576,
                overwrite: true,
                image: true,
                minecraftType: $purpose === UploadPurpose::MINECRAFT_SKIN ? 'skin' : 'cape',
            ),
            UploadPurpose::ADMIN_FILE => $this->adminFilePolicy($authorize),
            default => throw new UploadException('Неизвестное назначение загрузки.', 400),
        };
    }

    public function ownerUuid(array $context): string
    {
        $ownerUuid = trim((string)($context['ownerUuid'] ?? ''));
        if (!Uuid::isValid($ownerUuid)) {
            throw new UploadException('Некорректный владелец загрузки.', 400, ['ownerUuid' => $ownerUuid]);
        }
        return Uuid::normalize($ownerUuid);
    }

    private function adminImagePolicy(
        string $purpose,
        string $directory,
        int $maximumBytes,
        int $minimumDimension,
        int $maximumDimension,
        int $maximumPixels,
        string $permission,
        bool $authorize,
    ): UploadPolicy {
        if ($authorize) {
            $this->assertAdminOrPermission($permission);
        }
        return new UploadPolicy(
            purpose: $purpose,
            directory: $directory,
            createDirectory: true,
            maximumBytes: $maximumBytes,
            mimeExtensions: self::IMAGE_EXTENSIONS,
            minimumWidth: $minimumDimension,
            minimumHeight: $minimumDimension,
            maximumWidth: $maximumDimension,
            maximumHeight: $maximumDimension,
            maximumPixels: $maximumPixels,
            image: true,
        );
    }

    private function serverImagePolicy(bool $authorize): UploadPolicy
    {
        if ($authorize) {
            $this->assertAdminOrPermission(UploadPermission::SERVER_IMAGE);
        }
        return new UploadPolicy(
            purpose: UploadPurpose::SERVER_IMAGE,
            directory: 'servers',
            createDirectory: true,
            maximumBytes: 12_582_912,
            mimeExtensions: [
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/webp' => 'webp',
            ],
            minimumWidth: 320,
            minimumHeight: 180,
            maximumWidth: 8192,
            maximumHeight: 8192,
            maximumPixels: 33_554_432,
            image: true,
        );
    }

    private function adminFilePolicy(bool $authorize): UploadPolicy
    {
        if ($authorize) {
            $this->assertAdminOrPermission(UploadPermission::ADMIN_FILES);
        }
        return new UploadPolicy(
            purpose: UploadPurpose::ADMIN_FILE,
            directory: null,
            createDirectory: false,
            maximumBytes: 67_108_864,
            allowAnyType: true,
        );
    }

    private function assertOwnerOrPermission(string $ownerUuid, string $permission): void
    {
        if (Uuid::equals($this->session->uuid(), $ownerUuid)
            || $this->session->isAdmin()
            || $this->hasPermission($permission)) {
            return;
        }
        throw new UploadException('Недостаточно прав для загрузки в каталог другого пользователя.', 403, [
            'ownerUuid' => $ownerUuid,
            'requiredPermission' => $permission,
        ]);
    }

    private function assertAdminOrPermission(string $permission): void
    {
        if ($this->session->isAdmin() || $this->hasPermission($permission)) {
            return;
        }
        throw new UploadException('Недостаточно прав для этого назначения загрузки.', 403, [
            'requiredPermission' => $permission,
        ]);
    }

    private function hasPermission(string $permission): bool
    {
        $raw = $this->session->get('userPerms', '{}');
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            $permissions = is_array($decoded) ? $decoded : [];
        } elseif (is_array($raw)) {
            $permissions = $raw;
        } else {
            $permissions = [];
        }

        if (in_array('*', $permissions, true) || in_array($permission, $permissions, true)) {
            return true;
        }
        if (($permissions['*'] ?? false) === true || ($permissions[$permission] ?? false) === true) {
            return true;
        }

        $cursor = $permissions;
        foreach (explode('.', $permission) as $segment) {
            if (!is_array($cursor) || !array_key_exists($segment, $cursor)) {
                return false;
            }
            $cursor = $cursor[$segment];
        }
        return $cursor === true;
    }
}
