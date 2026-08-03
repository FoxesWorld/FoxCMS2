-- FoxCMS migration 018: widen legacy server image references.
-- Older installations may already have servers.serverImage as VARCHAR(64).
-- Generated upload paths exceed that length, while the canonical schema uses VARCHAR(512).

SET @fox_sql = CASE
    WHEN NOT EXISTS(
        SELECT 1
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'servers'
          AND COLUMN_NAME = 'serverImage'
    ) THEN
        "ALTER TABLE `servers` ADD COLUMN `serverImage` VARCHAR(512) NOT NULL DEFAULT '' AFTER `jreVersion`"
    WHEN EXISTS(
        SELECT 1
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'servers'
          AND COLUMN_NAME = 'serverImage'
          AND DATA_TYPE IN ('char', 'varchar', 'tinytext')
          AND COALESCE(CHARACTER_MAXIMUM_LENGTH, 0) < 512
    ) THEN
        "ALTER TABLE `servers` MODIFY COLUMN `serverImage` VARCHAR(512) NOT NULL DEFAULT ''"
    ELSE
        'SELECT 1'
END;
PREPARE fox_stmt FROM @fox_sql;
EXECUTE fox_stmt;
DEALLOCATE PREPARE fox_stmt;

SELECT `COLUMN_NAME`, `COLUMN_TYPE`, `CHARACTER_MAXIMUM_LENGTH`, `IS_NULLABLE`
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'servers'
  AND COLUMN_NAME = 'serverImage';
