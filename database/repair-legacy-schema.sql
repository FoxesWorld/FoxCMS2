-- FoxCMS schema repair for databases upgraded from the historical FoxEngine schema.
-- Safe to run repeatedly on MariaDB/MySQL: all table/column/index creation is conditional.
-- The file intentionally contains no DROP TABLE and does not delete user data.

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS `users` (
    `user_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `login` VARCHAR(64) NOT NULL,
    `uuid` CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    `password` VARCHAR(255) NOT NULL,
    `email` VARCHAR(254) NOT NULL,
    `user_group` INT UNSIGNED NOT NULL DEFAULT 4,
    `realname` VARCHAR(64) NOT NULL DEFAULT '',
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
    UNIQUE KEY `ux_users_uuid` (`uuid`),
    UNIQUE KEY `ux_users_login` (`login`),
    KEY `ix_users_email` (`email`),
    KEY `ix_users_last_date` (`last_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @fox_sql = IF(
    EXISTS(
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'users'
          AND COLUMN_NAME = 'user_id'
    ),
    'SELECT 1',
    'ALTER TABLE `users` ADD COLUMN `user_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY FIRST'
);
PREPARE fox_stmt FROM @fox_sql;
EXECUTE fox_stmt;
DEALLOCATE PREPARE fox_stmt;

SET @fox_sql = IF(
    EXISTS(
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'users'
          AND COLUMN_NAME = 'login'
    ),
    'SELECT 1',
    'ALTER TABLE `users` ADD COLUMN `login` VARCHAR(64) NOT NULL DEFAULT '''''
);
PREPARE fox_stmt FROM @fox_sql;
EXECUTE fox_stmt;
DEALLOCATE PREPARE fox_stmt;

SET @fox_sql = IF(
    EXISTS(
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'users'
          AND COLUMN_NAME = 'uuid'
    ),
    'SELECT 1',
    'ALTER TABLE `users` ADD COLUMN `uuid` CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NULL AFTER `login`'
);
PREPARE fox_stmt FROM @fox_sql;
EXECUTE fox_stmt;
DEALLOCATE PREPARE fox_stmt;

SET @fox_sql = IF(
    EXISTS(
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'users'
          AND COLUMN_NAME = 'password'
    ),
    'SELECT 1',
    'ALTER TABLE `users` ADD COLUMN `password` VARCHAR(255) NOT NULL DEFAULT '''''
);
PREPARE fox_stmt FROM @fox_sql;
EXECUTE fox_stmt;
DEALLOCATE PREPARE fox_stmt;

SET @fox_sql = IF(
    EXISTS(
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'users'
          AND COLUMN_NAME = 'email'
    ),
    'SELECT 1',
    'ALTER TABLE `users` ADD COLUMN `email` VARCHAR(254) NOT NULL DEFAULT '''''
);
PREPARE fox_stmt FROM @fox_sql;
EXECUTE fox_stmt;
DEALLOCATE PREPARE fox_stmt;

SET @fox_sql = IF(
    EXISTS(
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'users'
          AND COLUMN_NAME = 'user_group'
    ),
    'SELECT 1',
    'ALTER TABLE `users` ADD COLUMN `user_group` INT UNSIGNED NOT NULL DEFAULT 4'
);
PREPARE fox_stmt FROM @fox_sql;
EXECUTE fox_stmt;
DEALLOCATE PREPARE fox_stmt;

SET @fox_sql = IF(
    EXISTS(
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'users'
          AND COLUMN_NAME = 'realname'
    ),
    'SELECT 1',
    'ALTER TABLE `users` ADD COLUMN `realname` VARCHAR(64) NOT NULL DEFAULT '''''
);
PREPARE fox_stmt FROM @fox_sql;
EXECUTE fox_stmt;
DEALLOCATE PREPARE fox_stmt;

SET @fox_sql = IF(
    EXISTS(
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'users'
          AND COLUMN_NAME = 'reg_date'
    ),
    'SELECT 1',
    'ALTER TABLE `users` ADD COLUMN `reg_date` BIGINT UNSIGNED NOT NULL DEFAULT 0'
);
PREPARE fox_stmt FROM @fox_sql;
EXECUTE fox_stmt;
DEALLOCATE PREPARE fox_stmt;

SET @fox_sql = IF(
    EXISTS(
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'users'
          AND COLUMN_NAME = 'last_date'
    ),
    'SELECT 1',
    'ALTER TABLE `users` ADD COLUMN `last_date` BIGINT UNSIGNED NOT NULL DEFAULT 0'
);
PREPARE fox_stmt FROM @fox_sql;
EXECUTE fox_stmt;
DEALLOCATE PREPARE fox_stmt;

SET @fox_sql = IF(
    EXISTS(
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'users'
          AND COLUMN_NAME = 'profilePhoto'
    ),
    'SELECT 1',
    'ALTER TABLE `users` ADD COLUMN `profilePhoto` VARCHAR(512) NOT NULL DEFAULT ''/uploads/users/anonymous/avatar.jpg'''
);
PREPARE fox_stmt FROM @fox_sql;
EXECUTE fox_stmt;
DEALLOCATE PREPARE fox_stmt;

SET @fox_sql = IF(
    EXISTS(
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'users'
          AND COLUMN_NAME = 'logged_ip'
    ),
    'SELECT 1',
    'ALTER TABLE `users` ADD COLUMN `logged_ip` VARCHAR(45) NULL'
);
PREPARE fox_stmt FROM @fox_sql;
EXECUTE fox_stmt;
DEALLOCATE PREPARE fox_stmt;

SET @fox_sql = IF(
    EXISTS(
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'users'
          AND COLUMN_NAME = 'reg_ip'
    ),
    'SELECT 1',
    'ALTER TABLE `users` ADD COLUMN `reg_ip` VARCHAR(45) NULL'
);
PREPARE fox_stmt FROM @fox_sql;
EXECUTE fox_stmt;
DEALLOCATE PREPARE fox_stmt;

SET @fox_sql = IF(
    EXISTS(
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'users'
          AND COLUMN_NAME = 'userStatus'
    ),
    'SELECT 1',
    'ALTER TABLE `users` ADD COLUMN `userStatus` VARCHAR(128) NULL'
);
PREPARE fox_stmt FROM @fox_sql;
EXECUTE fox_stmt;
DEALLOCATE PREPARE fox_stmt;

SET @fox_sql = IF(
    EXISTS(
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'users'
          AND COLUMN_NAME = 'land'
    ),
    'SELECT 1',
    'ALTER TABLE `users` ADD COLUMN `land` VARCHAR(64) NULL'
);
PREPARE fox_stmt FROM @fox_sql;
EXECUTE fox_stmt;
DEALLOCATE PREPARE fox_stmt;

SET @fox_sql = IF(
    EXISTS(
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'users'
          AND COLUMN_NAME = 'colorScheme'
    ),
    'SELECT 1',
    'ALTER TABLE `users` ADD COLUMN `colorScheme` VARCHAR(16) NOT NULL DEFAULT ''#B5B8B1'''
);
PREPARE fox_stmt FROM @fox_sql;
EXECUTE fox_stmt;
DEALLOCATE PREPARE fox_stmt;

SET @fox_sql = IF(
    EXISTS(
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'users'
          AND COLUMN_NAME = 'token'
    ),
    'SELECT 1',
    'ALTER TABLE `users` ADD COLUMN `token` CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL'
);
PREPARE fox_stmt FROM @fox_sql;
EXECUTE fox_stmt;
DEALLOCATE PREPARE fox_stmt;

SET @fox_sql = IF(
    EXISTS(
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'users'
          AND COLUMN_NAME = 'units'
    ),
    'SELECT 1',
    'ALTER TABLE `users` ADD COLUMN `units` BIGINT NOT NULL DEFAULT 0'
);
PREPARE fox_stmt FROM @fox_sql;
EXECUTE fox_stmt;
DEALLOCATE PREPARE fox_stmt;

SET @fox_sql = IF(
    EXISTS(
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'users'
          AND COLUMN_NAME = 'balance'
    ),
    'SELECT 1',
    'ALTER TABLE `users` ADD COLUMN `balance` LONGTEXT NOT NULL DEFAULT ''[]'''
);
PREPARE fox_stmt FROM @fox_sql;
EXECUTE fox_stmt;
DEALLOCATE PREPARE fox_stmt;

SET @fox_sql = IF(
    EXISTS(
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'users'
          AND COLUMN_NAME = 'badges'
    ),
    'SELECT 1',
    'ALTER TABLE `users` ADD COLUMN `badges` LONGTEXT NOT NULL DEFAULT ''[]'''
);
PREPARE fox_stmt FROM @fox_sql;
EXECUTE fox_stmt;
DEALLOCATE PREPARE fox_stmt;

SET @fox_sql = IF(
    EXISTS(
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'users'
          AND COLUMN_NAME = 'serversOnline'
    ),
    'SELECT 1',
    'ALTER TABLE `users` ADD COLUMN `serversOnline` LONGTEXT NOT NULL DEFAULT ''{}'''
);
PREPARE fox_stmt FROM @fox_sql;
EXECUTE fox_stmt;
DEALLOCATE PREPARE fox_stmt;

SET @fox_sql = IF(
    EXISTS(
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'users'
          AND COLUMN_NAME = 'userPerms'
    ),
    'SELECT 1',
    'ALTER TABLE `users` ADD COLUMN `userPerms` LONGTEXT NOT NULL DEFAULT ''{}'''
);
PREPARE fox_stmt FROM @fox_sql;
EXECUTE fox_stmt;
DEALLOCATE PREPARE fox_stmt;

UPDATE `users`
SET `uuid` = LOWER(UUID())
WHERE `uuid` IS NULL
   OR `uuid` NOT REGEXP '^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[1-8][0-9a-fA-F]{3}-[89aAbB][0-9a-fA-F]{3}-[0-9a-fA-F]{12}$';

UPDATE `users` AS `user`
INNER JOIN (
    SELECT `uuid`
    FROM `users`
    GROUP BY `uuid`
    HAVING COUNT(*) > 1
) AS `duplicate` ON `duplicate`.`uuid` = `user`.`uuid`
SET `user`.`uuid` = LOWER(UUID());

UPDATE `users` SET `balance` = '[]' WHERE `balance` IS NULL OR TRIM(`balance`) = '';
UPDATE `users` SET `badges` = '[]' WHERE `badges` IS NULL OR TRIM(`badges`) = '';
UPDATE `users` SET `serversOnline` = '{}' WHERE `serversOnline` IS NULL OR TRIM(`serversOnline`) = '';
UPDATE `users` SET `userPerms` = '{}' WHERE `userPerms` IS NULL OR TRIM(`userPerms`) = '';

ALTER TABLE `users`
    MODIFY COLUMN `uuid` CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    MODIFY COLUMN `login` VARCHAR(64) NOT NULL,
    MODIFY COLUMN `serversOnline` LONGTEXT NOT NULL DEFAULT '{}',
    MODIFY COLUMN `badges` LONGTEXT NOT NULL DEFAULT '[]',
    MODIFY COLUMN `balance` LONGTEXT NOT NULL DEFAULT '[]',
    MODIFY COLUMN `userPerms` LONGTEXT NOT NULL DEFAULT '{}';

SET @fox_sql = IF(
    EXISTS(
        SELECT 1 FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'users'
          AND COLUMN_NAME = 'uuid'
          AND NON_UNIQUE = 0
    ),
    'SELECT 1',
    'ALTER TABLE `users` ADD UNIQUE KEY `ux_users_uuid` (`uuid`)'
);
PREPARE fox_stmt FROM @fox_sql;
EXECUTE fox_stmt;
DEALLOCATE PREPARE fox_stmt;

SET @fox_sql = IF(
    EXISTS(
        SELECT 1 FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'users'
          AND COLUMN_NAME = 'login'
          AND NON_UNIQUE = 0
    ),
    'SELECT 1',
    'ALTER TABLE `users` ADD UNIQUE KEY `ux_users_login` (`login`)'
);
PREPARE fox_stmt FROM @fox_sql;
EXECUTE fox_stmt;
DEALLOCATE PREPARE fox_stmt;

SET @fox_sql = IF(
    EXISTS(
        SELECT 1 FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'users'
          AND INDEX_NAME = 'ix_users_email'
    ),
    'SELECT 1',
    'ALTER TABLE `users` ADD KEY `ix_users_email` (`email`)'
);
PREPARE fox_stmt FROM @fox_sql;
EXECUTE fox_stmt;
DEALLOCATE PREPARE fox_stmt;

SET @fox_sql = IF(
    EXISTS(
        SELECT 1 FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'users'
          AND INDEX_NAME = 'ix_users_last_date'
    ),
    'SELECT 1',
    'ALTER TABLE `users` ADD KEY `ix_users_last_date` (`last_date`)'
);
PREPARE fox_stmt FROM @fox_sql;
EXECUTE fox_stmt;
DEALLOCATE PREPARE fox_stmt;

CREATE TABLE IF NOT EXISTS `groupAssociation` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `groupName` VARCHAR(64) NOT NULL,
    `groupColor` VARCHAR(16) NOT NULL DEFAULT '#ffffff',
    `groupNum` INT UNSIGNED NOT NULL,
    `groupType` VARCHAR(64) NOT NULL DEFAULT 'guest',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_group_number` (`groupNum`),
    UNIQUE KEY `uq_group_name` (`groupName`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @fox_sql = IF(
    EXISTS(
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'groupAssociation'
          AND COLUMN_NAME = 'id'
    ),
    'SELECT 1',
    'ALTER TABLE `groupAssociation` ADD COLUMN `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY FIRST'
);
PREPARE fox_stmt FROM @fox_sql;
EXECUTE fox_stmt;
DEALLOCATE PREPARE fox_stmt;

