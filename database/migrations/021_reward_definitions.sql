-- FoxCMS migration 021: independent reward definitions.
-- Badges remain a catalog. Rewards independently configure a badge, currency, or both.

SET @badges_engine := (
    SELECT `ENGINE`
    FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'badgesList'
    LIMIT 1
);
SET @ensure_badges_innodb := IF(
    LOWER(COALESCE(@badges_engine, '')) = 'innodb',
    'SELECT 1',
    'ALTER TABLE `badgesList` ENGINE=InnoDB'
);
PREPARE migrationStatement FROM @ensure_badges_innodb;
EXECUTE migrationStatement;
DEALLOCATE PREPARE migrationStatement;

SET @badge_id_type := (
    SELECT `COLUMN_TYPE`
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'badgesList'
      AND COLUMN_NAME = 'id'
    LIMIT 1
);
SET @badge_id_type := IF(
    @badge_id_type REGEXP '^(tinyint|smallint|mediumint|int|bigint)([(][0-9]+[)])?( unsigned)?$',
    @badge_id_type,
    'BIGINT UNSIGNED'
);
SET @create_reward_definitions := CONCAT(
    'CREATE TABLE IF NOT EXISTS `rewardDefinitions` (',
    '`id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,',
    '`rewardName` VARCHAR(160) NOT NULL,',
    '`description` TEXT NOT NULL,',
    '`badgeId` ', @badge_id_type, ' NULL,',
    '`currencyCode` VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NULL,',
    '`currencyAmount` BIGINT UNSIGNED NOT NULL DEFAULT 0,',
    '`enabled` TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,',
    '`createdAt` BIGINT UNSIGNED NOT NULL DEFAULT 0,',
    '`updatedAt` BIGINT UNSIGNED NOT NULL DEFAULT 0,',
    '`createdByUuid` CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NULL,',
    '`updatedByUuid` CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NULL,',
    'PRIMARY KEY (`id`),',
    'UNIQUE KEY `uq_reward_definition_name` (`rewardName`),',
    'KEY `ix_reward_definition_badge` (`badgeId`),',
    'KEY `ix_reward_definition_enabled` (`enabled`),',
    'CONSTRAINT `fk_reward_definition_badge` FOREIGN KEY (`badgeId`) ',
    'REFERENCES `badgesList` (`id`) ON DELETE RESTRICT,',
    'CONSTRAINT `ck_reward_definition_payload` CHECK ',
    '(`badgeId` IS NOT NULL OR (`currencyCode` IS NOT NULL AND `currencyAmount` > 0)),',
    'CONSTRAINT `ck_reward_definition_currency` CHECK ',
    '((`currencyCode` IS NULL AND `currencyAmount` = 0) OR ',
    '(`currencyCode` IS NOT NULL AND `currencyAmount` > 0))',
    ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
);
PREPARE migrationStatement FROM @create_reward_definitions;
EXECUTE migrationStatement;
DEALLOCATE PREPARE migrationStatement;

SET @has_old_keys := (
    SELECT COUNT(*) FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'badgeClaimKeys'
);
SET @migrate_reward_definitions := IF(
    @has_old_keys = 1,
    "INSERT INTO `rewardDefinitions`
        (`rewardName`, `description`, `badgeId`, `currencyCode`, `currencyAmount`, `enabled`, `createdAt`, `updatedAt`)
     SELECT CONCAT('Выдача: ', `badge`.`badgeName`),
            CONCAT('Мигрировано из прежней выдачи бейджа «', `badge`.`badgeName`, '».'),
            `badge`.`id`, NULLIF(`badge`.`rewardCurrency`, ''), `badge`.`rewardAmount`, 1,
            UNIX_TIMESTAMP(), UNIX_TIMESTAMP()
     FROM `badgesList` AS `badge`
     WHERE EXISTS (SELECT 1 FROM `badgeClaimKeys` AS `oldKey` WHERE `oldKey`.`badgeId` = `badge`.`id`)
        OR `badge`.`rewardAmount` > 0
     ON DUPLICATE KEY UPDATE
        `badgeId` = VALUES(`badgeId`),
        `currencyCode` = VALUES(`currencyCode`),
        `currencyAmount` = VALUES(`currencyAmount`),
        `updatedAt` = UNIX_TIMESTAMP()",
    'SELECT 1'
);
PREPARE migrationStatement FROM @migrate_reward_definitions;
EXECUTE migrationStatement;
DEALLOCATE PREPARE migrationStatement;

