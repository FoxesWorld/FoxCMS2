<?php

declare(strict_types=1);

final class ThemeContentRepository
{
    private const MAXIMUM_HTML_BYTES = 524_288;
    private const ALLOWED_TAGS = [
        'article', 'header', 'footer', 'main', 'section', 'nav', 'aside', 'div', 'span',
        'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'p', 'ul', 'ol', 'li',
        'strong', 'b', 'em', 'i', 'u', 's', 'small', 'q', 'blockquote',
        'figure', 'figcaption', 'img', 'a', 'hr', 'br', 'time', 'code', 'pre',
        'dl', 'dt', 'dd', 'table', 'thead', 'tbody', 'tfoot', 'tr', 'th', 'td',
        'details', 'summary', 'kbd', 'samp', 'mark', 'sup', 'sub',
    ];

    private string $pagesDirectory;

    public function __construct(string $templatesDirectory, string $themeName)
    {
        if (preg_match('/^[A-Za-z0-9][A-Za-z0-9_-]{0,63}$/D', $themeName) !== 1) {
            throw new InvalidArgumentException('Некорректное имя активной темы.');
        }
        $templatesRoot = realpath($templatesDirectory);
        $themeDirectory = is_string($templatesRoot)
            ? realpath($templatesRoot . DIRECTORY_SEPARATOR . $themeName)
            : false;
        if (!is_string($templatesRoot) || !is_string($themeDirectory) || !is_dir($themeDirectory)
            || !str_starts_with($themeDirectory, rtrim($templatesRoot, '/\\') . DIRECTORY_SEPARATOR)) {
            throw new RuntimeException('Активная тема недоступна.');
        }
        $this->pagesDirectory = rtrim($themeDirectory, '/\\')
            . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'pages';
    }

    /** @return array{schema:int,pages:list<array{id:string,title:string,html:string}>} */
    public function readProjectPages(): array
    {
        if (!is_dir($this->pagesDirectory)) {
            throw new RuntimeException('Каталог HTML-страниц проекта templates/' . basename(dirname(dirname($this->pagesDirectory))) . '/data/pages не найден.');
        }
        if (is_link($this->pagesDirectory)) {
            throw new RuntimeException('Каталог HTML-страниц проекта не может быть символической ссылкой.');
        }
        $pages = [];
        foreach (new DirectoryIterator($this->pagesDirectory) as $entry) {
            if ($entry->isDot() || $entry->isLink() || !$entry->isFile()
                || strtolower($entry->getExtension()) !== 'html') {
                continue;
            }
            $page = $this->read($entry->getBasename('.html'));
            if (is_array($page)) {
                $pages[] = $page;
            }
        }
        usort($pages, static fn(array $left, array $right): int => strcmp($left['id'], $right['id']));
        return ['schema' => 2, 'pages' => $pages];
    }

    /** @return array{schema:int,pages:list<array{id:string,title:string,html:string}>} */
    public function saveProjectPages(array $payload): array
    {
        $source = $payload['pages'] ?? [];
        if (!is_array($source) || !array_is_list($source) || count($source) > 64) {
            throw new InvalidArgumentException('HTML-страницы проекта должны быть массивом не более чем из 64 элементов.');
        }
        $this->assertWritableDirectory();
        $ids = [];
        foreach ($source as $entry) {
            if (!is_array($entry) || array_is_list($entry)) {
                throw new InvalidArgumentException('HTML-страница проекта должна быть объектом.');
            }
            $id = $this->id((string)($entry['id'] ?? ''));
            if (isset($ids[$id])) {
                throw new InvalidArgumentException('Повторяющийся ID HTML-страницы проекта: ' . $id);
            }
            $ids[$id] = true;
            $sanitized = $this->sanitize((string)($entry['html'] ?? ''), $id);
            $this->write($this->path($id), $sanitized['html']);
        }
        return $this->readProjectPages();
    }

