<?php

declare(strict_types=1);

final class UploadPurpose
{
    public const MINECRAFT_SKIN = 'minecraft.skin';
    public const MINECRAFT_CAPE = 'minecraft.cape';
    public const PROFILE_PHOTO = 'profile.photo';
    public const NEWS_COVER = 'news.cover';
    public const SLIDER_IMAGE = 'slider.image';
    public const ADMIN_FILE = 'admin.file';

    private const VALUES = [
        self::MINECRAFT_SKIN,
        self::MINECRAFT_CAPE,
        self::PROFILE_PHOTO,
        self::NEWS_COVER,
        self::SLIDER_IMAGE,
        self::ADMIN_FILE,
    ];

    public static function assert(string $purpose): string
    {
        $purpose = trim($purpose);
        if (!in_array($purpose, self::VALUES, true)) {
            throw new UploadException('Неизвестное назначение загрузки.', 400, ['purpose' => $purpose]);
        }
        return $purpose;
    }
}
