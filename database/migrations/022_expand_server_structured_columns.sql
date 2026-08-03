-- FoxCMS migration 022: expand structured server configuration columns.
-- Legacy databases may store JSON arrays in narrow VARCHAR columns.
-- These fields are canonical JSON text and must not be constrained by legacy lengths.

SET @fox_sql = CASE
    WHEN NOT EXISTS(
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'servers'
          AND COLUMN_NAME = 'serverGroups'
    ) THEN
        "ALTER TABLE `servers` ADD COLUMN `serverGroups` LONGTEXT NULL"
    WHEN EXISTS(
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'servers'
          AND COLUMN_NAME = 'serverGroups'
          AND (
              DATA_TYPE <> 'longtext'
              OR COALESCE(CHARACTER_MAXIMUM_LENGTH, 0) < 65535
          )
    ) THEN
        "ALTER TABLE `servers` MODIFY COLUMN `serverGroups` LONGTEXT NULL"
    ELSE
        'SELECT 1'
END;
PREPARE fox_stmt FROM @fox_sql;
EXECUTE fox_stmt;
DEALLOCATE PREPARE fox_stmt;

UPDATE `servers` SET `serverGroups` = '[]' WHERE `serverGroups` IS NULL;

SET @fox_sql = IF(
    EXISTS(
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'servers'
          AND COLUMN_NAME = 'serverGroups'
          AND (IS_NULLABLE = 'YES' OR COLUMN_DEFAULT IS NULL)
    ),
    "ALTER TABLE `servers` MODIFY COLUMN `serverGroups` LONGTEXT NOT NULL DEFAULT '[]'",
    'SELECT 1'
);
PREPARE fox_stmt FROM @fox_sql;
EXECUTE fox_stmt;
DEALLOCATE PREPARE fox_stmt;

SET @fox_sql = CASE
    WHEN NOT EXISTS(
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'servers'
          AND COLUMN_NAME = 'ignoreDirs'
    ) THEN
        "ALTER TABLE `servers` ADD COLUMN `ignoreDirs` LONGTEXT NULL"
    WHEN EXISTS(
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'servers'
          AND COLUMN_NAME = 'ignoreDirs'
          AND (
              DATA_TYPE <> 'longtext'
              OR COALESCE(CHARACTER_MAXIMUM_LENGTH, 0) < 65535
          )
    ) THEN
        "ALTER TABLE `servers` MODIFY COLUMN `ignoreDirs` LONGTEXT NULL"
    ELSE
        'SELECT 1'
END;
PREPARE fox_stmt FROM @fox_sql;
EXECUTE fox_stmt;
DEALLOCATE PREPARE fox_stmt;

UPDATE `servers` SET `ignoreDirs` = '[]' WHERE `ignoreDirs` IS NULL;

SET @fox_sql = IF(
    EXISTS(
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'servers'
          AND COLUMN_NAME = 'ignoreDirs'
          AND (IS_NULLABLE = 'YES' OR COLUMN_DEFAULT IS NULL)
    ),
    "ALTER TABLE `servers` MODIFY COLUMN `ignoreDirs` LONGTEXT NOT NULL DEFAULT '[]'",
    'SELECT 1'
);
PREPARE fox_stmt FROM @fox_sql;
EXECUTE fox_stmt;
DEALLOCATE PREPARE fox_stmt;

SET @fox_sql = CASE
    WHEN NOT EXISTS(
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'servers'
          AND COLUMN_NAME = 'modsInfo'
    ) THEN
        "ALTER TABLE `servers` ADD COLUMN `modsInfo` LONGTEXT NULL"
    WHEN EXISTS(
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'servers'
          AND COLUMN_NAME = 'modsInfo'
          AND (
              DATA_TYPE <> 'longtext'
              OR COALESCE(CHARACTER_MAXIMUM_LENGTH, 0) < 65535
          )
    ) THEN
        "ALTER TABLE `servers` MODIFY COLUMN `modsInfo` LONGTEXT NULL"
    ELSE
        'SELECT 1'
END;
PREPARE fox_stmt FROM @fox_sql;
EXECUTE fox_stmt;
DEALLOCATE PREPARE fox_stmt;

UPDATE `servers` SET `modsInfo` = '[]' WHERE `modsInfo` IS NULL;

SET @fox_sql = IF(
    EXISTS(
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'servers'
          AND COLUMN_NAME = 'modsInfo'
          AND (IS_NULLABLE = 'YES' OR COLUMN_DEFAULT IS NULL)
    ),
    "ALTER TABLE `servers` MODIFY COLUMN `modsInfo` LONGTEXT NOT NULL DEFAULT '[]'",
    'SELECT 1'
);
PREPARE fox_stmt FROM @fox_sql;
EXECUTE fox_stmt;
DEALLOCATE PREPARE fox_stmt;

SELECT `COLUMN_NAME`, `COLUMN_TYPE`, `CHARACTER_MAXIMUM_LENGTH`, `IS_NULLABLE`
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'servers'
  AND COLUMN_NAME IN ('serverGroups', 'ignoreDirs', 'modsInfo')
ORDER BY `COLUMN_NAME`;