    /** @return array{id:string,title:string,html:string}|null */
    public function read(string $id): ?array
    {
        $id = $this->id($id);
        $path = $this->path($id);
        if (!is_file($path)) {
            return null;
        }
        if (is_link($path)) {
            throw new RuntimeException('HTML-страница проекта не может быть символической ссылкой.');
        }
        $html = file_get_contents($path);
        if (!is_string($html)) {
            throw new RuntimeException('Не удалось прочитать HTML-страницу проекта.');
        }
        $sanitized = $this->sanitize($html, $id);
        return ['id' => $id, 'title' => $sanitized['title'], 'html' => $sanitized['html']];
    }

    /** @return array{title:string,html:string} */
    private function sanitize(string $html, string $id): array
    {
        if ($html === '' || strlen($html) > self::MAXIMUM_HTML_BYTES) {
            throw new InvalidArgumentException('HTML-страница проекта пуста или превышает 512 КиБ.');
        }
        if (preg_match('/<(?:script|style|iframe|object|embed|form|input|button|textarea|select|option|link|meta|base|svg|math)\b/i', $html) === 1
            || preg_match('/\son[a-z]+\s*=/i', $html) === 1
            || preg_match('/(?:javascript|vbscript|data\s*:\s*text\/html)\s*:/i', $html) === 1) {
            throw new InvalidArgumentException('HTML содержит запрещённые исполняемые элементы или атрибуты.');
        }
        if (!class_exists(DOMDocument::class)) {
            return $this->sanitizeWithoutDom($html, $id);
        }
        [$document, $wrapper, $article] = $this->document($html);
        $xpath = new DOMXPath($document);
        $nodes = $xpath->query('.//*', $wrapper);
        if ($nodes !== false) {
            foreach ($nodes as $node) {
                if (!$node instanceof DOMElement) continue;
                $tag = strtolower($node->tagName);
                if (!in_array($tag, self::ALLOWED_TAGS, true)) {
                    throw new InvalidArgumentException('HTML содержит запрещённый элемент <' . $tag . '>.');
                }
                $attributes = [];
                foreach ($node->attributes as $attribute) $attributes[] = [$attribute->name, $attribute->value];
                foreach ($attributes as [$name, $value]) $this->validateAttribute($tag, strtolower($name), $value);
            }
        }
        $article->setAttribute('data-project-page', '1');
        $article->setAttribute('data-page-id', $id);
        $titleNode = $xpath->query('.//h1[1]', $article)?->item(0);
        $title = $titleNode instanceof DOMElement ? trim($titleNode->textContent) : '';
        if ($title === '' || mb_strlen($title) > 180) {
            throw new InvalidArgumentException('HTML-страница проекта должна содержать непустой <h1> длиной до 180 символов.');
        }
        return ['title' => $title, 'html' => $this->fragment($document, $wrapper)];
    }

