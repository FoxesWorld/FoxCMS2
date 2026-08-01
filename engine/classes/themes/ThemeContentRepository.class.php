<?php

declare(strict_types=1);

final class ThemeContentRepository
{
    private string $themeDirectory;
    private string $pagesPath;

    public function __construct(string $templatesDirectory, string $themeName)
    {
        if (preg_match('/^[A-Za-z0-9][A-Za-z0-9_-]{0,63}$/D', $themeName) !== 1) {
            throw new InvalidArgumentException('Некорректное имя активной темы.');
        }
        $templatesRoot = realpath($templatesDirectory);
        if (!is_string($templatesRoot) || !is_dir($templatesRoot)) {
            throw new RuntimeException('Каталог тем недоступен.');
        }
        $themeDirectory = realpath($templatesRoot . DIRECTORY_SEPARATOR . $themeName);
        if (!is_string($themeDirectory) || !is_dir($themeDirectory)
            || !str_starts_with($themeDirectory, rtrim($templatesRoot, '/\\') . DIRECTORY_SEPARATOR)) {
            throw new RuntimeException('Активная тема недоступна.');
        }
        $this->themeDirectory = rtrim($themeDirectory, '/\\');
        $this->pagesPath = $this->themeDirectory . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'pages.json';
    }

    public function readProjectPages(): array
    {
        return $this->normalizeProjectPages($this->readJson($this->pagesPath, ['schema' => 1, 'pages' => []]));
    }

    public function saveProjectPages(array $payload): array
    {
        $normalized = $this->normalizeProjectPages($payload);
        $this->writeJson($this->pagesPath, $normalized);
        return $normalized;
    }

    private function normalizeProjectPages(array $payload): array
    {
        $source = $payload['pages'] ?? [];
        if (!is_array($source) || !array_is_list($source) || count($source) > 64) {
            throw new InvalidArgumentException('Страницы проекта должны быть массивом не более чем из 64 элементов.');
        }
        $pages = [];
        $ids = [];
        foreach ($source as $index => $entry) {
            if (!is_array($entry) || array_is_list($entry)) {
                throw new InvalidArgumentException('Страница ' . ($index + 1) . ' должна быть объектом.');
            }
            $id = trim((string)($entry['id'] ?? ''));
            if (preg_match('/^[a-z][a-z0-9-]{1,63}$/D', $id) !== 1 || isset($ids[$id])) {
                throw new InvalidArgumentException('Страница содержит некорректный или повторяющийся ID: ' . $id);
            }
            $ids[$id] = true;
            $layout = trim((string)($entry['layout'] ?? 'default'));
            if (!in_array($layout, ['default', 'rules'], true)) {
                throw new InvalidArgumentException('Страница ' . $id . ' содержит неизвестный layout.');
            }
            $image = trim(str_replace('\\', '/', (string)($entry['image'] ?? '')));
            if ($image !== '') {
                $this->validateImageReference($image);
            }
            $pages[] = [
                'id' => $id,
                'layout' => $layout,
                'eyebrow' => $this->text($entry['eyebrow'] ?? '', 0, 120, 'Надпись страницы ' . $id),
                'title' => $this->text($entry['title'] ?? '', 1, 180, 'Заголовок страницы ' . $id),
                'summary' => $this->text($entry['summary'] ?? '', 0, 1200, 'Краткое описание страницы ' . $id),
                'updated' => $this->text($entry['updated'] ?? '', 0, 80, 'Дата обновления страницы ' . $id),
                'image' => $image,
                'imageAlt' => $this->text($entry['imageAlt'] ?? '', 0, 240, 'Alt изображения страницы ' . $id),
                'imageCaption' => $this->text($entry['imageCaption'] ?? '', 0, 240, 'Подпись изображения страницы ' . $id),
                'sections' => $this->normalizeSections($entry['sections'] ?? [], 'страницы ' . $id),
            ];
        }
        return ['schema' => 1, 'pages' => $pages];
    }

    private function normalizeSections(mixed $source, string $context): array
    {
        if (!is_array($source) || !array_is_list($source) || count($source) > 40) {
            throw new InvalidArgumentException('Секции ' . $context . ' должны быть массивом не более чем из 40 элементов.');
        }
        $sections = [];
        foreach ($source as $index => $entry) {
            if (!is_array($entry) || array_is_list($entry)) {
                throw new InvalidArgumentException('Секция ' . ($index + 1) . ' ' . $context . ' должна быть объектом.');
            }
            $notice = null;
            if (is_array($entry['notice'] ?? null) && !array_is_list($entry['notice'])) {
                $noticeTitle = $this->text($entry['notice']['title'] ?? '', 0, 180, 'Заголовок notice');
                $noticeText = $this->text($entry['notice']['text'] ?? '', 0, 5000, 'Текст notice');
                if ($noticeTitle !== '' || $noticeText !== '') {
                    $notice = ['title' => $noticeTitle, 'text' => $noticeText];
                }
            }
            $cards = [];
            $cardSource = $entry['cards'] ?? [];
            if (!is_array($cardSource) || !array_is_list($cardSource) || count($cardSource) > 24) {
                throw new InvalidArgumentException('Карточки секции должны быть массивом не более чем из 24 элементов.');
            }
            foreach ($cardSource as $card) {
                if (!is_array($card) || array_is_list($card)) {
                    throw new InvalidArgumentException('Карточка секции должна быть объектом.');
                }
                $cards[] = [
                    'title' => $this->text($card['title'] ?? '', 1, 160, 'Заголовок карточки'),
                    'text' => $this->text($card['text'] ?? '', 0, 3000, 'Текст карточки'),
                ];
            }
            $sections[] = [
                'title' => $this->text($entry['title'] ?? '', 0, 180, 'Заголовок секции'),
                'paragraphs' => $this->stringList($entry['paragraphs'] ?? [], 64, 8000, 'Абзацы секции'),
                'items' => $this->stringList($entry['items'] ?? [], 96, 3000, 'Список секции'),
                'cards' => $cards,
                'notice' => $notice,
            ];
        }
        return $sections;
    }

