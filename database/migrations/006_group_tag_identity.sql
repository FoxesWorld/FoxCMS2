-- Stable group tags replace numeric group identifiers in all runtime contracts.
-- Numeric columns remain temporarily as legacy migration bridges only.

SET @fox_sql = IF(
    EXISTS(
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'groupAssociation'
          AND COLUMN_NAME = 'groupTag'
    ),
    'SELECT 1',
    'ALTER TABLE `groupAssociation` ADD COLUMN `groupTag` VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL AFTER `groupColor`'
);
PREPARE fox_stmt FROM @fox_sql;
EXECUTE fox_stmt;
DEALLOCATE PREPARE fox_stmt;

UPDATE `groupAssociation`
SET `groupTag` = LOWER(TRIM(`groupType`))
WHERE `groupTag` IS NULL OR TRIM(`groupTag`) = '';

UPDATE `groupAssociation`
SET `groupTag` = CONCAT('group-', `groupNum`)
WHERE `groupTag` IS NULL
   OR `groupTag` NOT REGEXP '^[a-z][a-z0-9_-]{0,63}$';

UPDATE `groupAssociation` AS `target`
INNER JOIN `groupAssociation` AS `keeper`
    ON `keeper`.`groupTag` = `target`.`groupTag`
   AND `keeper`.`groupNum` < `target`.`groupNum`
SET `target`.`groupTag` = CONCAT(LEFT(`target`.`groupTag`, 48), '-', `target`.`groupNum`);

ALTER TABLE `groupAssociation`
    MODIFY COLUMN `groupTag` VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL;

SET @fox_sql = IF(
    EXISTS(
        SELECT 1 FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'groupAssociation'
          AND INDEX_NAME = 'uq_group_tag'
    ),
    'SELECT 1',
    'ALTER TABLE `groupAssociation` ADD UNIQUE KEY `uq_group_tag` (`groupTag`)'
);
PREPARE fox_stmt FROM @fox_sql;
EXECUTE fox_stmt;
DEALLOCATE PREPARE fox_stmt;

SET @fox_sql = IF(
    EXISTS(
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'users'
          AND COLUMN_NAME = 'groupTag'
    ),
    'SELECT 1',
    'ALTER TABLE `users` ADD COLUMN `groupTag` VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL AFTER `email`'
);
PREPARE fox_stmt FROM @fox_sql;
EXECUTE fox_stmt;
DEALLOCATE PREPARE fox_stmt;

UPDATE `users` AS `user`
LEFT JOIN `groupAssociation` AS `groupRecord`
    ON `groupRecord`.`groupNum` = `user`.`user_group`
SET `user`.`groupTag` = COALESCE(`groupRecord`.`groupTag`, 'guest')
WHERE `user`.`groupTag` IS NULL OR TRIM(`user`.`groupTag`) = '';

ALTER TABLE `users`
    MODIFY COLUMN `groupTag` VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'user';

SET @fox_sql = IF(
    EXISTS(
        SELECT 1 FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'users'
          AND INDEX_NAME = 'ix_users_group_tag'
    ),
    'SELECT 1',
    'ALTER TABLE `users` ADD KEY `ix_users_group_tag` (`groupTag`)'
);
PREPARE fox_stmt FROM @fox_sql;
EXECUTE fox_stmt;
DEALLOCATE PREPARE fox_stmt;

SET @fox_sql = IF(
    EXISTS(
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'regCodes'
          AND COLUMN_NAME = 'groupTag'
    ),
    'SELECT 1',
    'ALTER TABLE `regCodes` ADD COLUMN `groupTag` VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL AFTER `code`'
);
PREPARE fox_stmt FROM @fox_sql;
EXECUTE fox_stmt;
DEALLOCATE PREPARE fox_stmt;

UPDATE `regCodes` AS `registrationCode`
LEFT JOIN `groupAssociation` AS `groupRecord`
    ON `groupRecord`.`groupNum` = `registrationCode`.`groupNum`
SET `registrationCode`.`groupTag` = COALESCE(`groupRecord`.`groupTag`, 'user')
WHERE `registrationCode`.`groupTag` IS NULL OR TRIM(`registrationCode`.`groupTag`) = '';

ALTER TABLE `regCodes`
    MODIFY COLUMN `groupTag` VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'user';

UPDATE `site_maintenance`
SET `allowedGroups` = '["admin"]'
WHERE `allowedGroups` = '[1]';