SET @fox_sql = IF(
    EXISTS(
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'groupAssociation'
          AND COLUMN_NAME = 'groupName'
    ),
    'SELECT 1',
    'ALTER TABLE `groupAssociation` ADD COLUMN `groupName` VARCHAR(64) NOT NULL DEFAULT '''''
);
PREPARE fox_stmt FROM @fox_sql;
EXECUTE fox_stmt;
DEALLOCATE PREPARE fox_stmt;

SET @fox_sql = IF(
    EXISTS(
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'groupAssociation'
          AND COLUMN_NAME = 'groupColor'
    ),
    'SELECT 1',
    'ALTER TABLE `groupAssociation` ADD COLUMN `groupColor` VARCHAR(16) NOT NULL DEFAULT ''#ffffff'''
);
PREPARE fox_stmt FROM @fox_sql;
EXECUTE fox_stmt;
DEALLOCATE PREPARE fox_stmt;

SET @fox_sql = IF(
    EXISTS(
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'groupAssociation'
          AND COLUMN_NAME = 'groupNum'
    ),
    'SELECT 1',
    'ALTER TABLE `groupAssociation` ADD COLUMN `groupNum` INT UNSIGNED NOT NULL DEFAULT 5'
);
PREPARE fox_stmt FROM @fox_sql;
EXECUTE fox_stmt;
DEALLOCATE PREPARE fox_stmt;

