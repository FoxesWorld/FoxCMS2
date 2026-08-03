<?php

declare(strict_types=1);

require_once __DIR__ . '/NewsContentSanitizer.class.php';
require_once __DIR__ . '/NewsRepository.class.php';
require_once __DIR__ . '/NewsSchemaManager.class.php';

final class NewsModule extends Module
{
    private const ACTION_HANDLERS = [
        'list' => 'listPosts',
        'detail' => 'detail',
        'toggleLike' => 'toggleLike',
        'addComment' => 'addComment',
        'deleteComment' => 'deleteComment',
        'save' => 'savePost',
        'delete' => 'deletePost',
        'uploadCover' => 'uploadCover',
    ];

    private UploadService $uploads;
    private NewsRepository $repository;
    private NewsContentSanitizer $contentSanitizer;
    private NewsSchemaManager $schema;

    public function __construct(
        db $db,
        private Logger $logger,
        private HttpRequest $request,
        private UserSession $session,
        array $config = [],
    ) {
        unset($config);
        $this->uploads = new UploadService($db, $session, $logger, $request);
        $this->repository = new NewsRepository($db);
        $this->contentSanitizer = new NewsContentSanitizer();
        $this->schema = new NewsSchemaManager($db);

        $action = $this->request->string('newsAction');
        if ($action === '') {
            return;
        }

        $handler = self::ACTION_HANDLERS[$action] ?? null;
        RequestTelemetry::identify('news.' . $action, [
            'component' => 'news',
            'action' => $action,
            'handler' => is_string($handler) ? $handler : 'unresolved',
            'moduleName' => 'News',
        ]);

        try {
            $this->schema->ensure();
            if (!is_string($handler) || !method_exists($this, $handler)) {
                throw new HttpException('Неизвестная операция новостей.', 400);
            }
            $this->{$handler}();
        } catch (UploadException $error) {
            RequestTelemetry::rejectHttp(
                'news.operation.rejected',
                $error->httpStatus(),
                $error->getMessage(),
                ['action' => $action],
            );
            JsonResponse::error($error->getMessage(), $error->httpStatus());
        } catch (HttpException $error) {
            RequestTelemetry::rejectHttp(
                'news.operation.rejected',
                $error->status(),
                $error->getMessage(),
                ['action' => $action],
            );
            JsonResponse::error($error->getMessage(), $error->status(), $error->headers());
        } catch (DomainException | InvalidArgumentException $error) {
            RequestTelemetry::rejectHttp(
                'news.operation.rejected',
                400,
                $error->getMessage(),
                ['action' => $action],
            );
            JsonResponse::error($error->getMessage(), 400);
        } catch (Throwable $error) {
            RequestTelemetry::failure(
                'news.operation.failed',
                $error,
                'News operation failed unexpectedly.',
                ['action' => $action],
            );
            $requestId = RequestTelemetry::requestId();
            if ($requestId === '') {
                $requestId = ExceptionContext::requestId('news');
            }
            JsonResponse::error(
                'Операция с новостями завершилась ошибкой. Код события: ' . $requestId . '.',
                500,
            );
        }
    }

    private function listPosts(): never
    {
        $items = $this->repository->listPosts(
            $this->request->integer('limit', 6),
            $this->viewerUuid(),
            $this->session->isAdmin(),
        );
        JsonResponse::send([
            'items' => array_map([$this, 'normalizePost'], $items),
            'canCreate' => $this->session->isAdmin(),
        ]);
    }

    private function detail(): never
    {
        $postId = $this->positiveId('id');
        $post = $this->repository->findPost(
            $postId,
            $this->viewerUuid(),
            $this->session->isAdmin(),
            $this->request->integer('trackView', 0) === 1,
        );
        if (!is_array($post)) {
            throw new HttpException('Новость не найдена.', 404);
        }

        $comments = $this->repository->comments($postId);
        foreach ($comments as &$comment) {
            $comment['id'] = (int)$comment['id'];
            $comment['canDelete'] = $this->session->isLogged()
                && ($this->session->isAdmin()
                    || Uuid::equals($this->session->uuid(), (string)$comment['authorUuid']));
        }
        unset($comment);

        JsonResponse::send([
            'post' => $this->normalizePost($post),
            'comments' => $comments,
            'canComment' => $this->session->isLogged() && (bool)$post['isPublished'],
        ]);
    }