CREATE TABLE IF NOT EXISTS `rewardClaimKeys` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `rewardId` BIGINT UNSIGNED NOT NULL,
    `tokenHash` CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    `tokenHint` VARCHAR(12) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT '',
    `usageMode` VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'single',
    `accessMode` VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'code',
    `publicPlacement` VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL,
    `usesCount` BIGINT UNSIGNED NOT NULL DEFAULT 0,
    `enabled` TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,
    `createdAt` BIGINT UNSIGNED NOT NULL DEFAULT 0,
    `updatedAt` BIGINT UNSIGNED NOT NULL DEFAULT 0,
    `createdByUuid` CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_reward_claim_key_hash` (`tokenHash`),
    UNIQUE KEY `uq_reward_claim_public_placement` (`publicPlacement`),
    KEY `ix_reward_claim_key_reward` (`rewardId`),
    KEY `ix_reward_claim_key_enabled` (`enabled`),
    CONSTRAINT `fk_reward_claim_key_reward`
        FOREIGN KEY (`rewardId`) REFERENCES `rewardDefinitions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @migrate_reward_keys := IF(
    @has_old_keys = 1,
    "INSERT IGNORE INTO `rewardClaimKeys`
        (`id`, `rewardId`, `tokenHash`, `tokenHint`, `usageMode`, `accessMode`, `publicPlacement`,
         `usesCount`, `enabled`, `createdAt`, `updatedAt`, `createdByUuid`)
     SELECT `oldKey`.`id`, `reward`.`id`, `oldKey`.`tokenHash`, `oldKey`.`tokenHint`,
            `oldKey`.`usageMode`, `oldKey`.`accessMode`, `oldKey`.`publicPlacement`,
            `oldKey`.`usesCount`, `oldKey`.`enabled`, `oldKey`.`createdAt`, `oldKey`.`updatedAt`, `oldKey`.`createdByUuid`
     FROM `badgeClaimKeys` AS `oldKey`
     INNER JOIN `badgesList` AS `badge` ON `badge`.`id` = `oldKey`.`badgeId`
     INNER JOIN `rewardDefinitions` AS `reward`
         ON `reward`.`rewardName` COLLATE utf8mb4_unicode_ci
          = CONVERT(CONCAT('Выдача: ', `badge`.`badgeName`) USING utf8mb4) COLLATE utf8mb4_unicode_ci",
    'SELECT 1'
);
PREPARE migrationStatement FROM @migrate_reward_keys;
EXECUTE migrationStatement;
DEALLOCATE PREPARE migrationStatement;

CREATE TABLE IF NOT EXISTS `rewardClaims` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `rewardId` BIGINT UNSIGNED NOT NULL,
    `keyId` BIGINT UNSIGNED NOT NULL,
    `userUuid` CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    `badgeGranted` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
    `badgeId` BIGINT UNSIGNED NULL,
    `badgeName` VARCHAR(120) NULL,
    `currencyCode` VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NULL,
    `currencyAmount` BIGINT UNSIGNED NOT NULL DEFAULT 0,
    `claimedAt` BIGINT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_reward_claim_reward_user` (`rewardId`, `userUuid`),
    KEY `ix_reward_claim_key` (`keyId`),
    KEY `ix_reward_claim_user` (`userUuid`),
    CONSTRAINT `fk_reward_claim_reward`
        FOREIGN KEY (`rewardId`) REFERENCES `rewardDefinitions` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_reward_claim_key`
        FOREIGN KEY (`keyId`) REFERENCES `rewardClaimKeys` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @has_old_claims := (
    SELECT COUNT(*) FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'badgeKeyClaims'
);
SET @migrate_reward_claims := IF(
    @has_old_claims = 1 AND @has_old_keys = 1,
    "INSERT IGNORE INTO `rewardClaims`
        (`id`, `rewardId`, `keyId`, `userUuid`, `badgeGranted`, `badgeId`, `badgeName`, `currencyCode`, `currencyAmount`, `claimedAt`)
     SELECT `oldClaim`.`id`, `newKey`.`rewardId`, `newKey`.`id`, `oldClaim`.`userUuid`,
            1, `reward`.`badgeId`, `badge`.`badgeName`, `reward`.`currencyCode`, `reward`.`currencyAmount`, `oldClaim`.`claimedAt`
     FROM `badgeKeyClaims` AS `oldClaim`
     INNER JOIN `rewardClaimKeys` AS `newKey` ON `newKey`.`id` = `oldClaim`.`keyId`
     INNER JOIN `rewardDefinitions` AS `reward` ON `reward`.`id` = `newKey`.`rewardId`
     LEFT JOIN `badgesList` AS `badge` ON `badge`.`id` = `reward`.`badgeId`",
    'SELECT 1'
);
PREPARE migrationStatement FROM @migrate_reward_claims;
EXECUTE migrationStatement;
DEALLOCATE PREPARE migrationStatement;

DROP TABLE IF EXISTS `badgeKeyClaims`;
DROP TABLE IF EXISTS `badgeClaimKeys`;

SET @has_reward_currency := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'badgesList' AND COLUMN_NAME = 'rewardCurrency'
);
SET @drop_reward_currency := IF(@has_reward_currency = 1,
    'ALTER TABLE `badgesList` DROP COLUMN `rewardCurrency`', 'SELECT 1');
PREPARE migrationStatement FROM @drop_reward_currency;
EXECUTE migrationStatement;
DEALLOCATE PREPARE migrationStatement;

SET @has_reward_amount := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'badgesList' AND COLUMN_NAME = 'rewardAmount'
);
SET @drop_reward_amount := IF(@has_reward_amount = 1,
    'ALTER TABLE `badgesList` DROP COLUMN `rewardAmount`', 'SELECT 1');
PREPARE migrationStatement FROM @drop_reward_amount;
EXECUTE migrationStatement;
DEALLOCATE PREPARE migrationStatement;