SET @fox_sql = IF(
    EXISTS(
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'groupAssociation'
          AND COLUMN_NAME = 'groupType'
    ),
    'SELECT 1',
    'ALTER TABLE `groupAssociation` ADD COLUMN `groupType` VARCHAR(64) NOT NULL DEFAULT ''guest'''
);
PREPARE fox_stmt FROM @fox_sql;
EXECUTE fox_stmt;
DEALLOCATE PREPARE fox_stmt;

INSERT IGNORE INTO `groupAssociation` (`groupName`, `groupColor`, `groupNum`, `groupType`) VALUES
    ('Администраторы', '#e85d5d', 1, 'admin'),
    ('Игроки', '#5bd08b', 4, 'user'),
    ('Гости', '#ffffff', 5, 'guest'),
    ('Тестировщики', '#d6b35b', 6, 'tester');

CREATE TABLE IF NOT EXISTS `regCodes` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(64) NOT NULL,
    `code` VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    `groupNum` INT UNSIGNED NOT NULL DEFAULT 4,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_registration_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @fox_sql = IF(
    EXISTS(
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'regCodes'
          AND COLUMN_NAME = 'id'
    ),
    'SELECT 1',
    'ALTER TABLE `regCodes` ADD COLUMN `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY FIRST'
);
PREPARE fox_stmt FROM @fox_sql;
EXECUTE fox_stmt;
DEALLOCATE PREPARE fox_stmt;

SET @fox_sql = IF(
    EXISTS(
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'regCodes'
          AND COLUMN_NAME = 'name'
    ),
    'SELECT 1',
    'ALTER TABLE `regCodes` ADD COLUMN `name` VARCHAR(64) NOT NULL DEFAULT '''''
);
PREPARE fox_stmt FROM @fox_sql;
EXECUTE fox_stmt;
DEALLOCATE PREPARE fox_stmt;

SET @fox_sql = IF(
    EXISTS(
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'regCodes'
          AND COLUMN_NAME = 'code'
    ),
    'SELECT 1',
    'ALTER TABLE `regCodes` ADD COLUMN `code` VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT '''''
);
PREPARE fox_stmt FROM @fox_sql;
EXECUTE fox_stmt;
DEALLOCATE PREPARE fox_stmt;

SET @fox_sql = IF(
    EXISTS(
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'regCodes'
          AND COLUMN_NAME = 'groupNum'
    ),
    'SELECT 1',
    'ALTER TABLE `regCodes` ADD COLUMN `groupNum` INT UNSIGNED NOT NULL DEFAULT 4'
);
PREPARE fox_stmt FROM @fox_sql;
EXECUTE fox_stmt;
DEALLOCATE PREPARE fox_stmt;

CREATE TABLE IF NOT EXISTS `servers` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `serverName` VARCHAR(64) NOT NULL,
    `host` VARCHAR(255) NOT NULL DEFAULT '',
    `port` INT UNSIGNED NOT NULL DEFAULT 25565,
    `ignoreDirs` LONGTEXT NOT NULL DEFAULT '[]',
    `enabled` VARCHAR(5) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'false',
    `checkLib` VARCHAR(5) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'false',
    `serverGroups` VARCHAR(255) NOT NULL DEFAULT '4,5,6',
    `serverDescription` TEXT NULL,
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

SET @fox_sql = IF(
    EXISTS(
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'servers'
          AND COLUMN_NAME = 'id'
    ),
    'SELECT 1',
    'ALTER TABLE `servers` ADD COLUMN `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY FIRST'
);
PREPARE fox_stmt FROM @fox_sql;
EXECUTE fox_stmt;
DEALLOCATE PREPARE fox_stmt;

