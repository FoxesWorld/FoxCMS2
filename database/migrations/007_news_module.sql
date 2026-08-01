-- News publications, unique per-user likes and comments.

CREATE TABLE IF NOT EXISTS `news_posts` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `authorUuid` CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    `title` VARCHAR(160) NOT NULL,
    `summary` VARCHAR(600) NOT NULL,
    `content` MEDIUMTEXT NOT NULL,
    `isPublished` TINYINT(1) NOT NULL DEFAULT 0,
    `publishedAt` DATETIME(4) NULL,
    `createdAt` DATETIME(4) NOT NULL DEFAULT CURRENT_TIMESTAMP(4),
    `updatedAt` DATETIME(4) NOT NULL DEFAULT CURRENT_TIMESTAMP(4) ON UPDATE CURRENT_TIMESTAMP(4),
    PRIMARY KEY (`id`),
    KEY `idx_news_posts_published` (`isPublished`, `publishedAt`, `id`),
    KEY `idx_news_posts_author` (`authorUuid`),
    CONSTRAINT `fk_news_posts_author`
        FOREIGN KEY (`authorUuid`) REFERENCES `users` (`uuid`)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `news_likes` (
    `postId` BIGINT UNSIGNED NOT NULL,
    `userUuid` CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    `createdAt` DATETIME(4) NOT NULL DEFAULT CURRENT_TIMESTAMP(4),
    PRIMARY KEY (`postId`, `userUuid`),
    KEY `idx_news_likes_user` (`userUuid`, `createdAt`),
    CONSTRAINT `fk_news_likes_post`
        FOREIGN KEY (`postId`) REFERENCES `news_posts` (`id`)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT `fk_news_likes_user`
        FOREIGN KEY (`userUuid`) REFERENCES `users` (`uuid`)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `news_comments` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `postId` BIGINT UNSIGNED NOT NULL,
    `authorUuid` CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    `content` VARCHAR(2000) NOT NULL,
    `createdAt` DATETIME(4) NOT NULL DEFAULT CURRENT_TIMESTAMP(4),
    `updatedAt` DATETIME(4) NOT NULL DEFAULT CURRENT_TIMESTAMP(4) ON UPDATE CURRENT_TIMESTAMP(4),
    PRIMARY KEY (`id`),
    KEY `idx_news_comments_post` (`postId`, `createdAt`, `id`),
    KEY `idx_news_comments_author` (`authorUuid`, `createdAt`),
    CONSTRAINT `fk_news_comments_post`
        FOREIGN KEY (`postId`) REFERENCES `news_posts` (`id`)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT `fk_news_comments_author`
        FOREIGN KEY (`authorUuid`) REFERENCES `users` (`uuid`)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