    private function stringList(mixed $source, int $maximumItems, int $maximumLength, string $label): array
    {
        if (!is_array($source) || !array_is_list($source) || count($source) > $maximumItems) {
            throw new InvalidArgumentException($label . ' должны быть массивом не более чем из ' . $maximumItems . ' элементов.');
        }
        $result = [];
        foreach ($source as $item) {
            $text = trim((string)$item);
            if ($text === '') {
                continue;
            }
            if (mb_strlen($text) > $maximumLength) {
                throw new InvalidArgumentException($label . ' содержат слишком длинный элемент.');
            }
            $result[] = $text;
        }
        return $result;
    }

    private function text(mixed $value, int $minimum, int $maximum, string $label): string
    {
        $text = trim((string)$value);
        $length = mb_strlen($text);
        if ($length < $minimum || $length > $maximum) {
            throw new InvalidArgumentException($label . ' должен содержать от ' . $minimum . ' до ' . $maximum . ' символов.');
        }
        return $text;
    }

    private function validateImageReference(string $image): void
    {
        if (strlen($image) > 512 || str_contains($image, "\0") || str_contains($image, '..')) {
            throw new InvalidArgumentException('Некорректный путь к изображению страницы.');
        }
        $uploadsPrefix = '/' . trim(str_replace('\\', '/', UPLOADS_DIR), '/') . '/';
        if (str_starts_with($image, $uploadsPrefix)) {
            $uploadsRoot = realpath(ROOT_DIR . UPLOADS_DIR);
            $relative = rawurldecode(substr($image, strlen($uploadsPrefix)));
            $file = is_string($uploadsRoot)
                ? realpath($uploadsRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative))
                : false;
            if (!is_string($uploadsRoot) || !is_string($file) || !is_file($file)
                || !str_starts_with($file, rtrim($uploadsRoot, '/\\') . DIRECTORY_SEPARATOR)) {
                throw new InvalidArgumentException('Загруженное изображение страницы не найдено.');
            }
            $this->assertImageFile($file);
            return;
        }
        if (str_starts_with($image, '/') || preg_match('/^[A-Za-z]:/D', $image) === 1
            || preg_match('#^[A-Za-z0-9._/-]+$#D', $image) !== 1) {
            throw new InvalidArgumentException('Изображение страницы должно быть ресурсом темы или файлом uploads.');
        }
        $assetRoot = realpath($this->themeDirectory . DIRECTORY_SEPARATOR . 'assets');
        $file = realpath(
            $this->themeDirectory . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR
            . str_replace('/', DIRECTORY_SEPARATOR, $image)
        );
        if (!is_string($assetRoot) || !is_string($file) || !is_file($file)
            || !str_starts_with($file, rtrim($assetRoot, '/\\') . DIRECTORY_SEPARATOR)) {
            throw new InvalidArgumentException('Изображение темы для страницы не найдено.');
        }
        $this->assertImageFile($file);
    }

    private function assertImageFile(string $file): void
    {
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file);
        if (!is_string($mime) || !in_array($mime, ['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'image/avif'], true)) {
            throw new InvalidArgumentException('Файл страницы не является поддерживаемым изображением.');
        }
    }

    private function readJson(string $path, array $fallback): array
    {
        if (!is_file($path)) {
            return $fallback;
        }
        $json = file_get_contents($path);
        if (!is_string($json)) {
            throw new RuntimeException('Не удалось прочитать JSON страниц проекта.');
        }
        $decoded = json_decode($json, true, 128, JSON_THROW_ON_ERROR);
        if (!is_array($decoded) || array_is_list($decoded)) {
            throw new RuntimeException('JSON страниц проекта должен содержать объект.');
        }
        return $decoded;
    }

    private function writeJson(string $path, array $payload): void
    {
        $directory = dirname($path);
        if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
            throw new RuntimeException('Не удалось создать каталог данных темы.');
        }
        $encoded = json_encode(
            $payload,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR,
        ) . PHP_EOL;
        $temporary = $directory . DIRECTORY_SEPARATOR . '.content-' . bin2hex(random_bytes(12)) . '.tmp';
        $backup = null;
        try {
            if (file_put_contents($temporary, $encoded, LOCK_EX) !== strlen($encoded)) {
                throw new RuntimeException('Не удалось записать временный JSON контента.');
            }
            @chmod($temporary, 0640);
            if (is_file($path)) {
                $backup = $directory . DIRECTORY_SEPARATOR . '.content-backup-' . bin2hex(random_bytes(12)) . '.tmp';
                if (!rename($path, $backup)) {
                    throw new RuntimeException('Не удалось подготовить замену JSON контента.');
                }
            }
            if (!rename($temporary, $path)) {
                if (is_string($backup) && is_file($backup)) {
                    @rename($backup, $path);
                }
                throw new RuntimeException('Не удалось атомарно сохранить JSON контента.');
            }
            @chmod($path, 0644);
            if (is_string($backup) && is_file($backup)) {
                @unlink($backup);
            }
        } catch (Throwable $error) {
            if (is_file($temporary)) {
                @unlink($temporary);
            }
            if (is_string($backup) && is_file($backup) && !is_file($path)) {
                @rename($backup, $path);
            }
            throw $error;
        }
    }
}