SET @fox_sql = IF(
    EXISTS(
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'servers'
          AND COLUMN_NAME = 'serverName'
    ),
    'SELECT 1',
    'ALTER TABLE `servers` ADD COLUMN `serverName` VARCHAR(64) NOT NULL DEFAULT '''''
);
PREPARE fox_stmt FROM @fox_sql;
EXECUTE fox_stmt;
DEALLOCATE PREPARE fox_stmt;

SET @fox_sql = IF(
    EXISTS(
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'servers'
          AND COLUMN_NAME = 'host'
    ),
    'SELECT 1',
    'ALTER TABLE `servers` ADD COLUMN `host` VARCHAR(255) NOT NULL DEFAULT '''''
);
PREPARE fox_stmt FROM @fox_sql;
EXECUTE fox_stmt;
DEALLOCATE PREPARE fox_stmt;

SET @fox_sql = IF(
    EXISTS(
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'servers'
          AND COLUMN_NAME = 'port'
    ),
    'SELECT 1',
    'ALTER TABLE `servers` ADD COLUMN `port` INT UNSIGNED NOT NULL DEFAULT 25565'
);
PREPARE fox_stmt FROM @fox_sql;
EXECUTE fox_stmt;
DEALLOCATE PREPARE fox_stmt;

SET @fox_sql = IF(
    EXISTS(
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'servers'
          AND COLUMN_NAME = 'ignoreDirs'
    ),
    'SELECT 1',
    'ALTER TABLE `servers` ADD COLUMN `ignoreDirs` LONGTEXT NOT NULL DEFAULT ''[]'''
);
PREPARE fox_stmt FROM @fox_sql;
EXECUTE fox_stmt;
DEALLOCATE PREPARE fox_stmt;

SET @fox_sql = IF(
    EXISTS(
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'servers'
          AND COLUMN_NAME = 'enabled'
    ),
    'SELECT 1',
    'ALTER TABLE `servers` ADD COLUMN `enabled` VARCHAR(5) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT ''false'''
);
PREPARE fox_stmt FROM @fox_sql;
EXECUTE fox_stmt;
DEALLOCATE PREPARE fox_stmt;

SET @fox_sql = IF(
    EXISTS(
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'servers'
          AND COLUMN_NAME = 'checkLib'
    ),
    'SELECT 1',
    'ALTER TABLE `servers` ADD COLUMN `checkLib` VARCHAR(5) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT ''false'''
);
PREPARE fox_stmt FROM @fox_sql;
EXECUTE fox_stmt;
DEALLOCATE PREPARE fox_stmt;

SET @fox_sql = IF(
    EXISTS(
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'servers'
          AND COLUMN_NAME = 'serverGroups'
    ),
    'SELECT 1',
    'ALTER TABLE `servers` ADD COLUMN `serverGroups` VARCHAR(255) NOT NULL DEFAULT ''4,5,6'''
);
PREPARE fox_stmt FROM @fox_sql;
EXECUTE fox_stmt;
DEALLOCATE PREPARE fox_stmt;

SET @fox_sql = IF(
    EXISTS(
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'servers'
          AND COLUMN_NAME = 'serverDescription'
    ),
    'SELECT 1',
    'ALTER TABLE `servers` ADD COLUMN `serverDescription` TEXT NULL'
);
PREPARE fox_stmt FROM @fox_sql;
EXECUTE fox_stmt;
DEALLOCATE PREPARE fox_stmt;

SET @fox_sql = IF(
    EXISTS(
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'servers'
          AND COLUMN_NAME = 'serverVersion'
    ),
    'SELECT 1',
    'ALTER TABLE `servers` ADD COLUMN `serverVersion` VARCHAR(64) NOT NULL DEFAULT '''''
);
PREPARE fox_stmt FROM @fox_sql;
EXECUTE fox_stmt;
DEALLOCATE PREPARE fox_stmt;

SET @fox_sql = IF(
    EXISTS(
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'servers'
          AND COLUMN_NAME = 'jreVersion'
    ),
    'SELECT 1',
    'ALTER TABLE `servers` ADD COLUMN `jreVersion` VARCHAR(64) NOT NULL DEFAULT '''''
);
PREPARE fox_stmt FROM @fox_sql;
EXECUTE fox_stmt;
DEALLOCATE PREPARE fox_stmt;

SET @fox_sql = IF(
    EXISTS(
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'servers'
          AND COLUMN_NAME = 'serverImage'
    ),
    'SELECT 1',
    'ALTER TABLE `servers` ADD COLUMN `serverImage` VARCHAR(512) NOT NULL DEFAULT '''''
);
PREPARE fox_stmt FROM @fox_sql;
EXECUTE fox_stmt;
DEALLOCATE PREPARE fox_stmt;

SET @fox_sql = IF(
    EXISTS(
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'servers'
          AND COLUMN_NAME = 'modsInfo'
    ),
    'SELECT 1',
    'ALTER TABLE `servers` ADD COLUMN `modsInfo` LONGTEXT NOT NULL DEFAULT ''[]'''
);
PREPARE fox_stmt FROM @fox_sql;
EXECUTE fox_stmt;
DEALLOCATE PREPARE fox_stmt;

SET @fox_sql = IF(
    EXISTS(
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'servers'
          AND COLUMN_NAME = 'mainClass'
    ),
    'SELECT 1',
    'ALTER TABLE `servers` ADD COLUMN `mainClass` VARCHAR(255) NOT NULL DEFAULT '''''
);
PREPARE fox_stmt FROM @fox_sql;
EXECUTE fox_stmt;
DEALLOCATE PREPARE fox_stmt;

SET @fox_sql = IF(
    EXISTS(
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'servers'
          AND COLUMN_NAME = 'forgeVersion'
    ),
    'SELECT 1',
    'ALTER TABLE `servers` ADD COLUMN `forgeVersion` VARCHAR(64) NOT NULL DEFAULT '''''
);
PREPARE fox_stmt FROM @fox_sql;
EXECUTE fox_stmt;
DEALLOCATE PREPARE fox_stmt;

