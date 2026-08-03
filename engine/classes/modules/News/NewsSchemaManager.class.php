<?php

declare(strict_types=1);

final class NewsSchemaManager
{
    private bool $verified = false;

    public function __construct(private db $database)
    {
    }

    public function ensure(): void
    {
        if ($this->verified) {
            return;
        }

        $statement = $this->database->query(
            "SELECT COUNT(*) FROM information_schema.TABLES "
            . "WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ('news_posts', 'news_likes', 'news_comments')"
        );
        $tableCount = $statement instanceof PDOStatement ? (int)$statement->fetchColumn() : 0;
        if ($tableCount < 3) {
            $this->createTables();
        }

        $this->addColumnIfMissing(
            'coverImage',
            'ALTER TABLE `news_posts` ADD COLUMN `coverImage` VARCHAR(512) NULL AFTER `content`',
        );
        $this->addColumnIfMissing(
            'viewsCount',
            'ALTER TABLE `news_posts` ADD COLUMN `viewsCount` BIGINT UNSIGNED NOT NULL DEFAULT 0 AFTER `isPublished`',
        );
        $this->verified = true;
    }

    private function createTables(): void
    {
        $this->database->exec(
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
        $this->database->exec(
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
        $this->database->exec(
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

    private function addColumnIfMissing(string $column, string $sql): void
    {
        if ($this->hasPostColumn($column)) {
            return;
        }
        try {
            $this->database->exec($sql);
        } catch (Throwable $error) {
            // Concurrent requests may add the same compatibility column.
            if (!$this->hasPostColumn($column)) {
                throw $error;
            }
        }
    }

    private function hasPostColumn(string $column): bool
    {
        $statement = $this->database->prepare(
            "SELECT COUNT(*) FROM information_schema.COLUMNS "
            . "WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'news_posts' AND COLUMN_NAME = :column"
        );
        $statement->execute([':column' => $column]);
        return (int)$statement->fetchColumn() === 1;
    }
}
