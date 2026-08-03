<?php

declare(strict_types=1);

final class AdminFileManager
{
    private const MAXIMUM_DELETE_DEPTH = 32;
    private const MAXIMUM_DELETE_ENTRIES = 10_000;

    public function __construct(
        private UploadService $uploads,
        private UserSession $session,
        private ?Logger $logger = null,
    ) {
    }

    /** @return array<string, mixed> */
    public function browse(string $relativePath): array
    {
        $directory = $this->resolvePath($relativePath, true);
        $items = [];
        foreach (new DirectoryIterator($directory) as $entry) {
            if ($entry->isDot() || $entry->isLink()) {
                continue;
            }
            $name = $entry->getFilename();
            if ($name === '' || str_starts_with($name, '.')) {
                continue;
            }

            $absolute = $entry->getPathname();
            $relative = $this->relativePath($absolute);
            $isDirectory = $entry->isDir();
            $items[] = [
                'name' => $name,
                'path' => $relative,
                'type' => $isDirectory ? 'directory' : 'file',
                'size' => $isDirectory ? 0 : max(0, (int)$entry->getSize()),
                'modified' => max(0, (int)$entry->getMTime()),
                'extension' => $isDirectory ? '' : strtolower($entry->getExtension()),
                'mime' => $isDirectory ? 'inode/directory' : $this->mime($absolute),
                'url' => $isDirectory ? '' : $this->publicUrl($relative),
            ];
        }
        usort($items, static function (array $left, array $right): int {
            if ($left['type'] !== $right['type']) {
                return $left['type'] === 'directory' ? -1 : 1;
            }
            return strnatcasecmp((string)$left['name'], (string)$right['name']);
        });

        $relative = $this->relativePath($directory);
        $parent = $relative === '' ? null : dirname(str_replace('/', DIRECTORY_SEPARATOR, $relative));
        if ($parent === '.' || $parent === DIRECTORY_SEPARATOR) {
            $parent = '';
        }

        return [
            'root' => '/uploads',
            'path' => $relative,
            'parent' => is_string($parent) ? str_replace(DIRECTORY_SEPARATOR, '/', $parent) : null,
            'items' => $items,
            'writable' => is_writable($directory),
            'totalBytes' => array_sum(array_column($items, 'size')),
        ];
    }

    /** @return array{message:string,type:string} */
    public function createDirectory(string $relativePath, string $name): array
    {
        $directory = $this->resolvePath($relativePath, true);
        $name = $this->safeName($name);
        $target = $directory . DIRECTORY_SEPARATOR . $name;
        if (file_exists($target) || is_link($target)) {
            throw new HttpException('Файл или каталог с таким именем уже существует.', 409);
        }
        if (!mkdir($target, 0755)) {
            throw new HttpException('Не удалось создать каталог.', 500);
        }
        $resolved = realpath($target);
        if (!is_string($resolved) || !$this->insideRoot($resolved, $this->root())) {
            @rmdir($target);
            throw new RuntimeException('Created directory escaped the uploads root.');
        }

        $this->logger?->event('admin.file.directory_created', 'Admin file directory created.', [
            'component' => 'admin_file_manager',
            'operation' => 'create_directory',
            'path' => $this->relativePath($resolved),
        ], 'INFO', 'success');
        return ['message' => 'Каталог создан.', 'type' => 'success'];
    }

    /** @return array<string, mixed> */
    public function upload(string $relativePath, ?array $file): array
    {
        try {
            $result = $this->uploads->store(
                UploadPurpose::ADMIN_FILE,
                $file,
                ['directory' => $relativePath],
            );
        } catch (UploadException $error) {
            throw new HttpException($error->getMessage(), $error->httpStatus(), [], $error);
        }

        return [
            'message' => 'Файл загружен без изменений.',
            'type' => 'success',
            'path' => $result->relativePath(),
            'url' => $result->publicPath(),
            'size' => $result->size(),
            'sha256' => $result->sha256(),
            'mime' => $result->mime(),
            'upload' => $result,
        ];
    }

    /** @return array{message:string,type:string} */
    public function rename(string $relativePath, string $name): array
    {
        $source = $this->resolvePath($relativePath, false);
        $root = $this->root();
        if ($source === $root) {
            throw new HttpException('Корневой каталог переименовать нельзя.', 409);
        }

        $name = $this->safeName($name);
        $target = dirname($source) . DIRECTORY_SEPARATOR . $name;
        if (file_exists($target) || is_link($target)) {
            throw new HttpException('Файл или каталог с таким именем уже существует.', 409);
        }
        if (!$this->insideRoot(dirname($target), $root)) {
            throw new HttpException('Недопустимый путь назначения.', 400);
        }
        if (!rename($source, $target)) {
            throw new HttpException('Не удалось переименовать объект.', 500);
        }

        $resolved = realpath($target);
        if (!is_string($resolved) || !$this->insideRoot($resolved, $root)) {
            @rename($target, $source);
            throw new RuntimeException('Renamed path escaped the uploads root.');
        }
        $this->logger?->event('admin.file.renamed', 'Admin file renamed.', [
            'component' => 'admin_file_manager',
            'operation' => 'rename',
            'from' => $this->relativePath($source),
            'to' => $this->relativePath($resolved),
        ], 'INFO', 'success');
        return ['message' => 'Объект переименован.', 'type' => 'success'];
    }

