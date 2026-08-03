<?php

declare(strict_types=1);

final class UploadFilesystem
{
    public function resolveDestinationDirectory(UploadPolicy $policy, array $context = []): string
    {
        $root = $this->root();
        $relative = $this->policyDirectory($policy, $context);
        $this->rejectSymlinkPath($relative, $root);
        $candidate = $relative === ''
            ? $root
            : $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);

        if ($policy->createDirectory
            && !is_dir($candidate)
            && !mkdir($candidate, 0755, true)
            && !is_dir($candidate)) {
            throw new UploadException('Не удалось создать целевой каталог.', 500, ['directory' => $relative]);
        }

        $resolved = realpath($candidate);
        if (!is_string($resolved) || !is_dir($resolved) || is_link($candidate) || !$this->insideRoot($resolved, $root)) {
            throw new UploadException('Загрузка в указанный каталог запрещена.', 403, ['directory' => $relative]);
        }
        if (!is_writable($resolved)) {
            throw new UploadException('Целевой каталог недоступен для записи.', 503, ['directory' => $relative]);
        }
        return $resolved;
    }

    public function resolveNewFilePath(string $directory, string $name): string
    {
        $name = $this->safeFileName($name);
        $root = $this->root();
        if (!$this->insideRoot($directory, $root)) {
            throw new UploadException('Целевой путь находится вне uploads.', 403);
        }

        $candidate = $directory . DIRECTORY_SEPARATOR . $name;
        if (is_link($candidate)) {
            throw new UploadException('Запись через символическую ссылку запрещена.', 409);
        }
        return $candidate;
    }

    /** @return array{absolute:string,relative:string,public:string} */
    public function resolveReference(string $publicPath, UploadPolicy $policy, array $context = []): array
    {
        $relative = $this->relativeFromPublicPath($publicPath);
        $expectedDirectory = $this->policyDirectory($policy, $context);
        if ($expectedDirectory !== ''
            && $relative !== $expectedDirectory
            && !str_starts_with($relative, $expectedDirectory . '/')) {
            throw new UploadException('Файл не относится к разрешённому назначению.', 403, [
                'purpose' => $policy->purpose,
                'path' => $relative,
            ]);
        }

        $root = $this->root();
        $this->rejectSymlinkPath($relative, $root);
        $candidate = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
        $resolved = realpath($candidate);
        if (!is_string($resolved) || !is_file($resolved) || is_link($candidate) || !$this->insideRoot($resolved, $root)) {
            throw new UploadException('Файл загрузки не найден.', 404, [
                'purpose' => $policy->purpose,
                'path' => $relative,
            ]);
        }

        return [
            'absolute' => $resolved,
            'relative' => $relative,
            'public' => $this->publicPath($relative),
        ];
    }

    public function removeResolvedReference(string $absolutePath): void
    {
        $root = $this->root();
        $resolved = realpath($absolutePath);
        if (!is_string($resolved) || !is_file($resolved) || is_link($absolutePath) || !$this->insideRoot($resolved, $root)) {
            throw new UploadException('Файл загрузки не найден.', 404);
        }
        if (!unlink($resolved)) {
            throw new UploadException('Не удалось удалить загруженный файл.', 500);
        }
    }

    public function publish(
        string $purpose,
        InspectedUpload $upload,
        string $destination,
        bool $overwrite,
    ): UploadResult {
        if (is_link($destination)) {
            throw new UploadException('Запись через символическую ссылку запрещена.', 409);
        }
        if (file_exists($destination) && !$overwrite) {
            throw new UploadException('Файл с таким именем уже существует.', 409, [
                'target' => $this->relativePath($destination),
            ]);
        }

        $directory = dirname($destination);
        if (!$this->insideRoot($directory, $this->root())) {
            throw new UploadException('Целевой путь находится вне uploads.', 403);
        }
        $staging = $directory . DIRECTORY_SEPARATOR . '.upload-' . bin2hex(random_bytes(12)) . '.tmp';
        $backup = null;

        try {
            if (!move_uploaded_file($upload->temporaryPath, $staging)) {
                throw new UploadException('Не удалось принять загруженный файл.', 500);
            }
            @chmod($staging, 0640);
            $this->verifyPublishedFile($staging, $upload->size, $upload->sha256);

            if ($overwrite && is_file($destination)) {
                if (is_link($destination)) {
                    throw new UploadException('Замена символической ссылки запрещена.', 409);
                }
                $backup = $directory . DIRECTORY_SEPARATOR . '.upload-backup-' . bin2hex(random_bytes(12)) . '.tmp';
                if (!rename($destination, $backup)) {
                    throw new UploadException('Не удалось подготовить замену существующего файла.', 500);
                }
            }
            if (!rename($staging, $destination)) {
                if (is_string($backup) && is_file($backup)) {
                    @rename($backup, $destination);
                }
                throw new UploadException('Не удалось атомарно опубликовать файл.', 500);
            }
            if (is_string($backup) && is_file($backup)) {
                @unlink($backup);
            }
            @chmod($destination, 0644);
            $this->verifyPublishedFile($destination, $upload->size, $upload->sha256);
        } catch (Throwable $error) {
            if (is_file($staging)) {
                @unlink($staging);
            }
            if (is_string($backup) && is_file($backup) && !is_file($destination)) {
                @rename($backup, $destination);
            }
            if ($error instanceof UploadException) {
                throw $error;
            }
            throw new UploadException('Не удалось опубликовать файл.', 500, [], $error);
        }

        $relative = $this->relativePath($destination);
        return new UploadResult(
            $purpose,
            $destination,
            $relative,
            $this->publicPath($relative),
            $upload->originalName,
            basename($destination),
            $upload->mime,
            $upload->size,
            $upload->sha256,
        );
    }

    public function policyDirectory(UploadPolicy $policy, array $context = []): string
    {
        if ($policy->directory !== null) {
            return $this->safeRelativePath($policy->directory);
        }
        return $this->safeRelativePath((string)($context['directory'] ?? ''));
    }

    public function safeFileName(string $name): string
    {
        try {
            return SafeUploadName::validate($name);
        } catch (InvalidArgumentException $error) {
            throw new UploadException('Недопустимое или опасное имя файла.', 400, [
                'name' => mb_substr(trim($name), 0, 180),
            ], $error);
        }
    }

    public function publicPath(string $relative): string
    {
        $segments = array_map('rawurlencode', explode('/', $relative));
        return '/' . trim(str_replace('\\', '/', UPLOADS_DIR), '/') . '/' . implode('/', $segments);
    }

    private function root(): string
    {
        $candidate = ROOT_DIR . str_replace('/', DIRECTORY_SEPARATOR, UPLOADS_DIR);
        if (!is_dir($candidate) && !mkdir($candidate, 0755, true) && !is_dir($candidate)) {
            throw new UploadException('Корневой каталог uploads недоступен.', 503);
        }
        $root = realpath($candidate);
        if (!is_string($root) || !is_dir($root) || is_link($candidate)) {
            throw new UploadException('Корневой каталог uploads недоступен.', 503);
        }
        return rtrim($root, '/\\');
    }

    private function relativeFromPublicPath(string $publicPath): string
    {
        $path = parse_url(trim(str_replace('\\', '/', $publicPath)), PHP_URL_PATH);
        $prefix = '/' . trim(str_replace('\\', '/', UPLOADS_DIR), '/') . '/';
        if (!is_string($path) || !str_starts_with($path, $prefix)) {
            throw new UploadException('Некорректный публичный путь загрузки.', 400);
        }
        return $this->safeRelativePath(rawurldecode(substr($path, strlen($prefix))));
    }

    private function relativePath(string $absolutePath): string
    {
        $root = $this->root();
        if (!$this->insideRoot($absolutePath, $root)) {
            throw new UploadException('Файл находится вне uploads.', 500);
        }
        return str_replace(DIRECTORY_SEPARATOR, '/', ltrim(substr($absolutePath, strlen($root)), '/\\'));
    }

    private function safeRelativePath(string $value): string
    {
        $value = trim(str_replace('\\', '/', $value), '/');
        if ($value === '') {
            return '';
        }
        if (str_contains($value, "\0")) {
            throw new UploadException('Недопустимый путь загрузки.', 400);
        }
        $segments = explode('/', $value);
        foreach ($segments as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..' || str_starts_with($segment, '.')) {
                throw new UploadException('Недопустимый путь загрузки.', 400, ['segment' => $segment]);
            }
            $this->safeFileName($segment);
        }
        return implode('/', $segments);
    }

    private function rejectSymlinkPath(string $relative, string $root): void
    {
        if ($relative === '') {
            return;
        }
        $cursor = $root;
        foreach (explode('/', $relative) as $segment) {
            $cursor .= DIRECTORY_SEPARATOR . $segment;
            if (is_link($cursor)) {
                throw new UploadException('Переход через символическую ссылку запрещён.', 409, [
                    'path' => $relative,
                ]);
            }
        }
    }

    private function insideRoot(string $path, string $root): bool
    {
        $path = rtrim($path, '/\\');
        $root = rtrim($root, '/\\');
        return $path === $root || str_starts_with($path, $root . DIRECTORY_SEPARATOR);
    }

    private function verifyPublishedFile(string $path, int $size, string $sha256): void
    {
        clearstatcache(true, $path);
        $actualSize = filesize($path);
        $actualHash = hash_file('sha256', $path);
        if (!is_int($actualSize) || $actualSize !== $size
            || !is_string($actualHash) || !hash_equals($sha256, $actualHash)) {
            throw new UploadException('Проверка целостности опубликованного файла завершилась ошибкой.', 500);
        }
    }
}
