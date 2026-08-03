<?php

declare(strict_types=1);

final class UploadFileInspector
{
    public function __construct(private UploadFilesystem $filesystem)
    {
    }

    public function inspect(?array $file, UploadPolicy $policy): InspectedUpload
    {
        if (!is_array($file)) {
            throw new UploadException('Файл не передан.', 400);
        }
        $error = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error !== UPLOAD_ERR_OK) {
            throw new UploadException($this->uploadErrorMessage($error), 400, ['uploadError' => $error]);
        }

        $temporary = (string)($file['tmp_name'] ?? '');
        if ($temporary === '' || !is_uploaded_file($temporary)) {
            throw new UploadException('Переданный файл не является HTTP-загрузкой.', 400);
        }
        $actualSize = filesize($temporary);
        $reportedSize = max(0, (int)($file['size'] ?? 0));
        if (!is_int($actualSize) || $actualSize < 1 || $actualSize > $policy->maximumBytes) {
            throw new UploadException('Файл пуст или превышает допустимый размер.', 413, [
                'actualSize' => is_int($actualSize) ? $actualSize : -1,
                'maximumBytes' => $policy->maximumBytes,
            ]);
        }
        if ($reportedSize > 0 && $reportedSize !== $actualSize) {
            throw new UploadException('Размер загруженного файла не совпадает с заявленным.', 400, [
                'reportedSize' => $reportedSize,
                'actualSize' => $actualSize,
            ]);
        }

        $originalName = $this->filesystem->safeFileName(
            basename(str_replace('\\', '/', (string)($file['name'] ?? 'upload.bin'))),
        );
        $mime = $this->detectMime($temporary, $policy->allowAnyType);
        $originalExtension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $extension = $this->assertMimeForPolicy($mime, $policy, $originalExtension);
        if ($policy->image) {
            $this->validateImage($temporary, $policy, $actualSize);
        }

        $hash = hash_file('sha256', $temporary);
        if (!is_string($hash) || preg_match('/^[a-f0-9]{64}$/D', $hash) !== 1) {
            throw new UploadException('Не удалось проверить целостность файла.', 500);
        }

