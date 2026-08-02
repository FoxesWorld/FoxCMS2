<?php

declare(strict_types=1);

final class NewsModule extends Module
{
    private const CONTENT_TAGS = [
        'p', 'br', 'strong', 'b', 'em', 'i', 'u', 's', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
        'ul', 'ol', 'li', 'blockquote', 'a', 'hr', 'pre', 'code', 'table', 'thead', 'tbody',
        'tfoot', 'tr', 'th', 'td', 'div', 'span', 'sub', 'sup', 'mark', 'figure', 'figcaption', 'img',
    ];

    private const CONTENT_DANGEROUS_TAGS = [
        'script', 'style', 'iframe', 'object', 'embed', 'form', 'input', 'button', 'textarea',
        'select', 'option', 'link', 'meta', 'base', 'svg', 'math', 'video', 'audio', 'source',
    ];

    private UploadService $uploads;

    public function __construct(
        private db $db,
        private Logger $logger,
        private HttpRequest $request,
        private UserSession $session,
        private array $config = [],
    ) {
        $this->uploads = new UploadService($db, $session, $logger, $request);
        $action = $this->request->string('newsAction');
        if ($action === '') {
            return;
        }

        try {
            $this->ensureSchema();
            match ($action) {
                'list' => $this->listPosts(),
                'detail' => $this->detail(),
                'toggleLike' => $this->toggleLike(),
                'addComment' => $this->addComment(),
                'deleteComment' => $this->deleteComment(),
                'save' => $this->savePost(),
                'delete' => $this->deletePost(),
                'uploadCover' => $this->uploadCover(),
                default => $this->respond(['message' => 'Неизвестная операция новостей.', 'type' => 'error'], 400),
            };
        } catch (UploadException $error) {
            $this->respond(['message' => $error->getMessage(), 'type' => 'error'], $error->httpStatus());
        } catch (DomainException | InvalidArgumentException $error) {
            $this->respond(['message' => $error->getMessage(), 'type' => 'error'], 400);
        } catch (Throwable $error) {
            $this->logger->logError('News operation failed.', [
                'action' => $action,
                'exception' => $error::class,
                'message' => $error->getMessage(),
            ]);
            $this->respond(['message' => 'Операция с новостями завершилась ошибкой.', 'type' => 'error'], 500);
        }
    }

