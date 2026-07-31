-- FoxCMS migration 005: enforce the profile/runtime fields required by the modern API.
-- This migration is intentionally idempotent because production databases may have a
-- partially applied legacy repair while migration 004 is already recorded.

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

UPDATE `users`
SET `balance` = '[]'
WHERE `balance` IS NULL OR TRIM(`balance`) = '';

UPDATE `users`
SET `badges` = '[]'
WHERE `badges` IS NULL OR TRIM(`badges`) = '';

UPDATE `users`
SET `serversOnline` = '{}'
WHERE `serversOnline` IS NULL OR TRIM(`serversOnline`) = '';

ALTER TABLE `users`
    MODIFY COLUMN `balance` LONGTEXT NOT NULL DEFAULT '[]',
    MODIFY COLUMN `badges` LONGTEXT NOT NULL DEFAULT '[]',
    MODIFY COLUMN `serversOnline` LONGTEXT NOT NULL DEFAULT '{}';

-- Fail before the migration is recorded if the runtime profile contract is still invalid.
SELECT `balance`, `badges`, `serversOnline` FROM `users` LIMIT 0;
