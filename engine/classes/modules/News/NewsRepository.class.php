<?php

declare(strict_types=1);

final class NewsRepository
{
    public function __construct(private db $database)
    {
    }

    /** @return list<array<string, mixed>> */
    public function listPosts(int $limit, int $offset, string $viewerUuid, bool $includeDrafts): array
    {
        $limit = max(1, min(50, $limit));
        $offset = max(0, $offset);
        $visibility = $includeDrafts
            ? '1 = 1'
            : '`post`.`isPublished` = 1 AND `post`.`publishedAt` IS NOT NULL';
        $statement = $this->database->prepare(
            'SELECT ' . $this->postFields($includeDrafts) . ' '
            . $this->postJoins() . ' '
            . 'WHERE ' . $visibility . ' '
            . 'ORDER BY COALESCE(`post`.`publishedAt`, `post`.`createdAt`) DESC, `post`.`id` DESC '
            . 'LIMIT ' . $limit . ' OFFSET ' . $offset
        );
        $statement->execute([':viewerUuid' => $viewerUuid]);
        return $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function countPosts(bool $includeDrafts): int
    {
        $visibility = $includeDrafts
            ? '1 = 1'
            : '`post`.`isPublished` = 1 AND `post`.`publishedAt` IS NOT NULL';
        return (int)$this->scalar(
            'SELECT COUNT(*) FROM `news_posts` AS `post` WHERE ' . $visibility,
        );
    }

    /** @return array<string, mixed>|null */
    public function findPost(
        int $postId,
        string $viewerUuid,
        bool $includeDrafts,
        bool $trackView,
    ): ?array {
        if ($trackView) {
            $statement = $this->database->prepare(
                'UPDATE `news_posts` SET `viewsCount` = `viewsCount` + 1 '
                . 'WHERE `id` = :postId AND `isPublished` = 1 AND `publishedAt` IS NOT NULL'
            );
            $statement->execute([':postId' => $postId]);
        }

        $visibility = $includeDrafts
            ? ''
            : ' AND `post`.`isPublished` = 1 AND `post`.`publishedAt` IS NOT NULL';
        $statement = $this->database->prepare(
            'SELECT ' . $this->postFields(true) . ' '
            . $this->postJoins() . ' '
            . 'WHERE `post`.`id` = :postId' . $visibility . ' LIMIT 1'
        );
        $statement->execute([
            ':viewerUuid' => $viewerUuid,
            ':postId' => $postId,
        ]);
        $post = $statement->fetch(PDO::FETCH_ASSOC);
        return is_array($post) ? $post : null;
    }

    /** @return list<array<string, mixed>> */
    public function comments(int $postId): array
    {
        $statement = $this->database->prepare(
            'SELECT `comment`.`id`, `comment`.`content`, `comment`.`createdAt`, `comment`.`updatedAt`, '
            . '`user`.`uuid` AS `authorUuid`, `user`.`login` AS `authorLogin`, '
            . 'COALESCE(NULLIF(`user`.`realname`, \'\'), `user`.`login`) AS `authorName`, '
            . '`user`.`profilePhoto` AS `authorPhoto`, `group`.`groupName` AS `authorGroup`, '
            . '`group`.`groupColor` AS `authorColor` '
            . 'FROM `news_comments` AS `comment` '
            . 'INNER JOIN `users` AS `user` ON `user`.`uuid` = `comment`.`authorUuid` '
            . 'LEFT JOIN `groupAssociation` AS `group` ON `group`.`groupTag` = `user`.`groupTag` '
            . 'WHERE `comment`.`postId` = :postId '
            . 'ORDER BY `comment`.`createdAt` ASC, `comment`.`id` ASC'
        );
        $statement->execute([':postId' => $postId]);
        return $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @return array{liked:bool,likesCount:int} */
    public function toggleLike(int $postId, string $userUuid): array
    {
        $liked = $this->database->transactional(function () use ($postId, $userUuid): bool {
            $existing = $this->database->prepare(
                'SELECT 1 FROM `news_likes` WHERE `postId` = :postId AND `userUuid` = :userUuid FOR UPDATE'
            );
            $existing->execute([':postId' => $postId, ':userUuid' => $userUuid]);
            if ($existing->fetchColumn() !== false) {
                $delete = $this->database->prepare(
                    'DELETE FROM `news_likes` WHERE `postId` = :postId AND `userUuid` = :userUuid'
                );
                $delete->execute([':postId' => $postId, ':userUuid' => $userUuid]);
                return false;
            }

            $insert = $this->database->prepare(
                'INSERT INTO `news_likes` (`postId`, `userUuid`) VALUES (:postId, :userUuid)'
            );
            $insert->execute([':postId' => $postId, ':userUuid' => $userUuid]);
            return true;
        });

        return [
            'liked' => $liked,
            'likesCount' => (int)$this->scalar(
                'SELECT COUNT(*) FROM `news_likes` WHERE `postId` = :postId',
                [':postId' => $postId],
            ),
        ];
    }

    public function isPublished(int $postId): bool
    {
        return (int)$this->scalar(
            'SELECT COUNT(*) FROM `news_posts` '
            . 'WHERE `id` = :postId AND `isPublished` = 1 AND `publishedAt` IS NOT NULL',
            [':postId' => $postId],
        ) === 1;
    }

    public function hasRecentComment(string $authorUuid, int $seconds = 15): bool
    {
        $seconds = max(1, min(3600, $seconds));
        return (int)$this->scalar(
            'SELECT COUNT(*) FROM `news_comments` WHERE `authorUuid` = :authorUuid '
            . 'AND `createdAt` >= DATE_SUB(CURRENT_TIMESTAMP(4), INTERVAL ' . $seconds . ' SECOND)',
            [':authorUuid' => $authorUuid],
        ) > 0;
    }

    public function addComment(int $postId, string $authorUuid, string $content): int
    {
        $statement = $this->database->prepare(
            'INSERT INTO `news_comments` (`postId`, `authorUuid`, `content`) '
            . 'VALUES (:postId, :authorUuid, :content)'
        );
        $statement->execute([
            ':postId' => $postId,
            ':authorUuid' => $authorUuid,
            ':content' => $content,
        ]);
        return (int)$this->database->lastInsertId();
    }

    public function commentAuthor(int $commentId): ?string
    {
        $statement = $this->database->prepare(
            'SELECT `authorUuid` FROM `news_comments` WHERE `id` = :commentId LIMIT 1'
        );
        $statement->execute([':commentId' => $commentId]);
        $authorUuid = $statement->fetchColumn();
        return is_string($authorUuid) ? $authorUuid : null;
    }

    public function deleteComment(int $commentId): bool
    {
        $statement = $this->database->prepare('DELETE FROM `news_comments` WHERE `id` = :commentId');
        $statement->execute([':commentId' => $commentId]);
        return $statement->rowCount() === 1;
    }

    /**
     * @param array{title:string,summary:string,content:string,coverImage:string,isPublished:bool} $post
     */
    public function savePost(int $postId, string $authorUuid, array $post): int
    {
        if ($postId > 0) {
            $statement = $this->database->prepare(
                'UPDATE `news_posts` SET `title` = :title, `summary` = :summary, '
                . '`content` = :content, `coverImage` = :coverImage, `isPublished` = :publishedFlag, '
                . '`publishedAt` = CASE WHEN :publishedDateFlag = 1 '
                . 'THEN COALESCE(`publishedAt`, CURRENT_TIMESTAMP(4)) ELSE NULL END '
                . 'WHERE `id` = :postId'
            );
            $statement->execute([
                ':title' => $post['title'],
                ':summary' => $post['summary'],
                ':content' => $post['content'],
                ':coverImage' => $post['coverImage'] === '' ? null : $post['coverImage'],
                ':publishedFlag' => $post['isPublished'] ? 1 : 0,
                ':publishedDateFlag' => $post['isPublished'] ? 1 : 0,
                ':postId' => $postId,
            ]);
            if ($statement->rowCount() === 0 && !$this->postExists($postId)) {
                throw new HttpException('Публикация не найдена.', 404);
            }
            return $postId;
        }

        $statement = $this->database->prepare(
            'INSERT INTO `news_posts` '
            . '(`authorUuid`, `title`, `summary`, `content`, `coverImage`, `isPublished`, `publishedAt`) '
            . 'VALUES (:authorUuid, :title, :summary, :content, :coverImage, :publishedFlag, '
            . 'CASE WHEN :publishedDateFlag = 1 THEN CURRENT_TIMESTAMP(4) ELSE NULL END)'
        );
        $statement->execute([
            ':authorUuid' => $authorUuid,
            ':title' => $post['title'],
            ':summary' => $post['summary'],
            ':content' => $post['content'],
            ':coverImage' => $post['coverImage'] === '' ? null : $post['coverImage'],
            ':publishedFlag' => $post['isPublished'] ? 1 : 0,
            ':publishedDateFlag' => $post['isPublished'] ? 1 : 0,
        ]);
        return (int)$this->database->lastInsertId();
    }

    public function deletePost(int $postId): bool
    {
        $statement = $this->database->prepare('DELETE FROM `news_posts` WHERE `id` = :postId');
        $statement->execute([':postId' => $postId]);
        return $statement->rowCount() === 1;
    }

    private function postExists(int $postId): bool
    {
        return (int)$this->scalar(
            'SELECT COUNT(*) FROM `news_posts` WHERE `id` = :postId',
            [':postId' => $postId],
        ) === 1;
    }

    private function postFields(bool $includeContent): string
    {
        return '`post`.`id`, `post`.`title`, `post`.`summary`, `post`.`coverImage`, '
            . '`post`.`isPublished`' . ($includeContent ? ', `post`.`content`' : '') . ', '
            . '`post`.`viewsCount`, `post`.`publishedAt`, `post`.`createdAt`, `post`.`updatedAt`, '
            . '`user`.`login` AS `authorLogin`, '
            . 'COALESCE(NULLIF(`user`.`realname`, \'\'), `user`.`login`) AS `authorName`, '
            . '`user`.`profilePhoto` AS `authorPhoto`, `group`.`groupName` AS `authorGroup`, '
            . '`group`.`groupColor` AS `authorColor`, '
            . '(SELECT COUNT(*) FROM `news_likes` AS `likeRow` '
            . 'WHERE `likeRow`.`postId` = `post`.`id`) AS `likesCount`, '
            . '(SELECT COUNT(*) FROM `news_comments` AS `commentRow` '
            . 'WHERE `commentRow`.`postId` = `post`.`id`) AS `commentsCount`, '
            . 'EXISTS(SELECT 1 FROM `news_likes` AS `viewerLike` '
            . 'WHERE `viewerLike`.`postId` = `post`.`id` '
            . 'AND `viewerLike`.`userUuid` = :viewerUuid) AS `likedByViewer`';
    }

    private function postJoins(): string
    {
        return 'FROM `news_posts` AS `post` '
            . 'INNER JOIN `users` AS `user` ON `user`.`uuid` = `post`.`authorUuid` '
            . 'LEFT JOIN `groupAssociation` AS `group` ON `group`.`groupTag` = `user`.`groupTag`';
    }

    private function scalar(string $sql, array $parameters = []): mixed
    {
        $statement = $this->database->prepare($sql);
        $statement->execute($parameters);
        return $statement->fetchColumn();
    }
}
