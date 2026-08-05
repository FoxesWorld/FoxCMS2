<?php

declare(strict_types=1);

namespace FoxCMS\Api\News;

final class NewsPresenter
{
    public function __construct(
        private readonly \NewsContentSanitizer $sanitizer,
        private readonly ImageDataUrlEncoder $imageEncoder,
    ) {
    }

    /** @param array<string, mixed> $post */
    public function post(array $post, bool $includeImages): array
    {
        $coverImage = $this->nullableText($post['coverImage'] ?? null);
        $result = [
            'id' => (int)($post['id'] ?? 0),
            'title' => (string)($post['title'] ?? ''),
            'summary' => (string)($post['summary'] ?? ''),
            'coverImage' => $coverImage,
            'publishedAt' => $this->nullableText($post['publishedAt'] ?? null),
            'createdAt' => (string)($post['createdAt'] ?? ''),
            'updatedAt' => (string)($post['updatedAt'] ?? ''),
            'authorLogin' => (string)($post['authorLogin'] ?? ''),
            'authorName' => (string)($post['authorName'] ?? ''),
            'authorPhoto' => $this->nullableText($post['authorPhoto'] ?? null),
            'authorGroup' => $this->nullableText($post['authorGroup'] ?? null),
            'authorColor' => $this->nullableText($post['authorColor'] ?? null),
            'likesCount' => max(0, (int)($post['likesCount'] ?? 0)),
            'commentsCount' => max(0, (int)($post['commentsCount'] ?? 0)),
            'viewsCount' => max(0, (int)($post['viewsCount'] ?? 0)),
        ];
        if ($includeImages) {
            $result['coverImageDataUrl'] = $this->imageEncoder->encode($coverImage);
        }
        if (array_key_exists('content', $post)) {
            $result['content'] = $this->sanitizer->sanitize((string)$post['content']);
        }
        return $result;
    }

    /** @param array<string, mixed> $comment */
    public function comment(array $comment): array
    {
        return [
            'id' => (int)($comment['id'] ?? 0),
            'content' => (string)($comment['content'] ?? ''),
            'createdAt' => (string)($comment['createdAt'] ?? ''),
            'updatedAt' => (string)($comment['updatedAt'] ?? ''),
            'authorLogin' => (string)($comment['authorLogin'] ?? ''),
            'authorName' => (string)($comment['authorName'] ?? ''),
            'authorPhoto' => $this->nullableText($comment['authorPhoto'] ?? null),
            'authorGroup' => $this->nullableText($comment['authorGroup'] ?? null),
            'authorColor' => $this->nullableText($comment['authorColor'] ?? null),
        ];
    }

    private function nullableText(mixed $value): ?string
    {
        $text = trim((string)$value);
        return $text === '' ? null : $text;
    }
}
