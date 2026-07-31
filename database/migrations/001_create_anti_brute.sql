-- FoxCMS migration 001: anti-brute-force state
-- Run with a database schema-owner/administrator account, never the web runtime account.
-- Select the FoxCMS database before applying this file.

CREATE TABLE IF NOT EXISTS `antiBrute` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `time` BIGINT UNSIGNED NULL DEFAULT NULL COMMENT 'Unix timestamp until authentication is allowed again',
    `recordTime` DATETIME(4) NOT NULL DEFAULT CURRENT_TIMESTAMP(4),
    `ip` VARCHAR(45) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    `attempts` INT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_anti_brute_ip` (`ip`),
    KEY `idx_anti_brute_blocked_until` (`time`),
    KEY `idx_anti_brute_record_time` (`recordTime`)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;

-- Grant DML separately when required. Example for this deployment:
-- GRANT SELECT, INSERT, UPDATE, DELETE
--     ON `foxescraft`.`antiBrute`
--     TO 'foxesworld'@'localhost';
