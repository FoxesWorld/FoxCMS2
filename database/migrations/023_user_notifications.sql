-- FoxCMS migration 023: persistent user notification center.

CREATE TABLE IF NOT EXISTS `userNotifications` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `userUuid` CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    `notificationType` VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    `severity` VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'info',
    `title` VARCHAR(160) NOT NULL,
    `message` VARCHAR(1000) NOT NULL,
    `actionUrl` VARCHAR(512) NULL,
    `payload` JSON NULL,
    `dedupeKey` VARCHAR(191) CHARACTER SET ascii COLLATE ascii_bin NULL,
    `createdAt` BIGINT UNSIGNED NOT NULL DEFAULT 0,
    `readAt` BIGINT UNSIGNED NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_user_notification_dedupe` (`userUuid`, `dedupeKey`),
    KEY `ix_user_notification_inbox` (`userUuid`, `readAt`, `id`),
    KEY `ix_user_notification_created` (`createdAt`),
    CONSTRAINT `fk_user_notification_user`
        FOREIGN KEY (`userUuid`) REFERENCES `users` (`uuid`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
