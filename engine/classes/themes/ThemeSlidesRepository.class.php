<?php

declare(strict_types=1);

final class ThemeSlidesRepository
{
    private string $themeDirectory;
    private string $dataPath;
    private string $frontendPath;

    public function __construct(string $templatesDirectory, string $themeName)
    {
        if (preg_match('/^[A-Za-z0-9][A-Za-z0-9_-]{0,63}$/D', $themeName) !== 1) {
            throw new InvalidArgumentException('Некорректное имя активной темы.');
        }
        $templatesRoot = realpath($templatesDirectory);
        if (!is_string($templatesRoot) || !is_dir($templatesRoot)) {
            throw new RuntimeException('Каталог тем недоступен: ' . $templatesDirectory);
        }
        $themeDirectory = realpath($templatesRoot . DIRECTORY_SEPARATOR . $themeName);
        if (!is_string($themeDirectory) || !is_dir($themeDirectory)
            || !str_starts_with($themeDirectory, rtrim($templatesRoot, '/\\') . DIRECTORY_SEPARATOR)) {
            throw new RuntimeException('Активная тема недоступна: ' . $templatesRoot . DIRECTORY_SEPARATOR . $themeName);
        }
        $this->themeDirectory = rtrim($themeDirectory, '/\\');
        $this->dataPath = $this->themeDirectory . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'slides.json';
        $this->frontendPath = $this->themeDirectory . DIRECTORY_SEPARATOR . 'frontend.json';
    }

    public function read(): array
    {
        if (!is_file($this->dataPath)) {
            return ['schema' => 1, 'eyebrow' => '', 'autoplayMs' => 7000, 'slides' => []];
        }
        error_clear_last();
        $json = @file_get_contents($this->dataPath);
        if (!is_string($json)) {
            throw new RuntimeException('Не удалось прочитать JSON слайдов: ' . $this->dataPath . '. ' . $this->lastFilesystemError());
        }
        $decoded = json_decode($json, true, 64, JSON_THROW_ON_ERROR);
        if (!is_array($decoded) || array_is_list($decoded)) {
            throw new RuntimeException('JSON слайдов должен быть объектом.');
        }
        return $this->normalize($decoded);
    }

    public function save(array $payload): array
    {
        $normalized = $this->normalize($payload);
        $directory = dirname($this->dataPath);
        if (!is_dir($directory)) {
            error_clear_last();
            if (!mkdir($directory, 0755, true) && !is_dir($directory)) {
                throw new RuntimeException(
                    'Не удалось создать каталог данных темы: ' . $directory . '. '
                    . $this->lastFilesystemError()
                );
            }
        }

        clearstatcache(true, $directory);
        if (!is_writable($directory)) {
            throw new RuntimeException(
                'Каталог данных темы недоступен для записи: ' . $directory
                . '. Целевой файл: ' . $this->dataPath
                . '. Права каталога: ' . $this->permissions($directory) . '.'
            );
        }

        $encoded = json_encode(
            $normalized,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR,
        ) . PHP_EOL;
        $temporary = $directory . DIRECTORY_SEPARATOR . '.slides-' . bin2hex(random_bytes(12)) . '.tmp';
        $backup = null;
        try {
            error_clear_last();
            $written = @file_put_contents($temporary, $encoded, LOCK_EX);
            if ($written !== strlen($encoded)) {
                $writtenDescription = $written === false ? 'запись не началась' : 'записано ' . $written . ' из ' . strlen($encoded) . ' байт';
                throw new RuntimeException(
                    'Не удалось записать временный JSON слайдов: ' . $temporary
                    . '. Целевой файл: ' . $this->dataPath
                    . '. Каталог: ' . $directory
                    . '. Результат: ' . $writtenDescription
                    . '. Права каталога: ' . $this->permissions($directory)
                    . '. ' . $this->lastFilesystemError()
                );
            }
            @chmod($temporary, 0640);

            if (is_file($this->dataPath)) {
                $backup = $directory . DIRECTORY_SEPARATOR . '.slides-backup-' . bin2hex(random_bytes(12)) . '.tmp';
                error_clear_last();
                if (!@rename($this->dataPath, $backup)) {
                    throw new RuntimeException(
                        'Не удалось переместить текущий JSON слайдов в резервный файл. Исходный файл: '
                        . $this->dataPath . '. Резервный файл: ' . $backup
                        . '. Каталог: ' . $directory
                        . '. ' . $this->lastFilesystemError()
                    );
                }
            }

            error_clear_last();
            if (!@rename($temporary, $this->dataPath)) {
                $replaceError = $this->lastFilesystemError();
                $restoreResult = 'резервная копия отсутствует';
                if (is_string($backup) && is_file($backup)) {
                    error_clear_last();
                    $restored = @rename($backup, $this->dataPath);
                    $restoreResult = $restored
                        ? 'предыдущий файл восстановлен из ' . $backup
                        : 'не удалось восстановить предыдущий файл из ' . $backup . ': ' . $this->lastFilesystemError();
                }
                throw new RuntimeException(
                    'Не удалось атомарно заменить JSON слайдов. Временный файл: ' . $temporary
                    . '. Целевой файл: ' . $this->dataPath
                    . '. Каталог: ' . $directory
                    . '. Ошибка замены: ' . $replaceError
                    . ' Результат восстановления: ' . $restoreResult . '.'
                );
            }

            @chmod($this->dataPath, 0644);
            if (is_string($backup) && is_file($backup)) {
                @unlink($backup);
            }
        } catch (Throwable $error) {
            if (is_file($temporary)) {
                @unlink($temporary);
            }
            if (is_string($backup) && is_file($backup) && !is_file($this->dataPath)) {
                @rename($backup, $this->dataPath);
            }
            throw $error;
        }
        return $normalized;
    }

