-- FoxCMS migration 024: browser session registry for the "My devices" page.

CREATE TABLE IF NOT EXISTS `userBrowserSessions` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `sessionUuid` CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    `userUuid` CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    `rememberDigest` CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL,
    `sessionType` VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'short',
    `ipAddress` VARCHAR(45) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT '',
    `userAgent` VARCHAR(512) NOT NULL DEFAULT '',
    `browser` VARCHAR(100) NOT NULL DEFAULT '',
    `operatingSystem` VARCHAR(100) NOT NULL DEFAULT '',
    `deviceLabel` VARCHAR(180) NOT NULL DEFAULT '',
    `locationLabel` VARCHAR(180) NOT NULL DEFAULT '',
    `createdAt` BIGINT UNSIGNED NOT NULL DEFAULT 0,
    `lastSeenAt` BIGINT UNSIGNED NOT NULL DEFAULT 0,
    `expiresAt` BIGINT UNSIGNED NOT NULL DEFAULT 0,
    `idleExpiresAt` BIGINT UNSIGNED NOT NULL DEFAULT 0,
    `revokedAt` BIGINT UNSIGNED NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_user_browser_session_uuid` (`sessionUuid`),
    UNIQUE KEY `uq_user_browser_remember_digest` (`rememberDigest`),
    KEY `ix_user_browser_session_active` (`userUuid`, `revokedAt`, `expiresAt`, `idleExpiresAt`),
    KEY `ix_user_browser_session_last_seen` (`userUuid`, `lastSeenAt`),
    CONSTRAINT `fk_user_browser_session_user`
        FOREIGN KEY (`userUuid`) REFERENCES `users` (`uuid`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
