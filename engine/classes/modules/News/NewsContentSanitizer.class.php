<?php

declare(strict_types=1);

final class NewsContentSanitizer
{
    private const ALLOWED_TAGS = [
        'p', 'br', 'strong', 'b', 'em', 'i', 'u', 's', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
        'ul', 'ol', 'li', 'blockquote', 'a', 'hr', 'pre', 'code', 'table', 'thead', 'tbody',
        'tfoot', 'tr', 'th', 'td', 'div', 'span', 'sub', 'sup', 'mark', 'figure', 'figcaption', 'img',
    ];

    private const DANGEROUS_TAGS = [
        'script', 'style', 'iframe', 'object', 'embed', 'form', 'input', 'button', 'textarea',
        'select', 'option', 'link', 'meta', 'base', 'svg', 'math', 'video', 'audio', 'source',
    ];

    public function sanitize(string $html): string
    {
        $html = trim($html);
        if ($html === '') {
            return '';
        }
        if (!str_contains($html, '<')) {
            return $this->plainText($html);
        }
        if (!class_exists(DOMDocument::class)) {
            return $this->plainText(html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        }

        $document = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        try {
            $loaded = $document->loadHTML(
                '<?xml encoding="UTF-8"><!doctype html><html><body><div id="fox-news-content">'
                    . $html . '</div></body></html>',
                LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING,
            );
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
        if ($loaded !== true) {
            return $this->plainText(html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        }

        $xpath = new DOMXPath($document);
        $wrapper = $xpath->query('//*[@id="fox-news-content"]')->item(0);
        if (!$wrapper instanceof DOMElement) {
            return $this->plainText(html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        }

        $nodes = [];
        foreach ($xpath->query('.//*', $wrapper) ?: [] as $node) {
            if ($node instanceof DOMElement) {
                $nodes[] = $node;
            }
        }
        foreach ($nodes as $node) {
            $this->sanitizeElement($node);
        }

        $comments = [];
        foreach ($xpath->query('.//comment()', $wrapper) ?: [] as $comment) {
            $comments[] = $comment;
        }
        foreach ($comments as $comment) {
            $comment->parentNode?->removeChild($comment);
        }

        $result = '';
        foreach ($wrapper->childNodes as $child) {
            $result .= $document->saveHTML($child);
        }
        return trim($result);
    }

    public function text(string $html): string
    {
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\x{00A0}/u', ' ', $text) ?? $text;
        return trim($text);
    }

    private function sanitizeElement(DOMElement $node): void
    {
        if (!$node->parentNode) {
            return;
        }
        $tag = strtolower($node->tagName);
        if (!in_array($tag, self::ALLOWED_TAGS, true)) {
            if (in_array($tag, self::DANGEROUS_TAGS, true)) {
                $node->parentNode->removeChild($node);
            } else {
                while ($node->firstChild) {
                    $node->parentNode->insertBefore($node->firstChild, $node);
                }
                $node->parentNode->removeChild($node);
            }
            return;
        }

        $attributes = [];
        foreach ($node->attributes as $attribute) {
            $attributes[] = [strtolower($attribute->name), $attribute->value];
        }
        foreach ($attributes as [$name, $value]) {
            $sanitized = $this->sanitizeAttribute($tag, $name, $value);
            if ($sanitized === null || $sanitized === '') {
                $node->removeAttribute($name);
            } else {
                $node->setAttribute($name, $sanitized);
            }
        }

        if ($tag === 'a' && $node->getAttribute('target') === '_blank') {
            $node->setAttribute('rel', 'noopener noreferrer');
        }
        if ($tag === 'img') {
            if (!$node->hasAttribute('loading')) {
                $node->setAttribute('loading', 'lazy');
            }
            if (!$node->hasAttribute('decoding')) {
                $node->setAttribute('decoding', 'async');
            }
        }
    }

    private function sanitizeAttribute(string $tag, string $name, string $value): ?string
    {
        $value = trim($value);
        if ($name === 'class') {
            return strlen($value) <= 512 && preg_match('/^[A-Za-z0-9 _:-]*$/D', $value) === 1
                ? $value
                : null;
        }
        if ($name === 'style') {
            return $this->sanitizeStyle($value);
        }
        if ($name === 'title') {
            return mb_strlen($value) <= 300 ? $value : mb_substr($value, 0, 300);
        }
        if ($tag === 'a' && $name === 'href') {
            return $this->sanitizeUrl($value, false);
        }
        if ($tag === 'a' && $name === 'target') {
            return in_array($value, ['_self', '_blank'], true) ? $value : null;
        }
        if ($tag === 'a' && $name === 'rel') {
            return preg_match('/^[A-Za-z ]{1,120}$/D', $value) === 1 ? $value : null;
        }
        if ($tag === 'img' && $name === 'src') {
            return $this->sanitizeUrl($value, true);
        }
        if ($tag === 'img' && $name === 'alt') {
            return mb_strlen($value) <= 500 ? $value : mb_substr($value, 0, 500);
        }
        if ($tag === 'img' && in_array($name, ['width', 'height'], true)) {
            return preg_match('/^[1-9][0-9]{0,4}$/D', $value) === 1 ? $value : null;
        }
        if ($tag === 'img' && $name === 'loading') {
            return in_array($value, ['lazy', 'eager'], true) ? $value : null;
        }
        if ($tag === 'img' && $name === 'decoding') {
            return in_array($value, ['async', 'sync', 'auto'], true) ? $value : null;
        }
        if (in_array($tag, ['th', 'td'], true) && in_array($name, ['colspan', 'rowspan'], true)) {
            return preg_match('/^[1-9][0-9]{0,2}$/D', $value) === 1 ? $value : null;
        }
        return null;
    }

    private function sanitizeStyle(string $style): ?string
    {
        $allowed = [];
        foreach (explode(';', $style) as $declaration) {
            if (!str_contains($declaration, ':')) {
                continue;
            }
            [$property, $value] = array_map('trim', explode(':', $declaration, 2));
            $property = strtolower($property);
            $value = strtolower($value);
            if ($property === 'text-align' && in_array($value, ['left', 'right', 'center', 'justify'], true)) {
                $allowed[] = 'text-align: ' . $value;
            }
        }
        return $allowed === [] ? null : implode('; ', $allowed) . ';';
    }

    private function sanitizeUrl(string $value, bool $image): ?string
    {
        $value = trim(html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if ($value === '' || str_contains($value, "\0") || str_contains($value, '\\')) {
            return null;
        }
        if (preg_match('/^(?:javascript|vbscript|data|file):/i', $value) === 1) {
            return null;
        }
        if (str_starts_with($value, '/') || (!$image && str_starts_with($value, '#'))) {
            return $value;
        }
        if (preg_match('#^https?://#i', $value) === 1) {
            return filter_var($value, FILTER_VALIDATE_URL) !== false ? $value : null;
        }
        if (!$image && preg_match('/^mailto:[^\s@]+@[^\s@]+$/i', $value) === 1) {
            return $value;
        }
        if (!$image && preg_match('#^[A-Za-z0-9._~/?#=&%+-]+$#D', $value) === 1) {
            return $value;
        }
        return null;
    }

    private function plainText(string $text): string
    {
        $text = trim($text);
        if ($text === '') {
            return '';
        }
        $escaped = htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
        return '<p>' . preg_replace('/\R/u', '<br>', $escaped) . '</p>';
    }
}
