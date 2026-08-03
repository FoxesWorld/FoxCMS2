-- FoxCMS migration 005 preflight: make legacy group tags safe before migration 006.
-- groupType is not unique in historical installations; multiple administrative
-- ranks may all contain "admin" and must not collapse into one runtime identity.

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

SET @fox_sql = IF(
    EXISTS(
        SELECT 1 FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'groupAssociation'
          AND INDEX_NAME = 'uq_group_tag'
    ),
    'ALTER TABLE `groupAssociation` DROP INDEX `uq_group_tag`',
    'SELECT 1'
);
PREPARE fox_stmt FROM @fox_sql;
EXECUTE fox_stmt;
DEALLOCATE PREPARE fox_stmt;

UPDATE `groupAssociation`
SET `groupTag` = CASE
    WHEN `groupNum` = 1 THEN 'admin'
    WHEN `groupNum` = 4 THEN 'user'
    WHEN `groupNum` = 5 THEN 'guest'
    WHEN `groupNum` = 6 THEN 'tester'
    WHEN `groupTag` IS NULL
      OR TRIM(`groupTag`) = ''
      OR LOWER(TRIM(`groupTag`)) IN ('admin', 'user', 'guest', 'tester')
      OR LOWER(TRIM(`groupTag`)) NOT REGEXP '^[a-z][a-z0-9_-]{0,63}$'
        THEN CONCAT('group-', `groupNum`)
    ELSE LOWER(TRIM(`groupTag`))
END;

UPDATE `groupAssociation` AS `target`
INNER JOIN (
    SELECT `groupTag`, MIN(`id`) AS `keeper_id`
    FROM `groupAssociation`
    GROUP BY `groupTag`
    HAVING COUNT(*) > 1
) AS `duplicate`
    ON `duplicate`.`groupTag` = `target`.`groupTag`
   AND `duplicate`.`keeper_id` <> `target`.`id`
SET `target`.`groupTag` = CONCAT(
    'legacy-',
    `target`.`id`,
    '-',
    LEFT(SHA2(CONCAT(`target`.`id`, ':', `target`.`groupNum`, ':', `target`.`groupName`), 256), 12)
);

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
