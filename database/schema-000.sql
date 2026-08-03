-- FoxCMS empty installation baseline.
-- This file contains no accounts, password hashes, email addresses, IP addresses or tokens.
-- Import into an empty database, then run: php scripts/migrate.php

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE `users` (
    `user_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `login` VARCHAR(64) NOT NULL,
    `uuid` VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NULL,
    `password` VARCHAR(255) NOT NULL,
    `email` VARCHAR(254) NOT NULL,
    `groupTag` VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'user',
    `user_group` INT UNSIGNED NOT NULL DEFAULT 4 COMMENT 'legacy compatibility only',
    `realname` VARCHAR(64) NOT NULL DEFAULT '',
    `hash` VARCHAR(64) NOT NULL DEFAULT '',
    `reg_date` BIGINT UNSIGNED NOT NULL DEFAULT 0,
    `last_date` BIGINT UNSIGNED NOT NULL DEFAULT 0,
    `profilePhoto` VARCHAR(512) NOT NULL DEFAULT '/uploads/users/anonymous/avatar.jpg',
    `logged_ip` VARCHAR(45) NULL,
    `reg_ip` VARCHAR(45) NULL,
    `userStatus` VARCHAR(128) NULL,
    `land` VARCHAR(64) NULL,
    `colorScheme` VARCHAR(16) NOT NULL DEFAULT '#B5B8B1',
    `token` CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL,
    `units` BIGINT NOT NULL DEFAULT 0,
    `balance` LONGTEXT NOT NULL DEFAULT '{"version":1,"currencies":[{"code":"units","name":"Units","amount":0,"symbol":"U","primary":true},{"code":"crystals","name":"Crystals","amount":0,"symbol":"C","primary":false}]}',
    `badges` LONGTEXT NOT NULL DEFAULT '[]',
    `serversOnline` LONGTEXT NOT NULL DEFAULT '{}',
    `userPerms` LONGTEXT NOT NULL DEFAULT '{}',
    PRIMARY KEY (`user_id`),
    UNIQUE KEY `uq_users_legacy_uuid` (`uuid`),
    KEY `ix_users_login` (`login`),
    KEY `ix_users_email` (`email`),
    KEY `ix_users_group_tag` (`groupTag`),
    KEY `ix_users_last_date` (`last_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `usersession` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user` VARCHAR(64) NOT NULL,
    `userMd5` VARCHAR(256) NULL,
    `passMd5` VARCHAR(256) NULL,
    `serverId` VARCHAR(256) NULL,
    `accessToken` VARCHAR(256) NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_usersession_user` (`user`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `groupAssociation` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `groupName` VARCHAR(64) NOT NULL,
    `groupColor` VARCHAR(16) NOT NULL DEFAULT '#ffffff',
    `groupTag` VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    `groupNum` INT UNSIGNED NOT NULL,
    `groupType` VARCHAR(64) NOT NULL DEFAULT 'guest',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_group_tag` (`groupTag`),
    UNIQUE KEY `uq_group_number` (`groupNum`),
    UNIQUE KEY `uq_group_name` (`groupName`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `groupAssociation` (`groupName`, `groupColor`, `groupTag`, `groupNum`, `groupType`) VALUES
    ('Администраторы', '#e85d5d', 'admin', 1, 'admin'),
    ('Игроки', '#5bd08b', 'user', 4, 'user'),
    ('Гости', '#ffffff', 'guest', 5, 'guest'),
    ('Тестировщики', '#d6b35b', 'tester', 6, 'tester');

CREATE TABLE `regCodes` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(64) NOT NULL,
    `code` VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    `groupTag` VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'user',
    `groupNum` INT UNSIGNED NOT NULL DEFAULT 4 COMMENT 'legacy compatibility only',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_registration_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `servers` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `serverName` VARCHAR(64) NOT NULL,
    `host` VARCHAR(255) NOT NULL DEFAULT '',
    `port` INT UNSIGNED NOT NULL DEFAULT 25565,
    `ignoreDirs` LONGTEXT NOT NULL DEFAULT '[]',
    `enabled` VARCHAR(5) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'false',
    `checkLib` VARCHAR(5) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'false',
    `serverGroups` VARCHAR(255) NOT NULL DEFAULT '["user","guest","tester"]',
    `serverDescription` TEXT NOT NULL,
    `serverVersion` VARCHAR(64) NOT NULL DEFAULT '',
    `jreVersion` VARCHAR(64) NOT NULL DEFAULT '',
    `serverImage` VARCHAR(512) NOT NULL DEFAULT '',
    `modsInfo` LONGTEXT NOT NULL DEFAULT '[]',
    `mainClass` VARCHAR(255) NOT NULL DEFAULT '',
    `forgeVersion` VARCHAR(64) NOT NULL DEFAULT '',
    `client` VARCHAR(64) NOT NULL DEFAULT '',
    `mcpVersion` VARCHAR(64) NOT NULL DEFAULT '',
    `forgeGroup` VARCHAR(128) NOT NULL DEFAULT '',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_server_name` (`serverName`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `infobox` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `group_name` VARCHAR(64) NOT NULL,
    `start_timestamp` BIGINT UNSIGNED NOT NULL DEFAULT 0,
    `end_timestamp` BIGINT UNSIGNED NOT NULL DEFAULT 0,
    `title` VARCHAR(255) NOT NULL DEFAULT '',
    `text` TEXT NOT NULL,
    `image` VARCHAR(512) NOT NULL DEFAULT '',
    `button_text` VARCHAR(128) NOT NULL DEFAULT '',
    `button_url` VARCHAR(512) NOT NULL DEFAULT '',
    PRIMARY KEY (`id`),
    KEY `ix_infobox_group` (`group_name`),
    KEY `ix_infobox_window` (`start_timestamp`, `end_timestamp`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `badgesList` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `badgeName` VARCHAR(64) NOT NULL,
    `description` VARCHAR(512) NOT NULL DEFAULT '',
    `img` VARCHAR(512) NOT NULL DEFAULT '',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_badge_name` (`badgeName`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `badgesList` (`badgeName`, `description`, `img`) VALUES
('Знаток правил', 'Подтверждает, что пользователь ознакомился с правилами проекта.', ''),
('Раннее Возрождение', 'Участник раннего этапа возрождения FoxesCraft 3.0.', '');

CREATE TABLE `rewardDefinitions` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `rewardName` VARCHAR(160) NOT NULL,
    `description` TEXT NOT NULL,
    `badgeId` BIGINT UNSIGNED NULL,
    `currencyCode` VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NULL,
    `currencyAmount` BIGINT UNSIGNED NOT NULL DEFAULT 0,
    `enabled` TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,
    `createdAt` BIGINT UNSIGNED NOT NULL DEFAULT 0,
    `updatedAt` BIGINT UNSIGNED NOT NULL DEFAULT 0,
    `createdByUuid` CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NULL,
    `updatedByUuid` CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_reward_definition_name` (`rewardName`),
    KEY `ix_reward_definition_badge` (`badgeId`),
    KEY `ix_reward_definition_enabled` (`enabled`),
    CONSTRAINT `fk_reward_definition_badge`
        FOREIGN KEY (`badgeId`) REFERENCES `badgesList` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `ck_reward_definition_payload`
        CHECK (`badgeId` IS NOT NULL OR (`currencyCode` IS NOT NULL AND `currencyAmount` > 0)),
    CONSTRAINT `ck_reward_definition_currency`
        CHECK ((`currencyCode` IS NULL AND `currencyAmount` = 0) OR (`currencyCode` IS NOT NULL AND `currencyAmount` > 0))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `rewardDefinitions`
    (`rewardName`, `description`, `badgeId`, `currencyCode`, `currencyAmount`, `enabled`, `createdAt`, `updatedAt`)
SELECT
    'Награда за ознакомление с правилами',
    'Выдаёт бейдж за подтверждение ознакомления с правилами проекта.',
    `id`,
    NULL,
    0,
    1,
    UNIX_TIMESTAMP(),
    UNIX_TIMESTAMP()
FROM `badgesList`
WHERE `badgeName` = 'Знаток правил';

INSERT INTO `rewardDefinitions`
    (`rewardName`, `description`, `badgeId`, `currencyCode`, `currencyAmount`, `enabled`, `createdAt`, `updatedAt`)
SELECT
    'Награда раннего возрождения',
    'Выдаёт памятный бейдж и стартовый бонус ранним участникам FoxesCraft 3.0.',
    `id`,
    'crystals',
    3,
    1,
    UNIX_TIMESTAMP(),
    UNIX_TIMESTAMP()
FROM `badgesList`
WHERE `badgeName` = 'Раннее Возрождение';

CREATE TABLE `rewardClaimKeys` (
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

INSERT INTO `rewardClaimKeys`
    (`rewardId`, `tokenHash`, `tokenHint`, `usageMode`, `accessMode`, `publicPlacement`, `usesCount`, `enabled`, `createdAt`, `updatedAt`, `createdByUuid`)
SELECT
    `id`, SHA2(RANDOM_BYTES(32), 256), LOWER(HEX(RANDOM_BYTES(5))),
    'reusable', 'public', 'rules', 0, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), NULL
FROM `rewardDefinitions`
WHERE `rewardName` = 'Награда за ознакомление с правилами';

INSERT INTO `rewardClaimKeys`
    (`rewardId`, `tokenHash`, `tokenHint`, `usageMode`, `accessMode`, `publicPlacement`, `usesCount`, `enabled`, `createdAt`, `updatedAt`, `createdByUuid`)
SELECT
    `id`, SHA2(RANDOM_BYTES(32), 256), LOWER(HEX(RANDOM_BYTES(5))),
    'reusable', 'public', 'welcome-native', 0, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), NULL
FROM `rewardDefinitions`
WHERE `rewardName` = 'Награда раннего возрождения';

CREATE TABLE `rewardClaims` (
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

CREATE TABLE `system_hardware_inventory` (
    `systemHWID` CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    `schemaVersion` SMALLINT UNSIGNED NOT NULL,
    `updaterVersion` VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    `platform` VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    `osName` VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    `osVersion` VARCHAR(120) NULL,
    `kernelVersion` VARCHAR(120) NULL,
    `architecture` VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    `cpuBrand` VARCHAR(200) NULL,
    `logicalCpuCount` SMALLINT UNSIGNED NOT NULL,
    `memoryBytes` BIGINT UNSIGNED NOT NULL,
    `gpuAdapters` JSON NOT NULL,
    `report` JSON NOT NULL,
    `firstSeenAt` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`systemHWID`),
    KEY `idx_system_hardware_inventory_platform` (`platform`),
    KEY `idx_system_hardware_inventory_first_seen` (`firstSeenAt`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
