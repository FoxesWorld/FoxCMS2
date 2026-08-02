-- Persisted public site identity and SEO settings controlled from the admin panel.

CREATE TABLE IF NOT EXISTS `site_settings` (
    `id` TINYINT UNSIGNED NOT NULL,
    `settings` LONGTEXT NOT NULL,
    `updatedAt` DATETIME(4) NOT NULL DEFAULT CURRENT_TIMESTAMP(4) ON UPDATE CURRENT_TIMESTAMP(4),
    `updatedByUuid` CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `site_settings` (`id`, `settings`) VALUES (1, '{}');