    private function ensureSchema(): void
    {
        $tableStatement = $this->db->query(
            "SELECT COUNT(*) FROM information_schema.TABLES "
            . "WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ('news_posts', 'news_likes', 'news_comments')"
        );
        $tableCount = $tableStatement instanceof PDOStatement ? (int)$tableStatement->fetchColumn() : 0;

        if ($tableCount < 3) {
            $this->db->exec(
                "CREATE TABLE IF NOT EXISTS `news_posts` ("
                . "`id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,"
                . "`authorUuid` CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,"
                . "`title` VARCHAR(160) NOT NULL,"
                . "`summary` VARCHAR(600) NOT NULL,"
                . "`content` MEDIUMTEXT NOT NULL,"
                . "`coverImage` VARCHAR(512) NULL,"
                . "`isPublished` TINYINT(1) NOT NULL DEFAULT 0,"
                . "`viewsCount` BIGINT UNSIGNED NOT NULL DEFAULT 0,"
                . "`publishedAt` DATETIME(4) NULL,"
                . "`createdAt` DATETIME(4) NOT NULL DEFAULT CURRENT_TIMESTAMP(4),"
                . "`updatedAt` DATETIME(4) NOT NULL DEFAULT CURRENT_TIMESTAMP(4) ON UPDATE CURRENT_TIMESTAMP(4),"
                . "PRIMARY KEY (`id`),"
                . "KEY `idx_news_posts_published` (`isPublished`, `publishedAt`, `id`),"
                . "KEY `idx_news_posts_author` (`authorUuid`),"
                . "CONSTRAINT `fk_news_posts_author` FOREIGN KEY (`authorUuid`) REFERENCES `users` (`uuid`) "
                . "ON UPDATE CASCADE ON DELETE RESTRICT"
                . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
            $this->db->exec(
                "CREATE TABLE IF NOT EXISTS `news_likes` ("
                . "`postId` BIGINT UNSIGNED NOT NULL,"
                . "`userUuid` CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,"
                . "`createdAt` DATETIME(4) NOT NULL DEFAULT CURRENT_TIMESTAMP(4),"
                . "PRIMARY KEY (`postId`, `userUuid`),"
                . "KEY `idx_news_likes_user` (`userUuid`, `createdAt`),"
                . "CONSTRAINT `fk_news_likes_post` FOREIGN KEY (`postId`) REFERENCES `news_posts` (`id`) "
                . "ON UPDATE CASCADE ON DELETE CASCADE,"
                . "CONSTRAINT `fk_news_likes_user` FOREIGN KEY (`userUuid`) REFERENCES `users` (`uuid`) "
                . "ON UPDATE CASCADE ON DELETE CASCADE"
                . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
            $this->db->exec(
                "CREATE TABLE IF NOT EXISTS `news_comments` ("
                . "`id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,"
                . "`postId` BIGINT UNSIGNED NOT NULL,"
                . "`authorUuid` CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,"
                . "`content` VARCHAR(2000) NOT NULL,"
                . "`createdAt` DATETIME(4) NOT NULL DEFAULT CURRENT_TIMESTAMP(4),"
                . "`updatedAt` DATETIME(4) NOT NULL DEFAULT CURRENT_TIMESTAMP(4) ON UPDATE CURRENT_TIMESTAMP(4),"
                . "PRIMARY KEY (`id`),"
                . "KEY `idx_news_comments_post` (`postId`, `createdAt`, `id`),"
                . "KEY `idx_news_comments_author` (`authorUuid`, `createdAt`),"
                . "CONSTRAINT `fk_news_comments_post` FOREIGN KEY (`postId`) REFERENCES `news_posts` (`id`) "
                . "ON UPDATE CASCADE ON DELETE CASCADE,"
                . "CONSTRAINT `fk_news_comments_author` FOREIGN KEY (`authorUuid`) REFERENCES `users` (`uuid`) "
                . "ON UPDATE CASCADE ON DELETE CASCADE"
                . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
        }

        $columnStatement = $this->db->query(
            "SELECT `COLUMN_NAME` FROM information_schema.COLUMNS "
            . "WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'news_posts' "
            . "AND COLUMN_NAME IN ('coverImage', 'viewsCount')"
        );
        $columns = $columnStatement instanceof PDOStatement
            ? array_map('strval', $columnStatement->fetchAll(PDO::FETCH_COLUMN))
            : [];
        if (!in_array('coverImage', $columns, true)) {
            $this->db->exec('ALTER TABLE `news_posts` ADD COLUMN `coverImage` VARCHAR(512) NULL AFTER `content`');
        }
        if (!in_array('viewsCount', $columns, true)) {
            $this->db->exec('ALTER TABLE `news_posts` ADD COLUMN `viewsCount` BIGINT UNSIGNED NOT NULL DEFAULT 0 AFTER `isPublished`');
        }
    }

    private function listPosts(): never
    {
        $limit = max(1, min(20, $this->request->integer('limit', 6)));
        $viewerUuid = $this->session->isLogged() ? $this->session->uuid() : '';
        $isAdmin = $this->session->isAdmin();
        $visibility = $isAdmin
            ? '1 = 1'
            : '`post`.`isPublished` = 1 AND `post`.`publishedAt` IS NOT NULL';
        $contentField = $isAdmin ? ', `post`.`content`' : '';
        $statement = $this->db->prepare(
            'SELECT `post`.`id`, `post`.`title`, `post`.`summary`, `post`.`coverImage`, `post`.`isPublished`' . $contentField . ', '
            . '`post`.`viewsCount`, `post`.`publishedAt`, `post`.`createdAt`, `post`.`updatedAt`, '
            . '`user`.`login` AS `authorLogin`, '
            . 'COALESCE(NULLIF(`user`.`realname`, \'\'), `user`.`login`) AS `authorName`, '
            . '`user`.`profilePhoto` AS `authorPhoto`, `group`.`groupName` AS `authorGroup`, '
            . '`group`.`groupColor` AS `authorColor`, '
            . '(SELECT COUNT(*) FROM `news_likes` AS `likeRow` WHERE `likeRow`.`postId` = `post`.`id`) AS `likesCount`, '
            . '(SELECT COUNT(*) FROM `news_comments` AS `commentRow` WHERE `commentRow`.`postId` = `post`.`id`) AS `commentsCount`, '
            . 'EXISTS(SELECT 1 FROM `news_likes` AS `viewerLike` '
            . 'WHERE `viewerLike`.`postId` = `post`.`id` AND `viewerLike`.`userUuid` = :viewerUuid) AS `likedByViewer` '
            . 'FROM `news_posts` AS `post` '
            . 'INNER JOIN `users` AS `user` ON `user`.`uuid` = `post`.`authorUuid` '
            . 'LEFT JOIN `groupAssociation` AS `group` ON `group`.`groupTag` = `user`.`groupTag` '
            . 'WHERE ' . $visibility . ' '
            . 'ORDER BY COALESCE(`post`.`publishedAt`, `post`.`createdAt`) DESC, `post`.`id` DESC LIMIT ' . $limit
        );
        $statement->execute([':viewerUuid' => $viewerUuid]);
        $items = $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $this->respond([
            'items' => array_map([$this, 'normalizePost'], $items),
            'canCreate' => $this->session->isAdmin(),
        ]);
    }

    private function detail(): never
    {
        $postId = $this->positiveId('id');
        $viewerUuid = $this->session->isLogged() ? $this->session->uuid() : '';
        $visibility = $this->session->isAdmin()
            ? ''
            : ' AND `post`.`isPublished` = 1 AND `post`.`publishedAt` IS NOT NULL';
        if ($this->request->integer('trackView', 0) === 1) {
            $view = $this->db->prepare(
                'UPDATE `news_posts` SET `viewsCount` = `viewsCount` + 1 '
                . 'WHERE `id` = :postId AND `isPublished` = 1 AND `publishedAt` IS NOT NULL'
            );
            $view->execute([':postId' => $postId]);
        }
        $statement = $this->db->prepare(
            'SELECT `post`.`id`, `post`.`title`, `post`.`summary`, `post`.`content`, `post`.`coverImage`, '
            . '`post`.`isPublished`, `post`.`viewsCount`, `post`.`publishedAt`, `post`.`createdAt`, `post`.`updatedAt`, '
            . '`user`.`login` AS `authorLogin`, '
            . 'COALESCE(NULLIF(`user`.`realname`, \'\'), `user`.`login`) AS `authorName`, '
            . '`user`.`profilePhoto` AS `authorPhoto`, `group`.`groupName` AS `authorGroup`, '
            . '`group`.`groupColor` AS `authorColor`, '
            . '(SELECT COUNT(*) FROM `news_likes` AS `likeRow` WHERE `likeRow`.`postId` = `post`.`id`) AS `likesCount`, '
            . '(SELECT COUNT(*) FROM `news_comments` AS `commentRow` WHERE `commentRow`.`postId` = `post`.`id`) AS `commentsCount`, '
            . 'EXISTS(SELECT 1 FROM `news_likes` AS `viewerLike` '
            . 'WHERE `viewerLike`.`postId` = `post`.`id` AND `viewerLike`.`userUuid` = :viewerUuid) AS `likedByViewer` '
            . 'FROM `news_posts` AS `post` '
            . 'INNER JOIN `users` AS `user` ON `user`.`uuid` = `post`.`authorUuid` '
            . 'LEFT JOIN `groupAssociation` AS `group` ON `group`.`groupTag` = `user`.`groupTag` '
            . 'WHERE `post`.`id` = :postId' . $visibility . ' LIMIT 1'
        );
        $statement->execute([':viewerUuid' => $viewerUuid, ':postId' => $postId]);
        $post = $statement->fetch(PDO::FETCH_ASSOC);
        if (!is_array($post)) {
            $this->respond(['message' => 'Новость не найдена.', 'type' => 'error'], 404);
        }

        $comments = $this->db->prepare(
            'SELECT `comment`.`id`, `comment`.`content`, `comment`.`createdAt`, `comment`.`updatedAt`, '
            . '`user`.`uuid` AS `authorUuid`, `user`.`login` AS `authorLogin`, '
            . 'COALESCE(NULLIF(`user`.`realname`, \'\'), `user`.`login`) AS `authorName`, '
            . '`user`.`profilePhoto` AS `authorPhoto`, `group`.`groupName` AS `authorGroup`, '
            . '`group`.`groupColor` AS `authorColor` '
            . 'FROM `news_comments` AS `comment` '
            . 'INNER JOIN `users` AS `user` ON `user`.`uuid` = `comment`.`authorUuid` '
            . 'LEFT JOIN `groupAssociation` AS `group` ON `group`.`groupTag` = `user`.`groupTag` '
            . 'WHERE `comment`.`postId` = :postId ORDER BY `comment`.`createdAt` ASC, `comment`.`id` ASC'
        );
        $comments->execute([':postId' => $postId]);
        $rows = $comments->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($rows as &$comment) {
            $comment['id'] = (int)$comment['id'];
            $comment['canDelete'] = $this->session->isLogged()
                && ($this->session->isAdmin() || Uuid::equals($this->session->uuid(), (string)$comment['authorUuid']));
        }
        unset($comment);

        $this->respond([
            'post' => $this->normalizePost($post),
            'comments' => $rows,
            'canComment' => $this->session->isLogged() && (bool)$post['isPublished'],
        ]);
    }

    private function toggleLike(): never
    {
        $this->requireMutation();
        $postId = $this->publishedPostId();
        $userUuid = $this->session->uuid();
        $liked = $this->db->transactional(function () use ($postId, $userUuid): bool {
            $existing = $this->db->prepare(
                'SELECT 1 FROM `news_likes` WHERE `postId` = :postId AND `userUuid` = :userUuid FOR UPDATE'
            );
            $existing->execute([':postId' => $postId, ':userUuid' => $userUuid]);
            if ($existing->fetchColumn() !== false) {
                $delete = $this->db->prepare('DELETE FROM `news_likes` WHERE `postId` = :postId AND `userUuid` = :userUuid');
                $delete->execute([':postId' => $postId, ':userUuid' => $userUuid]);
                return false;
            }
            $insert = $this->db->prepare('INSERT INTO `news_likes` (`postId`, `userUuid`) VALUES (:postId, :userUuid)');
            $insert->execute([':postId' => $postId, ':userUuid' => $userUuid]);
            return true;
        });
        $count = (int)$this->scalar('SELECT COUNT(*) FROM `news_likes` WHERE `postId` = :postId', [':postId' => $postId]);
        $this->respond(['liked' => $liked, 'likesCount' => $count]);
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
        $recent = (int)$this->scalar(
            'SELECT COUNT(*) FROM `news_comments` WHERE `authorUuid` = :authorUuid '
            . 'AND `createdAt` >= DATE_SUB(CURRENT_TIMESTAMP(4), INTERVAL 15 SECOND)',
            [':authorUuid' => $this->session->uuid()],
        );
        if ($recent > 0) {
            $this->respond(['message' => 'Перед следующим комментарием подождите несколько секунд.', 'type' => 'warning'], 429);
        }
        $statement = $this->db->prepare(
            'INSERT INTO `news_comments` (`postId`, `authorUuid`, `content`) VALUES (:postId, :authorUuid, :content)'
        );
        $statement->execute([
            ':postId' => $postId,
            ':authorUuid' => $this->session->uuid(),
            ':content' => $content,
        ]);
        $this->respond(['message' => 'Комментарий опубликован.', 'type' => 'success'], 201);
    }

    private function deleteComment(): never
    {
        $this->requireMutation();
        $commentId = $this->positiveId('commentId');
        $statement = $this->db->prepare('SELECT `authorUuid` FROM `news_comments` WHERE `id` = :commentId LIMIT 1');
        $statement->execute([':commentId' => $commentId]);
        $authorUuid = $statement->fetchColumn();
        if (!is_string($authorUuid)) {
            $this->respond(['message' => 'Комментарий не найден.', 'type' => 'error'], 404);
        }
        if (!$this->session->isAdmin() && !Uuid::equals($this->session->uuid(), $authorUuid)) {
            $this->respond(['message' => 'Недостаточно прав для удаления комментария.', 'type' => 'error'], 403);
        }
        $delete = $this->db->prepare('DELETE FROM `news_comments` WHERE `id` = :commentId');
        $delete->execute([':commentId' => $commentId]);
        $this->respond(['message' => 'Комментарий удалён.', 'type' => 'success']);
    }

    private function savePost(): never
    {
        $this->requireAdminMutation();
        $postId = max(0, $this->request->integer('id', 0));
        $entry = $this->decodeEntry();
        $title = trim((string)($entry['title'] ?? ''));
        $summary = trim((string)($entry['summary'] ?? ''));
        $content = $this->sanitizeContent((string)($entry['content'] ?? ''));
        $coverImage = $this->normalizeCoverImage((string)($entry['coverImage'] ?? ''));
        $isPublished = filter_var($entry['isPublished'] ?? false, FILTER_VALIDATE_BOOLEAN);
        if (mb_strlen($title) < 1 || mb_strlen($title) > 160) {
            throw new InvalidArgumentException('Заголовок должен содержать от 1 до 160 символов.');
        }
        if (mb_strlen($summary) < 1 || mb_strlen($summary) > 600) {
            throw new InvalidArgumentException('Короткий текст должен содержать от 1 до 600 символов.');
        }
        if (mb_strlen($this->contentText($content)) < 1 || mb_strlen($content) > 100000) {
            throw new InvalidArgumentException('Полный текст должен содержать от 1 до 100000 символов.');
        }

        if ($postId > 0) {
            $statement = $this->db->prepare(
                'UPDATE `news_posts` SET `title` = :title, `summary` = :summary, `content` = :content, `coverImage` = :coverImage, '
                . '`isPublished` = :publishedFlag, '
                . '`publishedAt` = CASE WHEN :publishedDateFlag = 1 '
                . 'THEN COALESCE(`publishedAt`, CURRENT_TIMESTAMP(4)) ELSE NULL END '
                . 'WHERE `id` = :postId'
            );
            $statement->execute([
                ':title' => $title,
                ':summary' => $summary,
                ':content' => $content,
                ':coverImage' => $coverImage === '' ? null : $coverImage,
                ':publishedFlag' => $isPublished ? 1 : 0,
                ':publishedDateFlag' => $isPublished ? 1 : 0,
                ':postId' => $postId,
            ]);
            if ($statement->rowCount() === 0 && (int)$this->scalar(
                'SELECT COUNT(*) FROM `news_posts` WHERE `id` = :postId',
                [':postId' => $postId],
            ) !== 1) {
                $this->respond(['message' => 'Публикация не найдена.', 'type' => 'error'], 404);
            }
        } else {
            $statement = $this->db->prepare(
                'INSERT INTO `news_posts` (`authorUuid`, `title`, `summary`, `content`, `coverImage`, `isPublished`, `publishedAt`) '
                . 'VALUES (:authorUuid, :title, :summary, :content, :coverImage, :publishedFlag, '
                . 'CASE WHEN :publishedDateFlag = 1 THEN CURRENT_TIMESTAMP(4) ELSE NULL END)'
            );
            $statement->execute([
                ':authorUuid' => $this->session->uuid(),
                ':title' => $title,
                ':summary' => $summary,
                ':content' => $content,
                ':coverImage' => $coverImage === '' ? null : $coverImage,
                ':publishedFlag' => $isPublished ? 1 : 0,
                ':publishedDateFlag' => $isPublished ? 1 : 0,
            ]);
            $postId = (int)$this->db->lastInsertId();
        }

        $this->logger->logInfo('News publication saved.', [
            'postId' => $postId,
            'administrator' => $this->session->login(),
            'published' => $isPublished,
        ]);
        $this->respond([
            'message' => $isPublished ? 'Публикация сохранена и опубликована.' : 'Черновик сохранён.',
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
        $this->respond([
            'message' => 'Обложка загружена.',
            'type' => 'success',
            'coverImage' => $result->publicPath(),
            'upload' => $result,
        ], 201);
    }

    private function normalizeCoverImage(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }
        return $this->uploads->validateReference(UploadPurpose::NEWS_COVER, $value);
    }

    private function deletePost(): never
    {
        $this->requireAdminMutation();
        $postId = $this->positiveId('id');
        $statement = $this->db->prepare('DELETE FROM `news_posts` WHERE `id` = :postId');
        $statement->execute([':postId' => $postId]);
        if ($statement->rowCount() !== 1) {
            $this->respond(['message' => 'Публикация не найдена.', 'type' => 'error'], 404);
        }
        $this->logger->logInfo('News publication deleted.', [
            'postId' => $postId,
            'administrator' => $this->session->login(),
        ]);
        $this->respond(['message' => 'Публикация удалена.', 'type' => 'success']);
    }

    private function requireMutation(): void
    {
        if (!$this->session->isLogged()) {
            $this->respond(['message' => 'Для этого действия нужно войти в аккаунт.', 'type' => 'error'], 401);
        }
        CsrfToken::requireValid($this->request->csrfToken());
    }

    private function requireAdminMutation(): void
    {
        $this->requireMutation();
        if (!$this->session->isAdmin()) {
            $this->respond(['message' => 'Редактировать публикации может только администратор.', 'type' => 'error'], 403);
        }
    }

    private function publishedPostId(): int
    {
        $postId = $this->positiveId('id');
        if ((int)$this->scalar(
            'SELECT COUNT(*) FROM `news_posts` WHERE `id` = :postId AND `isPublished` = 1 AND `publishedAt` IS NOT NULL',
            [':postId' => $postId],
        ) !== 1) {
            $this->respond(['message' => 'Новость не найдена.', 'type' => 'error'], 404);
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

    private function decodeEntry(): array
    {
        try {
            $decoded = json_decode($this->request->string('entry'), true, 8, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new InvalidArgumentException('Некорректные данные публикации.');
        }
        if (!is_array($decoded) || array_is_list($decoded)) {
            throw new InvalidArgumentException('Публикация должна быть объектом.');
        }
        return $decoded;
    }

    private function normalizePost(array $post): array
    {
        $post['id'] = (int)$post['id'];
        $post['likesCount'] = (int)($post['likesCount'] ?? 0);
        $post['commentsCount'] = (int)($post['commentsCount'] ?? 0);
        $post['viewsCount'] = (int)($post['viewsCount'] ?? 0);
        $post['coverImage'] = (string)($post['coverImage'] ?? '');
        if (array_key_exists('content', $post)) {
            $post['content'] = $this->sanitizeContent((string)$post['content']);
        }
        $post['likedByViewer'] = (bool)($post['likedByViewer'] ?? false);
        $post['isPublished'] = (bool)($post['isPublished'] ?? false);
        $post['canEdit'] = $this->session->isAdmin();
        return $post;
    }

    private function sanitizeContent(string $html): string
    {
        $html = trim($html);
        if ($html === '') {
            return '';
        }
        if (!str_contains($html, '<')) {
            return $this->plainTextContent($html);
        }
        if (!class_exists(DOMDocument::class)) {
            return $this->plainTextContent(html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        }

        $document = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        try {
            $loaded = $document->loadHTML(
                '<?xml encoding="UTF-8"><!doctype html><html><body><div id="fox-news-content">' . $html . '</div></body></html>',
                LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING,
            );
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
        if ($loaded !== true) {
            return $this->plainTextContent(html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        }

        $wrapper = (new DOMXPath($document))->query('//*[@id="fox-news-content"]')->item(0);
        if (!$wrapper instanceof DOMElement) {
            return $this->plainTextContent(html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        }

        $nodes = [];
        foreach ((new DOMXPath($document))->query('.//*', $wrapper) ?: [] as $node) {
            if ($node instanceof DOMElement) {
                $nodes[] = $node;
            }
        }

        foreach ($nodes as $node) {
            if (!$node->parentNode) {
                continue;
            }
            $tag = strtolower($node->tagName);
            if (!in_array($tag, self::CONTENT_TAGS, true)) {
                if (in_array($tag, self::CONTENT_DANGEROUS_TAGS, true)) {
                    $node->parentNode->removeChild($node);
                } else {
                    while ($node->firstChild) {
                        $node->parentNode->insertBefore($node->firstChild, $node);
                    }
                    $node->parentNode->removeChild($node);
                }
                continue;
            }

            $attributes = [];
            foreach ($node->attributes as $attribute) {
                $attributes[] = [strtolower($attribute->name), $attribute->value];
            }
            foreach ($attributes as [$name, $value]) {
                $sanitized = $this->sanitizeContentAttribute($tag, $name, $value);
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

        $xpath = new DOMXPath($document);
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

    private function sanitizeContentAttribute(string $tag, string $name, string $value): ?string
    {
        $value = trim($value);
        if ($name === 'class') {
            return strlen($value) <= 512 && preg_match('/^[A-Za-z0-9 _:-]*$/D', $value) === 1 ? $value : null;
        }
        if ($name === 'style') {
            return $this->sanitizeContentStyle($value);
        }
        if ($name === 'title') {
            return mb_strlen($value) <= 300 ? $value : mb_substr($value, 0, 300);
        }
        if ($tag === 'a' && $name === 'href') {
            return $this->sanitizeContentUrl($value, false);
        }
        if ($tag === 'a' && $name === 'target') {
            return in_array($value, ['_self', '_blank'], true) ? $value : null;
        }
        if ($tag === 'a' && $name === 'rel') {
            return preg_match('/^[A-Za-z ]{1,120}$/D', $value) === 1 ? $value : null;
        }
        if ($tag === 'img' && $name === 'src') {
            return $this->sanitizeContentUrl($value, true);
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

    private function sanitizeContentStyle(string $style): ?string
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

    private function sanitizeContentUrl(string $value, bool $image): ?string
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

    private function plainTextContent(string $text): string
    {
        $text = trim($text);
        if ($text === '') {
            return '';
        }
        $escaped = htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
        return '<p>' . preg_replace('/\R/u', '<br>', $escaped) . '</p>';
    }

    private function contentText(string $html): string
    {
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\x{00A0}/u', ' ', $text) ?? $text;
        return trim($text);
    }

    private function scalar(string $sql, array $parameters = []): mixed
    {
        $statement = $this->db->prepare($sql);
        $statement->execute($parameters);
        return $statement->fetchColumn();
    }

    private function respond(array $payload, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=UTF-8');
        header('Cache-Control: no-store');
        $encoded = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($encoded)) {
            throw new RuntimeException('Unable to encode news response.');
        }
        exit($encoded);
    }
}