    private function toggleLike(): never
    {
        $this->requireMutation();
        $postId = $this->publishedPostId();
        $result = $this->repository->toggleLike($postId, $this->session->uuid());
        RequestTelemetry::event(
            'news.like.toggled',
            'News like state changed.',
            [
                'postId' => $postId,
                'liked' => $result['liked'],
                'likesCount' => $result['likesCount'],
            ],
            'INFO',
            'success',
        );
        JsonResponse::send($result);
    }

    private function addComment(): never
    {
        $this->requireMutation();
        $postId = $this->publishedPostId();
        $content = trim($this->request->string('content'));
        $length = mb_strlen($content);
        if ($length < 1 || $length > 2000) {
            throw new InvalidArgumentException('Комментарий должен содержать от 1 до 2000 символов.');
        }
        if ($this->repository->hasRecentComment($this->session->uuid())) {
            RequestTelemetry::rejectHttp(
                'news.comment.rejected',
                429,
                'Comment creation rate limit exceeded.',
                ['postId' => $postId],
            );
            JsonResponse::send([
                'message' => 'Перед следующим комментарием подождите несколько секунд.',
                'type' => 'warning',
            ], 429);
        }

        $commentId = $this->repository->addComment($postId, $this->session->uuid(), $content);
        RequestTelemetry::event(
            'news.comment.created',
            'News comment created.',
            ['postId' => $postId, 'commentId' => $commentId],
            'INFO',
            'success',
        );
        JsonResponse::send([
            'message' => 'Комментарий опубликован.',
            'type' => 'success',
        ], 201);
    }

    private function deleteComment(): never
    {
        $this->requireMutation();
        $commentId = $this->positiveId('commentId');
        $authorUuid = $this->repository->commentAuthor($commentId);
        if (!is_string($authorUuid)) {
            throw new HttpException('Комментарий не найден.', 404);
        }
        if (!$this->session->isAdmin() && !Uuid::equals($this->session->uuid(), $authorUuid)) {
            throw new HttpException('Недостаточно прав для удаления комментария.', 403);
        }
        if (!$this->repository->deleteComment($commentId)) {
            throw new HttpException('Комментарий не найден.', 404);
        }
        RequestTelemetry::event(
            'news.comment.deleted',
            'News comment deleted.',
            ['commentId' => $commentId],
            'INFO',
            'success',
        );
        JsonResponse::send(['message' => 'Комментарий удалён.', 'type' => 'success']);
    }

    private function savePost(): never
    {
        $this->requireAdminMutation();
        $entry = $this->decodeEntry();
        $post = $this->validatePostEntry($entry);
        $postId = $this->repository->savePost(
            max(0, $this->request->integer('id', 0)),
            $this->session->uuid(),
            $post,
        );

        $this->logger->event('news.post.saved', 'News publication saved.', [
            'component' => 'news',
            'operation' => 'save_post',
            'postId' => $postId,
            'published' => $post['isPublished'],
        ], 'INFO', 'success');
        JsonResponse::send([
            'message' => $post['isPublished']
                ? 'Публикация сохранена и опубликована.'
                : 'Черновик сохранён.',
            'type' => 'success',
            'id' => $postId,
        ]);
    }

    private function uploadCover(): never
    {
        $result = $this->uploads->store(
            UploadPurpose::NEWS_COVER,
            $this->request->file('cover'),
        );
        JsonResponse::send([
            'message' => 'Обложка загружена.',
            'type' => 'success',
            'coverImage' => $result->publicPath(),
            'upload' => $result,
        ], 201);
    }

    private function deletePost(): never
    {
        $this->requireAdminMutation();
        $postId = $this->positiveId('id');
        if (!$this->repository->deletePost($postId)) {
            throw new HttpException('Публикация не найдена.', 404);
        }
        $this->logger->event('news.post.deleted', 'News publication deleted.', [
            'component' => 'news',
            'operation' => 'delete_post',
            'postId' => $postId,
        ], 'INFO', 'success');
        JsonResponse::send(['message' => 'Публикация удалена.', 'type' => 'success']);
    }