    /** @return array{message:string,type:string} */
    public function delete(string $relativePath): array
    {
        $target = $this->resolvePath($relativePath, false);
        if ($target === $this->root()) {
            throw new HttpException('Корневой каталог удалить нельзя.', 409);
        }

        $relative = $this->relativePath($target);
        $entries = 0;
        $this->assertDeletableTree($target, 0, $entries);
        $this->deleteTree($target);
        $this->logger?->event('admin.file.deleted', 'Admin file deleted.', [
            'component' => 'admin_file_manager',
            'operation' => 'delete',
            'path' => $relative,
            'entries' => $entries,
        ], 'INFO', 'success');
        return ['message' => 'Объект удалён.', 'type' => 'success'];
    }

    private function root(): string
    {
        $path = ROOT_DIR . UPLOADS_DIR;
        if (!is_dir($path) && !mkdir($path, 0755, true) && !is_dir($path)) {
            throw new HttpException('Каталог uploads недоступен.', 500);
        }
        $root = realpath($path);
        if (!is_string($root) || !is_dir($root)) {
            throw new HttpException('Каталог uploads недоступен.', 500);
        }
        return rtrim($root, '/\\');
    }

    private function resolvePath(string $value, bool $directory): string
    {
        $root = $this->root();
        $relative = $this->safeRelativePath($value);
        $candidate = $relative === ''
            ? $root
            : $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
        $this->rejectSymlinkPath($relative, $root);
        $resolved = realpath($candidate);
        if (!is_string($resolved) || is_link($candidate) || !$this->insideRoot($resolved, $root)) {
            throw new HttpException('Файл или каталог не найден.', 404);
        }
        if ($directory && !is_dir($resolved)) {
            throw new HttpException('Каталог не найден.', 404);
        }
        return $resolved;
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
                throw new HttpException('Переход через символическую ссылку запрещён.', 409);
            }
        }
    }

    private function safeRelativePath(string $value): string
    {
        $value = trim(str_replace('\\', '/', $value), '/');
        if ($value === '') {
            return '';
        }
        if (str_contains($value, "\0")) {
            throw new HttpException('Недопустимый путь.', 400);
        }
        $segments = explode('/', $value);
        foreach ($segments as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..' || str_starts_with($segment, '.')) {
                throw new HttpException('Недопустимый путь.', 400);
            }
            $this->safeName($segment);
        }
        return implode('/', $segments);
    }

    private function safeName(string $value): string
    {
        try {
            return SafeUploadName::validate($value);
        } catch (InvalidArgumentException $error) {
            throw new HttpException('Недопустимое или опасное имя файла или каталога.', 400, [], $error);
        }
    }

    private function insideRoot(string $path, string $root): bool
    {
        return $path === $root || str_starts_with($path, $root . DIRECTORY_SEPARATOR);
    }

    private function relativePath(string $path): string
    {
        $root = $this->root();
        $relative = ltrim(substr($path, strlen($root)), '/\\');
        return str_replace(DIRECTORY_SEPARATOR, '/', $relative);
    }

    private function publicUrl(string $relative): string
    {
        $segments = array_map('rawurlencode', explode('/', $relative));
        return rtrim(UPLOADS_DIR, '/') . '/' . implode('/', $segments);
    }

    private function mime(string $path): string
    {
        try {
            $mime = (new finfo(FILEINFO_MIME_TYPE))->file($path);
            return is_string($mime) && $mime !== '' ? $mime : 'application/octet-stream';
        } catch (Throwable) {
            return 'application/octet-stream';
        }
    }

    private function assertDeletableTree(string $path, int $depth, int &$entries): void
    {
        if ($depth > self::MAXIMUM_DELETE_DEPTH || $entries >= self::MAXIMUM_DELETE_ENTRIES) {
            throw new HttpException('Каталог слишком велик для безопасного удаления через File Manager.', 413);
        }
        if (is_link($path)) {
            throw new HttpException('Символические ссылки удалять через File Manager запрещено.', 409);
        }
        if (!is_file($path) && !is_dir($path)) {
            throw new HttpException('Файл или каталог не найден.', 404);
        }

        $entries++;
        if (!is_dir($path)) {
            return;
        }
        foreach (new DirectoryIterator($path) as $entry) {
            if ($entry->isDot()) {
                continue;
            }
            if ($entry->isLink()) {
                throw new HttpException('Каталог содержит символическую ссылку и не может быть удалён.', 409);
            }
            $this->assertDeletableTree($entry->getPathname(), $depth + 1, $entries);
        }
    }

    private function deleteTree(string $path): void
    {
        if (is_link($path)) {
            throw new HttpException('Символические ссылки удалять через File Manager запрещено.', 409);
        }
        if (is_file($path)) {
            if (!unlink($path)) {
                throw new HttpException('Не удалось удалить файл.', 500);
            }
            return;
        }
        foreach (new DirectoryIterator($path) as $entry) {
            if ($entry->isDot()) {
                continue;
            }
            if ($entry->isLink()) {
                throw new HttpException('Каталог содержит символическую ссылку и не может быть удалён.', 409);
            }
            $this->deleteTree($entry->getPathname());
        }
        if (!rmdir($path)) {
            throw new HttpException('Не удалось удалить каталог.', 500);
        }
    }
}
