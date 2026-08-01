<?php

declare(strict_types=1);

final class ThemeBadgePageRepository
{
    private const MAXIMUM_HTML_BYTES = 262_144;
    private const MAXIMUM_BADGE_NAME_LENGTH = 191;

    private const ALLOWED_TAGS = [
        'article', 'header', 'section', 'div', 'span',
        'h1', 'h2', 'h3', 'h4', 'p', 'ul', 'ol', 'li',
        'strong', 'b', 'em', 'small', 'q', 'blockquote',
        'figure', 'figcaption', 'img', 'a', 'hr', 'br',
        'time', 'code', 'pre',
    ];

    private const DATA_ATTRIBUTES = [
        'data-badge-page',
        'data-badge-name',
        'data-badge-slug',
        'data-badge-title',
        'data-badge-description',
        'data-badge-image',
        'data-badge-eyebrow',
        'data-badge-history',
    ];

    private string $pagesDirectory;

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
        $this->pagesDirectory = rtrim($themeDirectory, '/\\')
            . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'badges';
    }

    /** @return array{pages: list<array{badgeName: string, slug: string, html: string}>} */
    public function list(): array
    {
        if (!is_dir($this->pagesDirectory)) {
            return ['pages' => []];
        }
        if (is_link($this->pagesDirectory)) {
            throw new RuntimeException('Каталог HTML-страниц бейджей не может быть символической ссылкой.');
        }
        $pages = [];
        foreach (new DirectoryIterator($this->pagesDirectory) as $entry) {
            if ($entry->isDot() || $entry->isLink() || !$entry->isFile()
                || strtolower($entry->getExtension()) !== 'html') {
                continue;
            }
            $slug = $entry->getBasename('.html');
            try {
                $page = $this->read($slug);
                if (is_array($page)) {
                    $pages[] = $page;
                }
            } catch (Throwable $error) {
                error_log('[FoxesCraft badge pages] Skipped invalid page ' . $entry->getFilename()
                    . ': ' . $error::class . ': ' . $error->getMessage());
            }
        }
        usort($pages, static fn (array $left, array $right): int => strcmp($left['slug'], $right['slug']));
        return ['pages' => $pages];
    }

    public function exists(string $slug): bool
    {
        $path = $this->path($this->slug($slug));
        return is_file($path) && !is_link($path);
    }

    /** @return array{badgeName: string, slug: string, html: string}|null */
    public function read(string $slug): ?array
    {
        $slug = $this->slug($slug);
        $path = $this->path($slug);
        if (!is_file($path)) {
            return null;
        }
        if (is_link($path)) {
            throw new RuntimeException('HTML-страница бейджа не может быть символической ссылкой.');
        }
        $html = file_get_contents($path);
        if (!is_string($html)) {
            throw new RuntimeException('Не удалось прочитать HTML-страницу бейджа.');
        }
        $sanitized = $this->sanitize($html, null, $slug);
        return [
            'badgeName' => $sanitized['badgeName'],
            'slug' => $slug,
            'html' => $sanitized['html'],
        ];
    }

    /** @return array{badgeName: string, slug: string, html: string} */
    public function save(array $payload, string $badgeName, string $slug): array
    {
        $badgeName = trim($badgeName);
        $slug = $this->slug($slug);
        $html = (string)($payload['html'] ?? '');
        if ($badgeName === '') {
            throw new InvalidArgumentException('Название бейджа из БД не указано.');
        }
        $sanitized = $this->sanitize($html, $badgeName, $slug);
        $this->assertWritableDirectory();
        $this->write($this->path($slug), $sanitized['html']);
        return [
            'badgeName' => $badgeName,
            'slug' => $slug,
            'html' => $sanitized['html'],
        ];
    }

    public function move(string $sourceSlug, string $targetSlug, string $badgeName): void
    {
        $sourceSlug = $this->slug($sourceSlug);
        $targetSlug = $this->slug($targetSlug);
        if ($sourceSlug === $targetSlug || !$this->exists($sourceSlug)) {
            return;
        }
        if ($this->exists($targetSlug)) {
            throw new InvalidArgumentException('HTML-страница для нового URL уже существует: ' . $targetSlug);
        }
        $source = $this->read($sourceSlug);
        if (!is_array($source)) {
            return;
        }
        $this->save(['html' => (string)$source['html']], $badgeName, $targetSlug);
        try {
            $this->delete($sourceSlug);
        } catch (Throwable $error) {
            @unlink($this->path($targetSlug));
            throw $error;
        }
    }

    public function delete(string $slug): void
    {
        $path = $this->path($this->slug($slug));
        if (!is_file($path)) {
            return;
        }
        if (is_link($path) || !unlink($path)) {
            throw new RuntimeException('Не удалось удалить HTML-страницу бейджа.');
        }
    }

    public function render(array $page, array $badge): string
    {
        $slug = $this->slug((string)($page['slug'] ?? ''));
        $badgeName = trim((string)($badge['badgeName'] ?? ''));
        if ($badgeName === '') {
            throw new RuntimeException('Запись бейджа из БД не содержит badgeName.');
        }
        $sanitized = $this->sanitize((string)($page['html'] ?? ''), $badgeName, $slug);
        if (!class_exists(DOMDocument::class)) {
            return $this->renderWithoutDom($sanitized['html'], $badgeName, trim((string)($badge['description'] ?? '')), trim((string)($badge['img'] ?? '')), $slug);
        }
        [$document, $wrapper, $article] = $this->document($sanitized['html']);
        $xpath = new DOMXPath($document);

        $this->setText($xpath, $wrapper, 'data-badge-title', $badgeName);
        $this->setText($xpath, $wrapper, 'data-badge-description', trim((string)($badge['description'] ?? '')));

        $image = trim((string)($badge['img'] ?? ''));
        foreach ($this->elements($xpath, './/*[@data-badge-image]', $wrapper) as $element) {
            if (!$element instanceof DOMElement || strtolower($element->tagName) !== 'img') {
                continue;
            }
            if ($image === '') {
                $element->removeAttribute('src');
                $element->setAttribute('hidden', 'hidden');
            } else {
                $this->validateUrl($image, true);
                $element->setAttribute('src', $image);
                $element->setAttribute('alt', $badgeName);
                $element->removeAttribute('hidden');
            }
        }

        $article->setAttribute('data-badge-name', $badgeName);
        $article->setAttribute('data-badge-slug', $slug);
        return $this->fragment($document, $wrapper);
    }

    /** @return array{html: string, badgeName: string} */
    private function sanitize(string $html, ?string $badgeName, string $slug): array
    {
        if ($html === '' || strlen($html) > self::MAXIMUM_HTML_BYTES) {
            throw new InvalidArgumentException('HTML-страница бейджа пуста или превышает 256 КиБ.');
        }
        if (preg_match('/<(?:script|style|iframe|object|embed|form|input|button|textarea|select|option|link|meta|base|svg|math)\b/i', $html) === 1
            || preg_match('/\son[a-z]+\s*=/i', $html) === 1
            || preg_match('/(?:javascript|vbscript|data\s*:\s*text\/html)\s*:/i', $html) === 1) {
            throw new InvalidArgumentException('HTML содержит запрещённые исполняемые элементы или атрибуты.');
        }

        if (!class_exists(DOMDocument::class)) {
            return $this->sanitizeWithoutDom($html, $badgeName, $slug);
        }

        [$document, $wrapper, $article] = $this->document($html);
        $xpath = new DOMXPath($document);
        foreach ($this->elements($xpath, './/*', $wrapper) as $element) {
            if (!$element instanceof DOMElement) {
                continue;
            }
            $tag = strtolower($element->tagName);
            if (!in_array($tag, self::ALLOWED_TAGS, true)) {
                throw new InvalidArgumentException('HTML содержит запрещённый элемент <' . $tag . '>.');
            }
            $attributes = [];
            foreach ($element->attributes as $attribute) {
                $attributes[] = [$attribute->name, $attribute->value];
            }
            foreach ($attributes as [$name, $value]) {
                $this->validateAttribute($tag, strtolower($name), $value);
            }
        }

        if (!$article->hasAttribute('data-badge-page')) {
            throw new InvalidArgumentException('Корневой <article> должен содержать data-badge-page.');
        }
        foreach (['data-badge-title', 'data-badge-description', 'data-badge-image', 'data-badge-history'] as $required) {
            $nodes = $this->elements($xpath, './/*[@' . $required . ']', $wrapper);
            if (count($nodes) !== 1) {
                throw new InvalidArgumentException('HTML должен содержать ровно один элемент [' . $required . '].');
            }
        }
        $imageNode = $this->elements($xpath, './/*[@data-badge-image]', $wrapper)[0] ?? null;
        if (!$imageNode instanceof DOMElement || strtolower($imageNode->tagName) !== 'img') {
            throw new InvalidArgumentException('Элемент [data-badge-image] должен быть тегом <img>.');
        }

        $resolvedName = $badgeName ?? trim($article->getAttribute('data-badge-name'));
        if ($resolvedName === '' || mb_strlen($resolvedName) > self::MAXIMUM_BADGE_NAME_LENGTH) {
            throw new InvalidArgumentException('HTML-страница не содержит корректную привязку data-badge-name.');
        }
        $article->setAttribute('data-badge-name', $resolvedName);
        $article->setAttribute('data-badge-slug', $slug);
        $article->setAttribute('data-badge-page', '1');

        return ['html' => $this->fragment($document, $wrapper), 'badgeName' => $resolvedName];
    }

    /** @return array{html: string, badgeName: string} */
    private function sanitizeWithoutDom(string $html, ?string $badgeName, string $slug): array
    {
        if (preg_match('/^\s*<article\b([^>]*)>(.*)<\/article>\s*$/is', $html, $root) !== 1) {
            throw new InvalidArgumentException('HTML-страница должна содержать ровно один корневой <article>.');
        }

        preg_match_all('/<\/?\s*([A-Za-z][A-Za-z0-9-]*)\b[^>]*>/s', $html, $tagMatches);
        foreach ($tagMatches[1] ?? [] as $tagName) {
            $tag = strtolower((string)$tagName);
            if (!in_array($tag, self::ALLOWED_TAGS, true)) {
                throw new InvalidArgumentException('HTML содержит запрещённый элемент <' . $tag . '>.');
            }
        }

        foreach (['data-badge-title', 'data-badge-description', 'data-badge-image', 'data-badge-history'] as $required) {
            $count = preg_match_all('/\b' . preg_quote($required, '/') . '(?:\s*=|\s|>)/i', $html);
            if ($count !== 1) {
                throw new InvalidArgumentException('HTML должен содержать ровно один элемент [' . $required . '].');
            }
        }
        if (preg_match('/<img\b[^>]*\bdata-badge-image(?:\s*=|\s|>)[^>]*>/i', $html) !== 1) {
            throw new InvalidArgumentException('Элемент [data-badge-image] должен быть тегом <img>.');
        }

        $rootAttributes = (string)($root[1] ?? '');
        if (preg_match('/\bdata-badge-page(?:\s*=|\s|$)/i', $rootAttributes) !== 1) {
            throw new InvalidArgumentException('Корневой <article> должен содержать data-badge-page.');
        }
        $resolvedName = $badgeName;
        if ($resolvedName === null) {
            $resolvedName = preg_match('/\bdata-badge-name\s*=\s*(["\'])(.*?)\1/is', $rootAttributes, $nameMatch) === 1
                ? html_entity_decode(trim((string)$nameMatch[2]), ENT_QUOTES | ENT_HTML5, 'UTF-8')
                : '';
        }
        $resolvedName = trim((string)$resolvedName);
        if ($resolvedName === '' || mb_strlen($resolvedName) > self::MAXIMUM_BADGE_NAME_LENGTH) {
            throw new InvalidArgumentException('HTML-страница не содержит корректную привязку data-badge-name.');
        }

        $html = $this->replaceRootAttribute($html, 'data-badge-page', '1');
        $html = $this->replaceRootAttribute($html, 'data-badge-name', $resolvedName);
        $html = $this->replaceRootAttribute($html, 'data-badge-slug', $slug);
        return ['html' => trim($html) . PHP_EOL, 'badgeName' => $resolvedName];
    }

    private function renderWithoutDom(
        string $html,
        string $badgeName,
        string $description,
        string $image,
        string $slug,
    ): string {
        $title = htmlspecialchars($badgeName, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
        $descriptionHtml = htmlspecialchars($description, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
        $html = $this->replaceMarkedElementText($html, 'data-badge-title', $title);
        $html = $this->replaceMarkedElementText($html, 'data-badge-description', $descriptionHtml);

        $html = preg_replace_callback(
            '/<img\b([^>]*\bdata-badge-image(?:\s*=\s*(["\']).*?\2)?[^>]*)>/is',
            function (array $match) use ($image, $badgeName): string {
                $attributes = preg_replace('/\s+(?:src|alt|hidden)\s*=\s*(["\']).*?\1/is', '', (string)$match[1]) ?? (string)$match[1];
                if ($image === '') {
                    return '<img' . $attributes . ' hidden="hidden">';
                }
                $this->validateUrl($image, true);
                return '<img' . $attributes
                    . ' src="' . htmlspecialchars($image, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8') . '"'
                    . ' alt="' . htmlspecialchars($badgeName, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8') . '">';
            },
            $html,
            1,
        ) ?? $html;

        $html = $this->replaceRootAttribute($html, 'data-badge-page', '1');
        $html = $this->replaceRootAttribute($html, 'data-badge-name', $badgeName);
        $html = $this->replaceRootAttribute($html, 'data-badge-slug', $slug);
        return trim($html) . PHP_EOL;
    }

    private function replaceMarkedElementText(string $html, string $attribute, string $escapedText): string
    {
        $pattern = '/<([A-Za-z][A-Za-z0-9-]*)\b([^>]*\b' . preg_quote($attribute, '/') . '(?:\s*=\s*(["\']).*?\3)?[^>]*)>.*?<\/\1>/is';
        return preg_replace($pattern, '<$1$2>' . $escapedText . '</$1>', $html, 1) ?? $html;
    }

    private function replaceRootAttribute(string $html, string $attribute, string $value): string
    {
        $escaped = htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
        return preg_replace_callback(
            '/^\s*<article\b([^>]*)>/is',
            static function (array $match) use ($attribute, $escaped): string {
                $attributes = (string)($match[1] ?? '');
                $pattern = '/\s+' . preg_quote($attribute, '/') . '(?:\s*=\s*(["\']).*?\1)?/is';
                if (preg_match($pattern, $attributes) === 1) {
                    $attributes = preg_replace($pattern, ' ' . $attribute . '="' . $escaped . '"', $attributes, 1) ?? $attributes;
                } else {
                    $attributes .= ' ' . $attribute . '="' . $escaped . '"';
                }
                return '<article' . $attributes . '>';
            },
            $html,
            1,
        ) ?? $html;
    }

    /** @return array{DOMDocument, DOMElement, DOMElement} */
    private function document(string $html): array
    {
        $document = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        try {
            $loaded = $document->loadHTML(
                '<?xml encoding="UTF-8"><!doctype html><html><body><div id="fox-badge-wrapper">'
                . $html . '</div></body></html>',
                LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING,
            );
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
        if ($loaded !== true) {
            throw new InvalidArgumentException('Не удалось разобрать HTML-страницу бейджа.');
        }
        $xpath = new DOMXPath($document);
        $wrapper = $xpath->query('//*[@id="fox-badge-wrapper"]')->item(0);
        if (!$wrapper instanceof DOMElement) {
            throw new InvalidArgumentException('Не удалось выделить HTML-фрагмент страницы бейджа.');
        }
        $rootElements = [];
        foreach ($wrapper->childNodes as $child) {
            if ($child instanceof DOMText && trim($child->textContent) === '') {
                continue;
            }
            if ($child instanceof DOMElement) {
                $rootElements[] = $child;
                continue;
            }
            throw new InvalidArgumentException('HTML-страница должна содержать один корневой <article>.');
        }
        if (count($rootElements) !== 1 || strtolower($rootElements[0]->tagName) !== 'article') {
            throw new InvalidArgumentException('HTML-страница должна содержать ровно один корневой <article>.');
        }
        return [$document, $wrapper, $rootElements[0]];
    }

    private function validateAttribute(string $tag, string $name, string $value): void
    {
        if (str_starts_with($name, 'on') || $name === 'style' || $name === 'srcdoc') {
            throw new InvalidArgumentException('Запрещённый HTML-атрибут: ' . $name);
        }
        if (in_array($name, self::DATA_ATTRIBUTES, true)) {
            return;
        }
        if ($name === 'class') {
            if (strlen($value) > 512 || preg_match('/^[A-Za-z0-9 _:-]*$/D', $value) !== 1) {
                throw new InvalidArgumentException('Некорректное значение class.');
            }
            return;
        }
        if ($name === 'id') {
            if (preg_match('/^[A-Za-z][A-Za-z0-9_-]{0,79}$/D', $value) !== 1) {
                throw new InvalidArgumentException('Некорректное значение id.');
            }
            return;
        }
        if ($name === 'role' || $name === 'title' || str_starts_with($name, 'aria-')) {
            if (strlen($value) > 512) {
                throw new InvalidArgumentException('Слишком длинное значение атрибута ' . $name . '.');
            }
            return;
        }
        if ($tag === 'a' && in_array($name, ['href', 'target', 'rel'], true)) {
            if ($name === 'href') {
                $this->validateUrl($value, false);
            } elseif ($name === 'target' && !in_array($value, ['_self', '_blank'], true)) {
                throw new InvalidArgumentException('Некорректный target ссылки.');
            }
            return;
        }
        if ($tag === 'img' && in_array($name, ['src', 'alt', 'width', 'height', 'loading', 'decoding'], true)) {
            if ($name === 'src' && $value !== '') {
                $this->validateUrl($value, true);
            }
            if (in_array($name, ['width', 'height'], true)
                && preg_match('/^[1-9][0-9]{0,3}$/D', $value) !== 1) {
                throw new InvalidArgumentException('Некорректный размер изображения.');
            }
            if ($name === 'loading' && !in_array($value, ['lazy', 'eager'], true)) {
                throw new InvalidArgumentException('Некорректный loading изображения.');
            }
            if ($name === 'decoding' && !in_array($value, ['async', 'sync', 'auto'], true)) {
                throw new InvalidArgumentException('Некорректный decoding изображения.');
            }
            return;
        }
        if ($tag === 'time' && $name === 'datetime') {
            return;
        }
        throw new InvalidArgumentException('Атрибут ' . $name . ' запрещён для <' . $tag . '>.');
    }

    private function validateUrl(string $value, bool $image): void
    {
        $value = trim(html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if ($value === '' && $image) {
            return;
        }
        if (str_contains($value, "\0") || str_contains($value, '\\')
            || preg_match('/^(?:javascript|vbscript|data):/i', $value) === 1) {
            throw new InvalidArgumentException('HTML содержит небезопасный URL.');
        }
        if (str_starts_with($value, '#') || str_starts_with($value, '/')) {
            return;
        }
        if (!$image && preg_match('#^(?:https://|mailto:)#i', $value) === 1) {
            return;
        }
        if ($image && preg_match('#^[A-Za-z0-9._/-]+$#D', $value) === 1 && !str_contains($value, '..')) {
            return;
        }
        throw new InvalidArgumentException('HTML содержит URL, не разрешённый политикой страницы бейджа.');
    }

    /** @return list<DOMElement> */
    private function elements(DOMXPath $xpath, string $query, DOMElement $context): array
    {
        $result = [];
        $nodes = $xpath->query($query, $context);
        if ($nodes === false) {
            return [];
        }
        foreach ($nodes as $node) {
            if ($node instanceof DOMElement) {
                $result[] = $node;
            }
        }
        return $result;
    }

    private function setText(DOMXPath $xpath, DOMElement $wrapper, string $attribute, string $value): void
    {
        foreach ($this->elements($xpath, './/*[@' . $attribute . ']', $wrapper) as $element) {
            $element->textContent = $value;
        }
    }

    private function fragment(DOMDocument $document, DOMElement $wrapper): string
    {
        $html = '';
        foreach ($wrapper->childNodes as $child) {
            $html .= $document->saveHTML($child);
        }
        return trim($html) . PHP_EOL;
    }

    private function slug(string $slug): string
    {
        $slug = trim($slug);
        if (preg_match('/^[a-z0-9][a-z0-9-]{0,79}$/D', $slug) !== 1) {
            throw new InvalidArgumentException('Некорректный URL slug HTML-страницы бейджа: ' . $slug);
        }
        return $slug;
    }

    private function path(string $slug): string
    {
        return $this->pagesDirectory . DIRECTORY_SEPARATOR . $this->slug($slug) . '.html';
    }

    private function key(string $value): string
    {
        $value = mb_strtolower(trim($value), 'UTF-8');
        return preg_replace('/[^\p{L}\p{N}]+/u', '', $value) ?? '';
    }

    private function assertWritableDirectory(): void
    {
        if (!is_dir($this->pagesDirectory)
            && !mkdir($this->pagesDirectory, 0755, true)
            && !is_dir($this->pagesDirectory)) {
            throw new RuntimeException(
                'Не удалось создать каталог страниц бейджей: ' . $this->pagesDirectory
            );
        }
        clearstatcache(true, $this->pagesDirectory);
        if (is_link($this->pagesDirectory)) {
            throw new RuntimeException('Каталог страниц бейджей не может быть символической ссылкой.');
        }
        if (!is_writable($this->pagesDirectory)) {
            throw new RuntimeException(
                'Каталог страниц бейджей недоступен для записи: ' . $this->pagesDirectory
            );
        }
    }

    private function write(string $path, string $html): void
    {
        $directory = dirname($path);
        $temporary = $directory . DIRECTORY_SEPARATOR . '.badge-page-' . bin2hex(random_bytes(12)) . '.tmp';
        $backup = null;
        try {
            $written = @file_put_contents($temporary, $html, LOCK_EX);
            if ($written !== strlen($html)) {
                throw new RuntimeException(
                    'Не удалось записать временный файл страницы бейджа в каталог: ' . $directory
                );
            }
            @chmod($temporary, 0640);
            if (is_file($path)) {
                $backup = $directory . DIRECTORY_SEPARATOR . '.badge-page-backup-' . bin2hex(random_bytes(12)) . '.tmp';
                if (!rename($path, $backup)) {
                    throw new RuntimeException('Не удалось подготовить замену HTML-страницы бейджа.');
                }
            }
            if (!rename($temporary, $path)) {
                if (is_string($backup) && is_file($backup)) {
                    @rename($backup, $path);
                }
                throw new RuntimeException('Не удалось атомарно сохранить HTML-страницу бейджа.');
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
