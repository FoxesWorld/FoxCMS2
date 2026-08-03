<?php

declare(strict_types=1);

final class InspectedUpload
{
    public function __construct(
        public readonly string $temporaryPath,
        public readonly string $originalName,
        public readonly string $mime,
        public readonly string $extension,
        public readonly int $size,
        public readonly string $sha256,
    ) {
        if ($temporaryPath === '' || $originalName === '' || $mime === '' || $size < 1
            || preg_match('/^[a-f0-9]{64}$/D', $sha256) !== 1) {
            throw new InvalidArgumentException('Invalid inspected upload.');
        }
    }
}
