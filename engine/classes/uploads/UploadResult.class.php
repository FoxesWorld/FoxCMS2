<?php

declare(strict_types=1);

final class UploadResult implements JsonSerializable
{
    public function __construct(
        private string $purpose,
        private string $absolutePath,
        private string $relativePath,
        private string $publicPath,
        private string $originalName,
        private string $storedName,
        private string $mime,
        private int $size,
        private string $sha256,
    ) {
    }

    public function purpose(): string { return $this->purpose; }
    public function absolutePath(): string { return $this->absolutePath; }
    public function relativePath(): string { return $this->relativePath; }
    public function publicPath(): string { return $this->publicPath; }
    public function originalName(): string { return $this->originalName; }
    public function storedName(): string { return $this->storedName; }
    public function mime(): string { return $this->mime; }
    public function size(): int { return $this->size; }
    public function sha256(): string { return $this->sha256; }

    public function jsonSerialize(): array
    {
        return [
            'purpose' => $this->purpose,
            'path' => $this->relativePath,
            'url' => $this->publicPath,
            'name' => $this->storedName,
            'mime' => $this->mime,
            'size' => $this->size,
            'sha256' => $this->sha256,
        ];
    }
}