SET @fox_sql = IF(
    EXISTS(
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'servers'
          AND COLUMN_NAME = 'client'
    ),
    'SELECT 1',
    'ALTER TABLE `servers` ADD COLUMN `client` VARCHAR(64) NOT NULL DEFAULT '''''
);
PREPARE fox_stmt FROM @fox_sql;
EXECUTE fox_stmt;
DEALLOCATE PREPARE fox_stmt;

SET @fox_sql = IF(
    EXISTS(
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'servers'
          AND COLUMN_NAME = 'mcpVersion'
    ),
    'SELECT 1',
    'ALTER TABLE `servers` ADD COLUMN `mcpVersion` VARCHAR(64) NOT NULL DEFAULT '''''
);
PREPARE fox_stmt FROM @fox_sql;
EXECUTE fox_stmt;
DEALLOCATE PREPARE fox_stmt;

SET @fox_sql = IF(
    EXISTS(
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'servers'
          AND COLUMN_NAME = 'forgeGroup'
    ),
    'SELECT 1',
    'ALTER TABLE `servers` ADD COLUMN `forgeGroup` VARCHAR(128) NOT NULL DEFAULT '''''
);
PREPARE fox_stmt FROM @fox_sql;
EXECUTE fox_stmt;
DEALLOCATE PREPARE fox_stmt;

SET @fox_sql = IF(
    EXISTS(
        SELECT 1 FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'servers'
          AND COLUMN_NAME = 'serverName'
          AND NON_UNIQUE = 0
    ),
    'SELECT 1',
    'ALTER TABLE `servers` ADD UNIQUE KEY `uq_server_name` (`serverName`)'
);
PREPARE fox_stmt FROM @fox_sql;
EXECUTE fox_stmt;
DEALLOCATE PREPARE fox_stmt;

CREATE TABLE IF NOT EXISTS `infobox` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `group_name` VARCHAR(64) NOT NULL,
    `start_timestamp` BIGINT UNSIGNED NOT NULL DEFAULT 0,
    `end_timestamp` BIGINT UNSIGNED NOT NULL DEFAULT 0,
    `title` VARCHAR(255) NOT NULL DEFAULT '',
    `text` TEXT NULL,
    `image` VARCHAR(512) NOT NULL DEFAULT '',
    `button_text` VARCHAR(128) NOT NULL DEFAULT '',
    `button_url` VARCHAR(512) NOT NULL DEFAULT '',
    PRIMARY KEY (`id`),
    KEY `ix_infobox_group` (`group_name`),
    KEY `ix_infobox_window` (`start_timestamp`, `end_timestamp`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @fox_sql = IF(
    EXISTS(
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'infobox'
          AND COLUMN_NAME = 'id'
    ),
    'SELECT 1',
    'ALTER TABLE `infobox` ADD COLUMN `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY FIRST'
);
PREPARE fox_stmt FROM @fox_sql;
EXECUTE fox_stmt;
DEALLOCATE PREPARE fox_stmt;

SET @fox_sql = IF(
    EXISTS(
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'infobox'
          AND COLUMN_NAME = 'group_name'
    ),
    'SELECT 1',
    'ALTER TABLE `infobox` ADD COLUMN `group_name` VARCHAR(64) NOT NULL DEFAULT '''''
);
PREPARE fox_stmt FROM @fox_sql;
EXECUTE fox_stmt;
DEALLOCATE PREPARE fox_stmt;

SET @fox_sql = IF(
    EXISTS(
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'infobox'
          AND COLUMN_NAME = 'start_timestamp'
    ),
    'SELECT 1',
    'ALTER TABLE `infobox` ADD COLUMN `start_timestamp` BIGINT UNSIGNED NOT NULL DEFAULT 0'
);
PREPARE fox_stmt FROM @fox_sql;
EXECUTE fox_stmt;
DEALLOCATE PREPARE fox_stmt;

SET @fox_sql = IF(
    EXISTS(
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'infobox'
          AND COLUMN_NAME = 'end_timestamp'
    ),
    'SELECT 1',
    'ALTER TABLE `infobox` ADD COLUMN `end_timestamp` BIGINT UNSIGNED NOT NULL DEFAULT 0'
);
PREPARE fox_stmt FROM @fox_sql;
EXECUTE fox_stmt;
DEALLOCATE PREPARE fox_stmt;

SET @fox_sql = IF(
    EXISTS(
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'infobox'
          AND COLUMN_NAME = 'title'
    ),
    'SELECT 1',
    'ALTER TABLE `infobox` ADD COLUMN `title` VARCHAR(255) NOT NULL DEFAULT '''''
);
PREPARE fox_stmt FROM @fox_sql;
EXECUTE fox_stmt;
DEALLOCATE PREPARE fox_stmt;

SET @fox_sql = IF(
    EXISTS(
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'infobox'
          AND COLUMN_NAME = 'text'
    ),
    'SELECT 1',
    'ALTER TABLE `infobox` ADD COLUMN `text` TEXT NULL'
);
PREPARE fox_stmt FROM @fox_sql;
EXECUTE fox_stmt;
DEALLOCATE PREPARE fox_stmt;

SET @fox_sql = IF(
    EXISTS(
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'infobox'
          AND COLUMN_NAME = 'image'
    ),
    'SELECT 1',
    'ALTER TABLE `infobox` ADD COLUMN `image` VARCHAR(512) NOT NULL DEFAULT '''''
);
PREPARE fox_stmt FROM @fox_sql;
EXECUTE fox_stmt;
DEALLOCATE PREPARE fox_stmt;

SET @fox_sql = IF(
    EXISTS(
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'infobox'
          AND COLUMN_NAME = 'button_text'
    ),
    'SELECT 1',
    'ALTER TABLE `infobox` ADD COLUMN `button_text` VARCHAR(128) NOT NULL DEFAULT '''''
);
PREPARE fox_stmt FROM @fox_sql;
EXECUTE fox_stmt;
DEALLOCATE PREPARE fox_stmt;

SET @fox_sql = IF(
    EXISTS(
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'infobox'
          AND COLUMN_NAME = 'button_url'
    ),
    'SELECT 1',
    'ALTER TABLE `infobox` ADD COLUMN `button_url` VARCHAR(512) NOT NULL DEFAULT '''''
);
PREPARE fox_stmt FROM @fox_sql;
EXECUTE fox_stmt;
DEALLOCATE PREPARE fox_stmt;