        return new InspectedUpload(
            $temporary,
            $originalName,
            $mime,
            $extension,
            $actualSize,
            $hash,
        );
    }

    public function validateExistingFile(string $path, UploadPolicy $policy): void
    {
        if (!is_file($path) || !is_readable($path)) {
            throw new UploadException('Указанный файл не найден или недоступен.', 404);
        }
        $size = filesize($path);
        if (!is_int($size) || $size < 1 || $size > $policy->maximumBytes) {
            throw new UploadException('Файл пуст или превышает допустимый размер.', 413, [
                'maximumBytes' => $policy->maximumBytes,
            ]);
        }
        $mime = $this->detectMime($path, $policy->allowAnyType);
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $this->assertMimeForPolicy($mime, $policy, $extension);
        if ($policy->image) {
            $this->validateImage($path, $policy, $size);
        }
    }

    private function assertMimeForPolicy(
        string $mime,
        UploadPolicy $policy,
        string $originalExtension,
    ): string {
        if (is_array($policy->mimeExtensions)) {
            $extension = $policy->mimeExtensions[$mime] ?? null;
            if (!is_string($extension) || $extension === '') {
                throw new UploadException('Тип файла не разрешён для этого назначения.', 415, ['mime' => $mime]);
            }
            return $extension;
        }

        $blockedMime = array_map('strtolower', $policy->blockedMime);
        if (in_array(strtolower($mime), $blockedMime, true)) {
            throw new UploadException('Этот MIME-тип запрещён для загрузки.', 415, ['mime' => $mime]);
        }
        if ($originalExtension !== ''
            && in_array(strtolower($originalExtension), $policy->blockedExtensions, true)) {
            throw new UploadException('Это расширение запрещено для загрузки.', 415, [
                'extension' => $originalExtension,
            ]);
        }
        return $originalExtension;
    }

    private function validateImage(string $path, UploadPolicy $policy, int $size): void
    {
        $dimensions = @getimagesize($path);
        if (!is_array($dimensions)) {
            throw new UploadException('Декодер изображений отклонил файл.', 422);
        }
        $width = (int)($dimensions[0] ?? 0);
        $height = (int)($dimensions[1] ?? 0);
        if ($width < $policy->minimumWidth || $height < $policy->minimumHeight
            || $width > $policy->maximumWidth || $height > $policy->maximumHeight) {
            throw new UploadException('Недопустимые размеры изображения.', 422, [
                'width' => $width,
                'height' => $height,
                'minimumWidth' => $policy->minimumWidth,
                'minimumHeight' => $policy->minimumHeight,
                'maximumWidth' => $policy->maximumWidth,
                'maximumHeight' => $policy->maximumHeight,
            ]);
        }

        $pixels = $width * $height;
        if ($pixels > $policy->maximumPixels) {
            throw new UploadException('Изображение содержит слишком много пикселей.', 422, [
                'width' => $width,
                'height' => $height,
                'pixels' => $pixels,
                'maximumPixels' => $policy->maximumPixels,
            ]);
        }
        if ($policy->minecraftType !== null
            && !$this->validMinecraftDimensions($policy->minecraftType, $width, $height)) {
            throw new UploadException('Размеры текстуры не поддерживаются Minecraft.', 422, [
                'textureType' => $policy->minecraftType,
                'width' => $width,
                'height' => $height,
            ]);
        }
        $this->probeImage($path, $size, $pixels);
    }

    private function detectMime(string $path, bool $fallbackToBinary): string
    {
        try {
            $mime = (new finfo(FILEINFO_MIME_TYPE))->file($path);
        } catch (Throwable $error) {
            if ($fallbackToBinary) {
                return 'application/octet-stream';
            }
            throw new UploadException('Не удалось определить MIME-тип файла.', 415, [], $error);
        }
        if (!is_string($mime) || trim($mime) === '') {
            if ($fallbackToBinary) {
                return 'application/octet-stream';
            }
            throw new UploadException('Не удалось определить MIME-тип файла.', 415);
        }
        return strtolower(trim($mime));
    }

    private function probeImage(string $path, int $size, int $pixels): void
    {
        if (!extension_loaded('gd') || $size > 8_388_608 || $pixels > 12_000_000) {
            return;
        }
        $data = file_get_contents($path);
        if (!is_string($data)) {
            throw new UploadException('Не удалось прочитать изображение.', 422);
        }
        $image = @imagecreatefromstring($data);
        if (!$image instanceof GdImage) {
            throw new UploadException('Декодер изображений отклонил файл.', 422);
        }
        imagedestroy($image);
    }

    private function validMinecraftDimensions(string $type, int $width, int $height): bool
    {
        if ($type === 'skin') {
            return $this->isPowerOfTwo($width) && ($height === $width || $height * 2 === $width);
        }
        return ($width === 22 && $height === 17)
            || ($this->isPowerOfTwo($width) && $height * 2 === $width);
    }

    private function isPowerOfTwo(int $value): bool
    {
        return $value > 0 && ($value & ($value - 1)) === 0;
    }

    private function uploadErrorMessage(int $error): string
    {
        return match ($error) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Файл превышает серверный лимит загрузки.',
            UPLOAD_ERR_PARTIAL => 'Файл был загружен не полностью.',
            UPLOAD_ERR_NO_FILE => 'Файл не выбран.',
            UPLOAD_ERR_NO_TMP_DIR => 'На сервере отсутствует временный каталог загрузок.',
            UPLOAD_ERR_CANT_WRITE => 'Сервер не смог записать временный файл.',
            UPLOAD_ERR_EXTENSION => 'Загрузка остановлена серверным расширением.',
            default => 'Неизвестная ошибка загрузки файла.',
        };
    }
}
