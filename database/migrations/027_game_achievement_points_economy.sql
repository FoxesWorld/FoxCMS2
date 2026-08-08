-- FoxCMS migration 027: achievement points economy and idempotent conversion to Units.

CREATE TABLE IF NOT EXISTS `gameAchievementPointAwards` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `serverId` VARCHAR(100) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    `playerUuid` CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    `achievementKey` VARCHAR(190) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    `pointsAwarded` INT UNSIGNED NOT NULL DEFAULT 0,
    `awardedAt` BIGINT UNSIGNED NOT NULL,
    `createdAt` BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_game_achievement_point_award` (`serverId`, `playerUuid`, `achievementKey`),
    KEY `ix_game_achievement_point_award_player` (`playerUuid`, `awardedAt`),
    KEY `ix_game_achievement_point_award_server` (`serverId`, `awardedAt`),
    CONSTRAINT `fk_game_achievement_point_award_user`
        FOREIGN KEY (`playerUuid`) REFERENCES `users` (`uuid`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `gameAchievementPointExchanges` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `requestUuid` CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    `playerUuid` CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    `pointsSpent` BIGINT UNSIGNED NOT NULL,
    `unitsGranted` BIGINT UNSIGNED NOT NULL,
    `pointsPerUnit` INT UNSIGNED NOT NULL,
    `createdAt` BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_game_achievement_point_exchange_request` (`requestUuid`),
    KEY `ix_game_achievement_point_exchange_player` (`playerUuid`, `createdAt`),
    CONSTRAINT `fk_game_achievement_point_exchange_user`
        FOREIGN KEY (`playerUuid`) REFERENCES `users` (`uuid`) ON DELETE CASCADE,
    CONSTRAINT `ck_game_achievement_point_exchange_values`
        CHECK (`pointsSpent` > 0 AND `unitsGranted` > 0 AND `pointsPerUnit` > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `gameAchievementEconomySettings` (
    `id` TINYINT UNSIGNED NOT NULL,
    `enabled` TINYINT(1) NOT NULL DEFAULT 1,
    `pointsPerUnit` INT UNSIGNED NOT NULL DEFAULT 10,
    `minimumPoints` INT UNSIGNED NOT NULL DEFAULT 10,
    `updatedAt` BIGINT UNSIGNED NOT NULL,
    `updatedByUuid` CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NULL,
    PRIMARY KEY (`id`),
    CONSTRAINT `ck_game_achievement_economy_singleton` CHECK (`id` = 1),
    CONSTRAINT `ck_game_achievement_economy_rate` CHECK (`pointsPerUnit` > 0 AND `minimumPoints` > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `gameAchievementEconomySettings`
    (`id`, `enabled`, `pointsPerUnit`, `minimumPoints`, `updatedAt`, `updatedByUuid`)
VALUES (1, 1, 10, 10, UNIX_TIMESTAMP(), NULL)
ON DUPLICATE KEY UPDATE `id` = VALUES(`id`);

-- Existing completions participate in the economy. The current catalog value is
-- snapshotted exactly once and never follows later catalog point changes.
INSERT IGNORE INTO `gameAchievementPointAwards`
    (`serverId`, `playerUuid`, `achievementKey`, `pointsAwarded`, `awardedAt`, `createdAt`)
SELECT
    `player`.`serverId`,
    `player`.`playerUuid`,
    `achievement`.`achievementKey`,
    `achievement`.`points`,
    COALESCE(`player`.`completedAt`, `player`.`updatedAt`),
    UNIX_TIMESTAMP()
FROM `playerAchievements` AS `player`
INNER JOIN `gameAchievements` AS `achievement`
    ON `achievement`.`id` = `player`.`achievementId`
WHERE `player`.`completed` = 1
  AND `achievement`.`achievementKey` NOT LIKE '%:advancement/recipes/%';