CREATE TABLE IF NOT EXISTS `badgesList` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `badgeName` VARCHAR(64) NOT NULL,
    `description` VARCHAR(512) NOT NULL DEFAULT '',
    `img` VARCHAR(512) NOT NULL DEFAULT '',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_badge_name` (`badgeName`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @fox_sql = IF(
    EXISTS(
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'badgesList'
          AND COLUMN_NAME = 'id'
    ),
    'SELECT 1',
    'ALTER TABLE `badgesList` ADD COLUMN `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY FIRST'
);
PREPARE fox_stmt FROM @fox_sql;
EXECUTE fox_stmt;
DEALLOCATE PREPARE fox_stmt;

SET @fox_sql = IF(
    EXISTS(
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'badgesList'
          AND COLUMN_NAME = 'badgeName'
    ),
    'SELECT 1',
    'ALTER TABLE `badgesList` ADD COLUMN `badgeName` VARCHAR(64) NOT NULL DEFAULT '''''
);
PREPARE fox_stmt FROM @fox_sql;
EXECUTE fox_stmt;
DEALLOCATE PREPARE fox_stmt;

SET @fox_sql = IF(
    EXISTS(
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'badgesList'
          AND COLUMN_NAME = 'description'
    ),
    'SELECT 1',
    'ALTER TABLE `badgesList` ADD COLUMN `description` VARCHAR(512) NOT NULL DEFAULT '''''
);
PREPARE fox_stmt FROM @fox_sql;
EXECUTE fox_stmt;
DEALLOCATE PREPARE fox_stmt;

SET @fox_sql = IF(
    EXISTS(
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'badgesList'
          AND COLUMN_NAME = 'img'
    ),
    'SELECT 1',
    'ALTER TABLE `badgesList` ADD COLUMN `img` VARCHAR(512) NOT NULL DEFAULT '''''
);
PREPARE fox_stmt FROM @fox_sql;
EXECUTE fox_stmt;
DEALLOCATE PREPARE fox_stmt;

CREATE TABLE IF NOT EXISTS `antiBrute` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `time` BIGINT UNSIGNED NULL DEFAULT NULL,
    `recordTime` DATETIME(4) NOT NULL DEFAULT CURRENT_TIMESTAMP(4),
    `ip` VARCHAR(45) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    `attempts` INT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_anti_brute_ip` (`ip`),
    KEY `idx_anti_brute_blocked_until` (`time`),
    KEY `idx_anti_brute_record_time` (`recordTime`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @fox_sql = IF(
    EXISTS(
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'antiBrute'
          AND COLUMN_NAME = 'id'
    ),
    'SELECT 1',
    'ALTER TABLE `antiBrute` ADD COLUMN `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY FIRST'
);
PREPARE fox_stmt FROM @fox_sql;
EXECUTE fox_stmt;
DEALLOCATE PREPARE fox_stmt;

SET @fox_sql = IF(
    EXISTS(
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'antiBrute'
          AND COLUMN_NAME = 'time'
    ),
    'SELECT 1',
    'ALTER TABLE `antiBrute` ADD COLUMN `time` BIGINT UNSIGNED NULL DEFAULT NULL'
);
PREPARE fox_stmt FROM @fox_sql;
EXECUTE fox_stmt;
DEALLOCATE PREPARE fox_stmt;

SET @fox_sql = IF(
    EXISTS(
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'antiBrute'
          AND COLUMN_NAME = 'recordTime'
    ),
    'SELECT 1',
    'ALTER TABLE `antiBrute` ADD COLUMN `recordTime` DATETIME(4) NOT NULL DEFAULT CURRENT_TIMESTAMP(4)'
);
PREPARE fox_stmt FROM @fox_sql;
EXECUTE fox_stmt;
DEALLOCATE PREPARE fox_stmt;

SET @fox_sql = IF(
    EXISTS(
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'antiBrute'
          AND COLUMN_NAME = 'ip'
    ),
    'SELECT 1',
    'ALTER TABLE `antiBrute` ADD COLUMN `ip` VARCHAR(45) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT '''''
);
PREPARE fox_stmt FROM @fox_sql;
EXECUTE fox_stmt;
DEALLOCATE PREPARE fox_stmt;

SET @fox_sql = IF(
    EXISTS(
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'antiBrute'
          AND COLUMN_NAME = 'attempts'
    ),
    'SELECT 1',
    'ALTER TABLE `antiBrute` ADD COLUMN `attempts` INT UNSIGNED NOT NULL DEFAULT 0'
);
PREPARE fox_stmt FROM @fox_sql;
EXECUTE fox_stmt;
DEALLOCATE PREPARE fox_stmt;

CREATE TABLE IF NOT EXISTS `usersession` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `userUuid` CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    `serverId` VARCHAR(41) CHARACTER SET ascii COLLATE ascii_bin NULL,
    `accessToken` CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    `expiresAt` BIGINT UNSIGNED NOT NULL DEFAULT 0,
    `updatedAt` DATETIME(4) NOT NULL DEFAULT CURRENT_TIMESTAMP(4) ON UPDATE CURRENT_TIMESTAMP(4),
    PRIMARY KEY (`id`),
    UNIQUE KEY `ux_usersession_user_uuid` (`userUuid`),
    KEY `ix_usersession_access_token` (`accessToken`),
    CONSTRAINT `fk_usersession_user_uuid` FOREIGN KEY (`userUuid`) REFERENCES `users` (`uuid`) ON UPDATE RESTRICT ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @fox_sql = IF(
    EXISTS(
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'usersession'
          AND COLUMN_NAME = 'id'
    ),
    'SELECT 1',
    'ALTER TABLE `usersession` ADD COLUMN `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY FIRST'
);
PREPARE fox_stmt FROM @fox_sql;
EXECUTE fox_stmt;
DEALLOCATE PREPARE fox_stmt;

SET @fox_sql = IF(
    EXISTS(
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'usersession'
          AND COLUMN_NAME = 'userUuid'
    ),
    'SELECT 1',
    'ALTER TABLE `usersession` ADD COLUMN `userUuid` CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NULL AFTER `id`'
);
PREPARE fox_stmt FROM @fox_sql;
EXECUTE fox_stmt;
DEALLOCATE PREPARE fox_stmt;