    /** @return array{title:string,html:string} */
    private function sanitizeWithoutDom(string $html, string $id): array
    {
        if (preg_match('/^\s*<article\b([^>]*)>(.*)<\/article>\s*$/is', $html) !== 1) {
            throw new InvalidArgumentException('HTML-страница проекта должна содержать один корневой <article>.');
        }
        preg_match_all('/<\/?\s*([A-Za-z][A-Za-z0-9-]*)\b[^>]*>/s', $html, $matches);
        foreach ($matches[1] ?? [] as $tagName) {
            $tag = strtolower((string)$tagName);
            if (!in_array($tag, self::ALLOWED_TAGS, true)) {
                throw new InvalidArgumentException('HTML содержит запрещённый элемент <' . $tag . '>.');
            }
        }
        if (preg_match('/<h1\b[^>]*>(.*?)<\/h1>/is', $html, $titleMatch) !== 1) {
            throw new InvalidArgumentException('HTML-страница проекта должна содержать <h1>.');
        }
        $title = trim(html_entity_decode(strip_tags((string)$titleMatch[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if ($title === '' || mb_strlen($title) > 180) {
            throw new InvalidArgumentException('Некорректный заголовок HTML-страницы проекта.');
        }
        $html = $this->replaceRootAttribute($html, 'data-project-page', '1');
        $html = $this->replaceRootAttribute($html, 'data-page-id', $id);
        return ['title' => $title, 'html' => trim($html) . PHP_EOL];
    }

    private function validateAttribute(string $tag, string $name, string $value): void
    {
        if (str_starts_with($name, 'on') || in_array($name, ['style', 'srcdoc'], true)) {
            throw new InvalidArgumentException('Запрещённый HTML-атрибут: ' . $name);
        }
        if (preg_match('/^data-[a-z0-9-]{1,64}$/D', $name) === 1) {
            if (strlen($value) > 512) throw new InvalidArgumentException('Слишком длинный data-атрибут.');
            return;
        }
        if ($name === 'class') {
            if (strlen($value) > 1024 || preg_match('/^[A-Za-z0-9 _:-]*$/D', $value) !== 1) {
                throw new InvalidArgumentException('Некорректное значение class.');
            }
            return;
        }
        if ($name === 'id') {
            if (preg_match('/^[A-Za-z][A-Za-z0-9_-]{0,79}$/D', $value) !== 1) throw new InvalidArgumentException('Некорректное значение id.');
            return;
        }
        if ($name === 'role' || $name === 'title' || str_starts_with($name, 'aria-')) {
            if (strlen($value) > 512) throw new InvalidArgumentException('Слишком длинный атрибут ' . $name . '.');
            return;
        }
        if ($tag === 'a' && in_array($name, ['href', 'target', 'rel'], true)) {
            if ($name === 'href') $this->validateUrl($value, false);
            elseif ($name === 'target' && !in_array($value, ['_self', '_blank'], true)) throw new InvalidArgumentException('Некорректный target ссылки.');
            return;
        }
        if ($tag === 'img' && in_array($name, ['src', 'alt', 'width', 'height', 'loading', 'decoding', 'fetchpriority'], true)) {
            if ($name === 'src') $this->validateUrl($value, true);
            return;
        }
        if ($tag === 'time' && $name === 'datetime') return;
        if (in_array($tag, ['th', 'td'], true) && in_array($name, ['colspan', 'rowspan', 'scope'], true)) return;
        if ($tag === 'details' && $name === 'open') return;
        if ($tag === 'img' && $name === 'hidden') return;
        throw new InvalidArgumentException('Атрибут ' . $name . ' запрещён для <' . $tag . '>.');
    }

    private function validateUrl(string $value, bool $image): void
    {
        $value = trim(html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if ($value === '' && $image) return;
        if (str_contains($value, "\0") || str_contains($value, '\\') || str_contains($value, '..')
            || preg_match('/^(?:javascript|vbscript|data):/i', $value) === 1) {
            throw new InvalidArgumentException('HTML содержит небезопасный URL.');
        }
        if (str_starts_with($value, '#') || str_starts_with($value, '/')) return;
        if (!$image && preg_match('#^(?:https://|mailto:)#i', $value) === 1) return;
        if ($image && preg_match('#^(?:https://)?[A-Za-z0-9._/-]+$#D', $value) === 1) return;
        if (!$image && preg_match('#^[A-Za-z0-9._/#?-]+$#D', $value) === 1) return;
        throw new InvalidArgumentException('HTML содержит URL, не разрешённый политикой проекта.');
    }

    /** @return array{DOMDocument,DOMElement,DOMElement} */
    private function document(string $html): array
    {
        $document = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        try {
            $loaded = $document->loadHTML(
                '<?xml encoding="UTF-8"><!doctype html><html><body><div id="fox-project-wrapper">' . $html . '</div></body></html>',
                LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING,
            );
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
        if ($loaded !== true) throw new InvalidArgumentException('Не удалось разобрать HTML-страницу проекта.');
        $xpath = new DOMXPath($document);
        $wrapper = $xpath->query('//*[@id="fox-project-wrapper"]')->item(0);
        if (!$wrapper instanceof DOMElement) throw new InvalidArgumentException('Не удалось выделить HTML-фрагмент страницы проекта.');
        $roots = [];
        foreach ($wrapper->childNodes as $child) {
            if ($child instanceof DOMText && trim($child->textContent) === '') continue;
            if ($child instanceof DOMElement) { $roots[] = $child; continue; }
            throw new InvalidArgumentException('HTML-страница проекта должна содержать один корневой <article>.');
        }
        if (count($roots) !== 1 || strtolower($roots[0]->tagName) !== 'article') {
            throw new InvalidArgumentException('HTML-страница проекта должна содержать один корневой <article>.');
        }
        return [$document, $wrapper, $roots[0]];
    }

    private function fragment(DOMDocument $document, DOMElement $wrapper): string
    {
        $html = '';
        foreach ($wrapper->childNodes as $child) $html .= $document->saveHTML($child);
        return trim($html) . PHP_EOL;
    }

    private function replaceRootAttribute(string $html, string $attribute, string $value): string
    {
        $escaped = htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
        return preg_replace_callback('/^\s*<article\b([^>]*)>/is', static function(array $match) use ($attribute, $escaped): string {
            $attributes = (string)($match[1] ?? '');
            $pattern = '/\s+' . preg_quote($attribute, '/') . '(?:\s*=\s*(["\']).*?\1)?/is';
            $attributes = preg_match($pattern, $attributes) === 1
                ? (preg_replace($pattern, ' ' . $attribute . '="' . $escaped . '"', $attributes, 1) ?? $attributes)
                : $attributes . ' ' . $attribute . '="' . $escaped . '"';
            return '<article' . $attributes . '>';
        }, $html, 1) ?? $html;
    }

    private function id(string $id): string
    {
        $id = trim($id);
        if (preg_match('/^[a-z][a-z0-9-]{1,63}$/D', $id) !== 1) throw new InvalidArgumentException('Некорректный ID HTML-страницы проекта: ' . $id);
        return $id;
    }

    private function path(string $id): string
    {
        return $this->pagesDirectory . DIRECTORY_SEPARATOR . $this->id($id) . '.html';
    }

    private function assertWritableDirectory(): void
    {
        if (!is_dir($this->pagesDirectory) && !mkdir($this->pagesDirectory, 0755, true) && !is_dir($this->pagesDirectory)) {
            throw new RuntimeException('Не удалось создать каталог HTML-страниц проекта.');
        }
        if (is_link($this->pagesDirectory) || !is_writable($this->pagesDirectory)) {
            throw new RuntimeException('Каталог HTML-страниц проекта недоступен для записи.');
        }
    }

    private function write(string $path, string $html): void
    {
        $directory = dirname($path);
        $temporary = $directory . DIRECTORY_SEPARATOR . '.project-page-' . bin2hex(random_bytes(12)) . '.tmp';
        $backup = null;
        try {
            if (@file_put_contents($temporary, $html, LOCK_EX) !== strlen($html)) throw new RuntimeException('Не удалось записать временный HTML-файл проекта.');
            @chmod($temporary, 0640);
            if (is_file($path)) {
                $backup = $directory . DIRECTORY_SEPARATOR . '.project-page-backup-' . bin2hex(random_bytes(12)) . '.tmp';
                if (!rename($path, $backup)) throw new RuntimeException('Не удалось подготовить замену HTML-страницы проекта.');
            }
            if (!rename($temporary, $path)) {
                if (is_string($backup) && is_file($backup)) @rename($backup, $path);
                throw new RuntimeException('Не удалось атомарно сохранить HTML-страницу проекта.');
            }
            @chmod($path, 0644);
            if (is_string($backup) && is_file($backup)) @unlink($backup);
        } catch (Throwable $error) {
            if (is_file($temporary)) @unlink($temporary);
            if (is_string($backup) && is_file($backup) && !is_file($path)) @rename($backup, $path);
            throw $error;
        }
    }
}