    public function routes(): array
    {
        if (!is_file($this->frontendPath)) {
            return [];
        }
        error_clear_last();
        $json = @file_get_contents($this->frontendPath);
        if (!is_string($json)) {
            throw new RuntimeException(
                'Не удалось прочитать маршруты темы: ' . $this->frontendPath . '. '
                . $this->lastFilesystemError()
            );
        }
        $manifest = json_decode($json, true, 64, JSON_THROW_ON_ERROR);
        $routes = is_array($manifest['routes'] ?? null) ? $manifest['routes'] : [];
        $result = [];
        foreach ($routes as $route) {
            if (!is_array($route) || !is_string($route['name'] ?? null) || !is_string($route['path'] ?? null)
                || isset($route['redirect'])) {
                continue;
            }
            $result[] = [
                'name' => $route['name'],
                'path' => $route['path'],
                'title' => is_string($route['title'] ?? null) ? $route['title'] : $route['name'],
            ];
        }
        return $result;
    }

    private function lastFilesystemError(): string
    {
        $error = error_get_last();
        if (!is_array($error) || !is_string($error['message'] ?? null) || trim($error['message']) === '') {
            return 'Системное предупреждение PHP отсутствует.';
        }
        $message = trim(str_replace(["\r", "\n", "\t"], ' ', $error['message']));
        $message = preg_replace('/\s+/u', ' ', $message) ?? $message;
        return 'Системная ошибка: ' . mb_substr($message, 0, 1000, 'UTF-8') . '.';
    }

    private function permissions(string $path): string
    {
        $permissions = @fileperms($path);
        return is_int($permissions) ? substr(sprintf('%o', $permissions), -4) : 'не определены';
    }