SET @fox_sql = IF(
    EXISTS(
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'usersession'
          AND COLUMN_NAME = 'serverId'
    ),
    'SELECT 1',
    'ALTER TABLE `usersession` ADD COLUMN `serverId` VARCHAR(41) CHARACTER SET ascii COLLATE ascii_bin NULL'
);
PREPARE fox_stmt FROM @fox_sql;
EXECUTE fox_stmt;
DEALLOCATE PREPARE fox_stmt;

SET @fox_sql = IF(
    EXISTS(
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'usersession'
          AND COLUMN_NAME = 'accessToken'
    ),
    'SELECT 1',
    'ALTER TABLE `usersession` ADD COLUMN `accessToken` CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT '''''
);
PREPARE fox_stmt FROM @fox_sql;
EXECUTE fox_stmt;
DEALLOCATE PREPARE fox_stmt;

SET @fox_sql = IF(
    EXISTS(
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'usersession'
          AND COLUMN_NAME = 'expiresAt'
    ),
    'SELECT 1',
    'ALTER TABLE `usersession` ADD COLUMN `expiresAt` BIGINT UNSIGNED NOT NULL DEFAULT 0'
);
PREPARE fox_stmt FROM @fox_sql;
EXECUTE fox_stmt;
DEALLOCATE PREPARE fox_stmt;

SET @fox_sql = IF(
    EXISTS(
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'usersession'
          AND COLUMN_NAME = 'updatedAt'
    ),
    'SELECT 1',
    'ALTER TABLE `usersession` ADD COLUMN `updatedAt` DATETIME(4) NOT NULL DEFAULT CURRENT_TIMESTAMP(4) ON UPDATE CURRENT_TIMESTAMP(4)'
);
PREPARE fox_stmt FROM @fox_sql;
EXECUTE fox_stmt;
DEALLOCATE PREPARE fox_stmt;

SET @fox_sql = IF(
    EXISTS(
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'usersession'
          AND COLUMN_NAME = 'user'
    ),
    'UPDATE `usersession` AS `session` INNER JOIN `users` AS `user` ON `user`.`login` = `session`.`user` SET `session`.`userUuid` = `user`.`uuid` WHERE `session`.`userUuid` IS NULL',
    'SELECT 1'
);
PREPARE fox_stmt FROM @fox_sql;
EXECUTE fox_stmt;
DEALLOCATE PREPARE fox_stmt;

DELETE FROM `usersession` WHERE `userUuid` IS NULL;
UPDATE `usersession` SET `accessToken` = SHA2(`accessToken`, 256) WHERE CHAR_LENGTH(`accessToken`) <> 64;
ALTER TABLE `usersession`
    MODIFY COLUMN `userUuid` CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    MODIFY COLUMN `accessToken` CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL;

SET @fox_sql = IF(
    EXISTS(
        SELECT 1 FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'usersession'
          AND COLUMN_NAME = 'userUuid'
          AND NON_UNIQUE = 0
    ),
    'SELECT 1',
    'ALTER TABLE `usersession` ADD UNIQUE KEY `ux_usersession_user_uuid` (`userUuid`)'
);
PREPARE fox_stmt FROM @fox_sql;
EXECUTE fox_stmt;
DEALLOCATE PREPARE fox_stmt;

SET @fox_sql = IF(
    EXISTS(
        SELECT 1 FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'usersession'
          AND INDEX_NAME = 'ix_usersession_access_token'
    ),
    'SELECT 1',
    'ALTER TABLE `usersession` ADD KEY `ix_usersession_access_token` (`accessToken`)'
);
PREPARE fox_stmt FROM @fox_sql;
EXECUTE fox_stmt;
DEALLOCATE PREPARE fox_stmt;

CREATE TABLE IF NOT EXISTS `password_reset_tokens` (
    `userUuid` CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    `tokenHash` CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    `expiresAt` BIGINT UNSIGNED NOT NULL,
    `createdAt` DATETIME(4) NOT NULL DEFAULT CURRENT_TIMESTAMP(4),
    PRIMARY KEY (`userUuid`),
    UNIQUE KEY `ux_password_reset_token_hash` (`tokenHash`),
    KEY `ix_password_reset_expires_at` (`expiresAt`),
    CONSTRAINT `fk_password_reset_user_uuid` FOREIGN KEY (`userUuid`) REFERENCES `users` (`uuid`) ON UPDATE RESTRICT ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @fox_sql = IF(
    EXISTS(
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'password_reset_tokens'
          AND COLUMN_NAME = 'userUuid'
    ),
    'SELECT 1',
    'ALTER TABLE `password_reset_tokens` ADD COLUMN `userUuid` CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT '''''
);
PREPARE fox_stmt FROM @fox_sql;
EXECUTE fox_stmt;
DEALLOCATE PREPARE fox_stmt;

SET @fox_sql = IF(
    EXISTS(
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'password_reset_tokens'
          AND COLUMN_NAME = 'tokenHash'
    ),
    'SELECT 1',
    'ALTER TABLE `password_reset_tokens` ADD COLUMN `tokenHash` CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT '''''
);
PREPARE fox_stmt FROM @fox_sql;
EXECUTE fox_stmt;
DEALLOCATE PREPARE fox_stmt;

SET @fox_sql = IF(
    EXISTS(
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'password_reset_tokens'
          AND COLUMN_NAME = 'expiresAt'
    ),
    'SELECT 1',
    'ALTER TABLE `password_reset_tokens` ADD COLUMN `expiresAt` BIGINT UNSIGNED NOT NULL DEFAULT 0'
);
PREPARE fox_stmt FROM @fox_sql;
EXECUTE fox_stmt;
DEALLOCATE PREPARE fox_stmt;

SET @fox_sql = IF(
    EXISTS(
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'password_reset_tokens'
          AND COLUMN_NAME = 'createdAt'
    ),
    'SELECT 1',
    'ALTER TABLE `password_reset_tokens` ADD COLUMN `createdAt` DATETIME(4) NOT NULL DEFAULT CURRENT_TIMESTAMP(4)'
);
PREPARE fox_stmt FROM @fox_sql;
EXECUTE fox_stmt;
DEALLOCATE PREPARE fox_stmt;

CREATE TABLE IF NOT EXISTS `user_hardware_reports` (
    `userUuid` CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    `cpuIdHash` CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL,
    `cpu` VARCHAR(255) NOT NULL DEFAULT '',
    `gpus` JSON NOT NULL,
    `payload` JSON NOT NULL,
    `updatedAt` DATETIME(4) NOT NULL DEFAULT CURRENT_TIMESTAMP(4) ON UPDATE CURRENT_TIMESTAMP(4),
    PRIMARY KEY (`userUuid`),
    KEY `ix_user_hardware_cpu_id_hash` (`cpuIdHash`),
    CONSTRAINT `fk_user_hardware_user_uuid` FOREIGN KEY (`userUuid`) REFERENCES `users` (`uuid`) ON UPDATE RESTRICT ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @fox_sql = IF(
    EXISTS(
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'user_hardware_reports'
          AND COLUMN_NAME = 'userUuid'
    ),
    'SELECT 1',
    'ALTER TABLE `user_hardware_reports` ADD COLUMN `userUuid` CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT '''''
);
PREPARE fox_stmt FROM @fox_sql;
EXECUTE fox_stmt;
DEALLOCATE PREPARE fox_stmt;

