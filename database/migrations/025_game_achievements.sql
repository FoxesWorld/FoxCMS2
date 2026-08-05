-- FoxCMS migration 025: authenticated Minecraft server achievement catalog and player progress.

CREATE TABLE IF NOT EXISTS `gameAchievements` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `serverId` VARCHAR(100) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    `gameCode` VARCHAR(50) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'minecraft',
    `achievementKey` VARCHAR(190) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    `achievementType` VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'advancement',
    `parentKey` VARCHAR(190) CHARACTER SET ascii COLLATE ascii_bin NULL,
    `title` VARCHAR(190) NOT NULL,
    `description` TEXT NOT NULL,
    `frameType` VARCHAR(24) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'task',
    `category` VARCHAR(100) NOT NULL DEFAULT 'general',
    `iconBase64` MEDIUMTEXT NOT NULL,
    `iconMime` VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'image/png',
    `iconItem` VARCHAR(190) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT '',
    `iconComponents` JSON NOT NULL,
    `criteria` JSON NOT NULL,
    `requirements` JSON NOT NULL,
    `points` INT UNSIGNED NOT NULL DEFAULT 0,
    `hidden` TINYINT(1) NOT NULL DEFAULT 0,
    `announceToChat` TINYINT(1) NOT NULL DEFAULT 0,
    `showToast` TINYINT(1) NOT NULL DEFAULT 1,
    `enabled` TINYINT(1) NOT NULL DEFAULT 1,
    `definitionHash` CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    `catalogRevision` CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    `createdAt` BIGINT UNSIGNED NOT NULL,
    `updatedAt` BIGINT UNSIGNED NOT NULL,
    `lastSeenAt` BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_game_achievement_key` (`serverId`, `gameCode`, `achievementKey`),
    KEY `ix_game_achievement_catalog` (`serverId`, `enabled`, `category`),
    KEY `ix_game_achievement_revision` (`serverId`, `catalogRevision`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `playerAchievements` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `serverId` VARCHAR(100) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    `playerUuid` CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    `playerName` VARCHAR(64) NOT NULL DEFAULT '',
    `achievementId` BIGINT UNSIGNED NOT NULL,
    `progress` BIGINT UNSIGNED NOT NULL DEFAULT 0,
    `target` BIGINT UNSIGNED NOT NULL DEFAULT 1,
    `completed` TINYINT(1) NOT NULL DEFAULT 0,
    `firstProgressAt` BIGINT UNSIGNED NOT NULL,
    `completedAt` BIGINT UNSIGNED NULL,
    `updatedAt` BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_player_server_achievement` (`serverId`, `playerUuid`, `achievementId`),
    KEY `ix_player_achievement_profile` (`playerUuid`, `completed`, `updatedAt`),
    KEY `ix_player_achievement_completed` (`achievementId`, `completed`, `completedAt`),
    CONSTRAINT `fk_player_achievement_user`
        FOREIGN KEY (`playerUuid`) REFERENCES `users` (`uuid`) ON DELETE CASCADE,
    CONSTRAINT `fk_player_achievement_definition`
        FOREIGN KEY (`achievementId`) REFERENCES `gameAchievements` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `gameAchievementEvents` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `eventUuid` CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    `serverId` VARCHAR(100) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    `playerUuid` CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    `achievementKey` VARCHAR(190) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    `eventType` VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'advancement',
    `payload` JSON NOT NULL,
    `occurredAt` BIGINT UNSIGNED NOT NULL,
    `receivedAt` BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_game_achievement_event_uuid` (`eventUuid`),
    KEY `ix_game_achievement_event_player` (`playerUuid`, `receivedAt`),
    KEY `ix_game_achievement_event_server` (`serverId`, `receivedAt`),
    CONSTRAINT `fk_game_achievement_event_user`
        FOREIGN KEY (`playerUuid`) REFERENCES `users` (`uuid`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