    private function requireMutation(): void
    {
        if (!$this->session->isLogged()) {
            throw new HttpException('Для этого действия нужно войти в аккаунт.', 401);
        }
        if (!CsrfToken::validate($this->request->csrfToken())) {
            throw new HttpException('Защитный токен устарел. Обновите страницу.', 403);
        }
    }

    private function requireAdminMutation(): void
    {
        $this->requireMutation();
        if (!$this->session->isAdmin()) {
            throw new HttpException('Редактировать публикации может только администратор.', 403);
        }
    }

    private function publishedPostId(): int
    {
        $postId = $this->positiveId('id');
        if (!$this->repository->isPublished($postId)) {
            throw new HttpException('Новость не найдена.', 404);
        }
        return $postId;
    }

    private function positiveId(string $field): int
    {
        $value = $this->request->integer($field, 0);
        if ($value < 1) {
            throw new InvalidArgumentException('Некорректный идентификатор.');
        }
        return $value;
    }

    /** @return array<string, mixed> */
    private function decodeEntry(): array
    {
        try {
            $decoded = json_decode($this->request->string('entry'), true, 8, JSON_THROW_ON_ERROR);
        } catch (JsonException $error) {
            throw new InvalidArgumentException('Некорректные данные публикации.', 0, $error);
        }
        if (!is_array($decoded) || array_is_list($decoded)) {
            throw new InvalidArgumentException('Публикация должна быть объектом.');
        }
        return $decoded;
    }

    /**
     * @param array<string, mixed> $entry
     * @return array{title:string,summary:string,content:string,coverImage:string,isPublished:bool}
     */
    private function validatePostEntry(array $entry): array
    {
        $title = trim((string)($entry['title'] ?? ''));
        $summary = trim((string)($entry['summary'] ?? ''));
        $content = $this->contentSanitizer->sanitize((string)($entry['content'] ?? ''));
        $coverImage = $this->normalizeCoverImage((string)($entry['coverImage'] ?? ''));
        $isPublished = filter_var($entry['isPublished'] ?? false, FILTER_VALIDATE_BOOLEAN);

        if (mb_strlen($title) < 1 || mb_strlen($title) > 160) {
            throw new InvalidArgumentException('Заголовок должен содержать от 1 до 160 символов.');
        }
        if (mb_strlen($summary) < 1 || mb_strlen($summary) > 600) {
            throw new InvalidArgumentException('Короткий текст должен содержать от 1 до 600 символов.');
        }
        if (mb_strlen($this->contentSanitizer->text($content)) < 1 || mb_strlen($content) > 100000) {
            throw new InvalidArgumentException('Полный текст должен содержать от 1 до 100000 символов.');
        }

        return [
            'title' => $title,
            'summary' => $summary,
            'content' => $content,
            'coverImage' => $coverImage,
            'isPublished' => $isPublished,
        ];
    }

    private function normalizeCoverImage(string $value): string
    {
        $value = trim($value);
        return $value === ''
            ? ''
            : $this->uploads->validateReference(UploadPurpose::NEWS_COVER, $value);
    }

    /** @param array<string, mixed> $post */
    private function normalizePost(array $post): array
    {
        $post['id'] = (int)$post['id'];
        $post['likesCount'] = (int)($post['likesCount'] ?? 0);
        $post['commentsCount'] = (int)($post['commentsCount'] ?? 0);
        $post['viewsCount'] = (int)($post['viewsCount'] ?? 0);
        $post['coverImage'] = (string)($post['coverImage'] ?? '');
        if (array_key_exists('content', $post)) {
            $post['content'] = $this->contentSanitizer->sanitize((string)$post['content']);
        }
        $post['likedByViewer'] = (bool)($post['likedByViewer'] ?? false);
        $post['isPublished'] = (bool)($post['isPublished'] ?? false);
        $post['canEdit'] = $this->session->isAdmin();
        return $post;
    }

    private function viewerUuid(): string
    {
        return $this->session->isLogged() ? $this->session->uuid() : '';
    }
}
