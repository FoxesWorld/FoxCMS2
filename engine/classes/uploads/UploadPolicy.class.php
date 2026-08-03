<?php

declare(strict_types=1);

final class UploadPolicy
{
    /**
     * @param array<string, string>|null $mimeExtensions
     * @param list<string> $blockedMime
     * @param list<string> $blockedExtensions
     */
    public function __construct(
        public readonly string $purpose,
        public readonly ?string $directory,
        public readonly bool $createDirectory,
        public readonly int $maximumBytes,
        public readonly ?array $mimeExtensions = null,
        public readonly array $blockedMime = [],
        public readonly array $blockedExtensions = [],
        public readonly int $minimumWidth = 0,
        public readonly int $minimumHeight = 0,
        public readonly int $maximumWidth = 0,
        public readonly int $maximumHeight = 0,
        public readonly int $maximumPixels = 0,
        public readonly bool $overwrite = false,
        public readonly bool $image = false,
        public readonly bool $allowAnyType = false,
        public readonly ?string $minecraftType = null,
    ) {
        if ($purpose === '' || $maximumBytes < 1) {
            throw new InvalidArgumentException('Invalid upload policy.');
        }
        if ($image && (
            $minimumWidth < 1
            || $minimumHeight < 1
            || $maximumWidth < $minimumWidth
            || $maximumHeight < $minimumHeight
            || $maximumPixels < 1
        )) {
            throw new InvalidArgumentException('Invalid image upload policy.');
        }
    }
}
