-- FoxCMS migration 017: mark selected badge claim keys as public claim offers.
-- Public offers authorize the generic claimBadge action to generate and consume a
-- separate one-time key. The public offer stores only an internal SHA-256 digest;
-- no reusable plaintext claim code is exposed to the browser.

SET @fox_sql = IF(
    NOT EXISTS(
        SELECT 1
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'badgeClaimKeys'
          AND COLUMN_NAME = 'accessMode'
    ),
    "ALTER TABLE `badgeClaimKeys` ADD COLUMN `accessMode` VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'code' AFTER `usageMode`",
    'SELECT 1'
);
PREPARE fox_stmt FROM @fox_sql;
EXECUTE fox_stmt;
DEALLOCATE PREPARE fox_stmt;

INSERT INTO `badgeClaimKeys`
    (`badgeId`, `tokenHash`, `tokenHint`, `usageMode`, `accessMode`, `usesCount`, `enabled`, `createdAt`, `updatedAt`, `createdByUuid`)
SELECT
    `id`,
    'e959a5af7ab0d7307f3881221b2e4fb96a236509df791073c8b7a5fddcb15e57',
    'VpS-gZAlB8',
    'reusable',
    'public',
    0,
    1,
    UNIX_TIMESTAMP(),
    UNIX_TIMESTAMP(),
    NULL
FROM `badgesList`
WHERE `badgeName` = 'Знаток правил'
ON DUPLICATE KEY UPDATE
    `badgeId` = VALUES(`badgeId`),
    `tokenHint` = VALUES(`tokenHint`),
    `usageMode` = 'reusable',
    `accessMode` = 'public',
    `enabled` = 1,
    `updatedAt` = VALUES(`updatedAt`);

SELECT `key`.`id`, `key`.`badgeId`, `key`.`tokenHint`, `key`.`usageMode`, `key`.`accessMode`, `key`.`enabled`
FROM `badgeClaimKeys` AS `key`
INNER JOIN `badgesList` AS `badge` ON `badge`.`id` = `key`.`badgeId`
WHERE `key`.`tokenHash` = 'e959a5af7ab0d7307f3881221b2e4fb96a236509df791073c8b7a5fddcb15e57'
  AND `badge`.`badgeName` = 'Знаток правил'
LIMIT 1;