SET @fox_sql = IF(
    EXISTS(
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'user_hardware_reports'
          AND COLUMN_NAME = 'cpuIdHash'
    ),
    'SELECT 1',
    'ALTER TABLE `user_hardware_reports` ADD COLUMN `cpuIdHash` CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL'
);
PREPARE fox_stmt FROM @fox_sql;
EXECUTE fox_stmt;
DEALLOCATE PREPARE fox_stmt;

SET @fox_sql = IF(
    EXISTS(
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'user_hardware_reports'
          AND COLUMN_NAME = 'cpu'
    ),
    'SELECT 1',
    'ALTER TABLE `user_hardware_reports` ADD COLUMN `cpu` VARCHAR(255) NOT NULL DEFAULT '''''
);
PREPARE fox_stmt FROM @fox_sql;
EXECUTE fox_stmt;
DEALLOCATE PREPARE fox_stmt;

SET @fox_sql = IF(
    EXISTS(
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'user_hardware_reports'
          AND COLUMN_NAME = 'gpus'
    ),
    'SELECT 1',
    'ALTER TABLE `user_hardware_reports` ADD COLUMN `gpus` JSON NULL'
);
PREPARE fox_stmt FROM @fox_sql;
EXECUTE fox_stmt;
DEALLOCATE PREPARE fox_stmt;

SET @fox_sql = IF(
    EXISTS(
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'user_hardware_reports'
          AND COLUMN_NAME = 'payload'
    ),
    'SELECT 1',
    'ALTER TABLE `user_hardware_reports` ADD COLUMN `payload` JSON NULL'
);
PREPARE fox_stmt FROM @fox_sql;
EXECUTE fox_stmt;
DEALLOCATE PREPARE fox_stmt;

SET @fox_sql = IF(
    EXISTS(
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'user_hardware_reports'
          AND COLUMN_NAME = 'updatedAt'
    ),
    'SELECT 1',
    'ALTER TABLE `user_hardware_reports` ADD COLUMN `updatedAt` DATETIME(4) NOT NULL DEFAULT CURRENT_TIMESTAMP(4) ON UPDATE CURRENT_TIMESTAMP(4)'
);
PREPARE fox_stmt FROM @fox_sql;
EXECUTE fox_stmt;
DEALLOCATE PREPARE fox_stmt;

CREATE TABLE IF NOT EXISTS `userBadges` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `userUuid` CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    `badges` LONGTEXT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `ux_user_badges_user_uuid` (`userUuid`),
    CONSTRAINT `fk_user_badges_user_uuid` FOREIGN KEY (`userUuid`) REFERENCES `users` (`uuid`) ON UPDATE RESTRICT ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @fox_sql = IF(
    EXISTS(
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'userBadges'
          AND COLUMN_NAME = 'id'
    ),
    'SELECT 1',
    'ALTER TABLE `userBadges` ADD COLUMN `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY FIRST'
);
PREPARE fox_stmt FROM @fox_sql;
EXECUTE fox_stmt;
DEALLOCATE PREPARE fox_stmt;

SET @fox_sql = IF(
    EXISTS(
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'userBadges'
          AND COLUMN_NAME = 'userUuid'
    ),
    'SELECT 1',
    'ALTER TABLE `userBadges` ADD COLUMN `userUuid` CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NULL AFTER `id`'
);
PREPARE fox_stmt FROM @fox_sql;
EXECUTE fox_stmt;
DEALLOCATE PREPARE fox_stmt;

SET @fox_sql = IF(
    EXISTS(
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'userBadges'
          AND COLUMN_NAME = 'badges'
    ),
    'SELECT 1',
    'ALTER TABLE `userBadges` ADD COLUMN `badges` LONGTEXT NULL'
);
PREPARE fox_stmt FROM @fox_sql;
EXECUTE fox_stmt;
DEALLOCATE PREPARE fox_stmt;

SET @fox_sql = IF(
    EXISTS(
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'userBadges'
          AND COLUMN_NAME = 'userLogin'
    ),
    'UPDATE `userBadges` AS `assignment` INNER JOIN `users` AS `user` ON `user`.`login` = `assignment`.`userLogin` SET `assignment`.`userUuid` = `user`.`uuid` WHERE `assignment`.`userUuid` IS NULL',
    'SELECT 1'
);
PREPARE fox_stmt FROM @fox_sql;
EXECUTE fox_stmt;
DEALLOCATE PREPARE fox_stmt;

DELETE FROM `userBadges` WHERE `userUuid` IS NULL;
ALTER TABLE `userBadges` MODIFY COLUMN `userUuid` CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL;

SET @fox_sql = IF(
    EXISTS(
        SELECT 1 FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'userBadges'
          AND COLUMN_NAME = 'userUuid'
          AND NON_UNIQUE = 0
    ),
    'SELECT 1',
    'ALTER TABLE `userBadges` ADD UNIQUE KEY `ux_user_badges_user_uuid` (`userUuid`)'
);
PREPARE fox_stmt FROM @fox_sql;
EXECUTE fox_stmt;
DEALLOCATE PREPARE fox_stmt;

SET FOREIGN_KEY_CHECKS = 1;

-- Critical compatibility assertion. This deliberately fails the migration if the
-- column that caused the production HTTP 500 is still unavailable.
SELECT `balance`, `badges`, `serversOnline` FROM `users` LIMIT 0;
