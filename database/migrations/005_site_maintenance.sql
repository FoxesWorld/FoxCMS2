-- Runtime maintenance mode controlled from the administrative panel.

CREATE TABLE IF NOT EXISTS `site_maintenance` (
    `id` TINYINT UNSIGNED NOT NULL,
    `enabled` TINYINT(1) NOT NULL DEFAULT 0,
    `allowedGroups` LONGTEXT NOT NULL,
    `title` VARCHAR(160) NOT NULL,
    `message` VARCHAR(1200) NOT NULL,
    `updatedAt` DATETIME(4) NOT NULL DEFAULT CURRENT_TIMESTAMP(4) ON UPDATE CURRENT_TIMESTAMP(4),
    `updatedByUuid` CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `site_maintenance`
    (`id`, `enabled`, `allowedGroups`, `title`, `message`)
VALUES
    (1, 0, '[1]', 'Ведутся технические работы', 'Мы обновляем систему. Доступ будет восстановлен после завершения работ.');
