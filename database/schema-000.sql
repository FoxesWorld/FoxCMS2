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
    `user_group` INT UNSIGNED NOT NULL DEFAULT 4,
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
    `balance` LONGTEXT NOT NULL DEFAULT '[]',
    `badges` LONGTEXT NOT NULL DEFAULT '[]',
    `serversOnline` LONGTEXT NOT NULL DEFAULT '{}',
    `userPerms` LONGTEXT NOT NULL DEFAULT '{}',
    PRIMARY KEY (`user_id`),
    UNIQUE KEY `uq_users_legacy_uuid` (`uuid`),
    KEY `ix_users_login` (`login`),
    KEY `ix_users_email` (`email`),
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

CREATE TABLE `userBadges` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `userLogin` VARCHAR(64) NOT NULL,
    `badges` LONGTEXT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_user_badges_login` (`userLogin`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `groupAssociation` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `groupName` VARCHAR(64) NOT NULL,
    `groupColor` VARCHAR(16) NOT NULL DEFAULT '#ffffff',
    `groupNum` INT UNSIGNED NOT NULL,
    `groupType` VARCHAR(64) NOT NULL DEFAULT 'guest',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_group_number` (`groupNum`),
    UNIQUE KEY `uq_group_name` (`groupName`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `groupAssociation` (`groupName`, `groupColor`, `groupNum`, `groupType`) VALUES
    ('Администраторы', '#e85d5d', 1, 'admin'),
    ('Игроки', '#5bd08b', 4, 'user'),
    ('Гости', '#ffffff', 5, 'guest'),
    ('Тестировщики', '#d6b35b', 6, 'tester');

CREATE TABLE `regCodes` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(64) NOT NULL,
    `code` VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    `groupNum` INT UNSIGNED NOT NULL DEFAULT 4,
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
    `serverGroups` VARCHAR(255) NOT NULL DEFAULT '4,5,6',
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

SET FOREIGN_KEY_CHECKS = 1;