    private function normalize(array $payload): array
    {
        $eyebrow = trim((string)($payload['eyebrow'] ?? ''));
        if (mb_strlen($eyebrow) > 100) {
            throw new InvalidArgumentException('Надпись над заголовком не должна превышать 100 символов.');
        }
        $autoplayMs = (int)($payload['autoplayMs'] ?? 7000);
        if ($autoplayMs !== 0 && ($autoplayMs < 3000 || $autoplayMs > 60000)) {
            throw new InvalidArgumentException('Автопереключение должно быть от 3000 до 60000 мс либо 0.');
        }
        $source = $payload['slides'] ?? [];
        if (!is_array($source) || !array_is_list($source) || count($source) > 24) {
            throw new InvalidArgumentException('Слайды должны быть массивом не более чем из 24 элементов.');
        }
        $routeNames = array_column($this->routes(), 'name');
        $slides = [];
        $ids = [];
        foreach ($source as $index => $entry) {
            if (!is_array($entry) || array_is_list($entry)) {
                throw new InvalidArgumentException('Каждый слайд должен быть объектом.');
            }
            $id = trim((string)($entry['id'] ?? ''));
            if (preg_match('/^[a-z][a-z0-9-]{1,63}$/D', $id) !== 1 || isset($ids[$id])) {
                throw new InvalidArgumentException('Слайд ' . ($index + 1) . ' содержит некорректный или повторяющийся ID.');
            }
            $ids[$id] = true;
            $title = trim((string)($entry['title'] ?? ''));
            $description = trim((string)($entry['description'] ?? ''));
            $image = trim(str_replace('\\', '/', (string)($entry['image'] ?? '')));
            $route = trim((string)($entry['route'] ?? ''));
            $action = trim((string)($entry['action'] ?? ''));
            $secondaryRoute = trim((string)($entry['secondaryRoute'] ?? ''));
            $secondaryAction = trim((string)($entry['secondaryAction'] ?? ''));
            if ($title === '' || mb_strlen($title) > 160) {
                throw new InvalidArgumentException('Заголовок слайда ' . ($index + 1) . ' должен содержать от 1 до 160 символов.');
            }
            if (mb_strlen($description) > 600 || mb_strlen($action) > 80 || mb_strlen($secondaryAction) > 80) {
                throw new InvalidArgumentException('Текст слайда ' . ($index + 1) . ' превышает допустимую длину.');
            }
            $this->validateImage($image);
            if ($route === '' || !in_array($route, $routeNames, true)) {
                throw new InvalidArgumentException('Основной маршрут слайда ' . ($index + 1) . ' не существует.');
            }
            if ($secondaryRoute !== '' && !in_array($secondaryRoute, $routeNames, true)) {
                throw new InvalidArgumentException('Дополнительный маршрут слайда ' . ($index + 1) . ' не существует.');
            }
            if (($secondaryRoute === '') !== ($secondaryAction === '')) {
                throw new InvalidArgumentException('Дополнительный маршрут и текст кнопки должны быть заполнены вместе.');
            }
            $slides[] = [
                'id' => $id,
                'enabled' => filter_var($entry['enabled'] ?? true, FILTER_VALIDATE_BOOLEAN),
                'title' => $title,
                'description' => $description,
                'image' => $image,
                'route' => $route,
                'action' => $action !== '' ? $action : 'Подробнее',
                'secondaryRoute' => $secondaryRoute,
                'secondaryAction' => $secondaryAction,
            ];
        }
        return [
            'schema' => 1,
            'eyebrow' => $eyebrow,
            'autoplayMs' => $autoplayMs,
            'slides' => $slides,
        ];
    }

    private function validateImage(string $image): void
    {
        if ($image === '' || strlen($image) > 512 || str_contains($image, "\0") || str_contains($image, '..')) {
            throw new InvalidArgumentException('Некорректный путь к изображению слайда.');
        }
        $uploadPrefix = '/' . trim(str_replace('\\', '/', UPLOADS_DIR), '/') . '/slides/';
        if (str_starts_with($image, $uploadPrefix)) {
            $uploadsRoot = realpath(ROOT_DIR . UPLOADS_DIR);
            $file = realpath(ROOT_DIR . DIRECTORY_SEPARATOR . ltrim(rawurldecode($image), '/'));
            if (!is_string($uploadsRoot) || !is_string($file) || !is_file($file)
                || !str_starts_with($file, rtrim($uploadsRoot, '/\\') . DIRECTORY_SEPARATOR . 'slides' . DIRECTORY_SEPARATOR)) {
                throw new InvalidArgumentException('Загруженное изображение слайда не найдено.');
            }
            $this->assertImageFile($file);
            return;
        }
        if (str_starts_with($image, '/') || preg_match('/^[A-Za-z]:/D', $image) === 1
            || preg_match('#^[A-Za-z0-9._/-]+$#D', $image) !== 1) {
            throw new InvalidArgumentException('Изображение должно быть ресурсом темы или файлом из каталога загрузок слайдов.');
        }
        $assetRoot = realpath($this->themeDirectory . DIRECTORY_SEPARATOR . 'assets');
        $file = realpath($this->themeDirectory . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $image));
        if (!is_string($assetRoot) || !is_string($file) || !is_file($file)
            || !str_starts_with($file, rtrim($assetRoot, '/\\') . DIRECTORY_SEPARATOR)) {
            throw new InvalidArgumentException('Изображение темы для слайда не найдено.');
        }
        $this->assertImageFile($file);
    }

    private function assertImageFile(string $file): void
    {
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file);
        if (!is_string($mime) || !in_array($mime, ['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'image/avif'], true)) {
            throw new InvalidArgumentException('Файл слайда не является поддерживаемым изображением.');
        }
    }
}
