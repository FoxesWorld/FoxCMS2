-- Publication cover image and read counter for the horizontal news card.

ALTER TABLE `news_posts`
    ADD COLUMN IF NOT EXISTS `coverImage` VARCHAR(512) NULL AFTER `content`,
    ADD COLUMN IF NOT EXISTS `viewsCount` BIGINT UNSIGNED NOT NULL DEFAULT 0 AFTER `isPublished`;
