-- FoxCMS migration 019: badge-owned rewards and cryptographic public placements.
-- Reward configuration belongs to badgesList. Claim keys contain authorization
-- only; plaintext code values are never persisted.

SET @fox_sql = IF(
    NOT EXISTS(
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'badgesList'
          AND COLUMN_NAME = 'rewardCurrency'
    ),
    "ALTER TABLE `badgesList` ADD COLUMN `rewardCurrency` VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NULL AFTER `img`",
    'SELECT 1'
);
PREPARE fox_stmt FROM @fox_sql;
EXECUTE fox_stmt;
DEALLOCATE PREPARE fox_stmt;

SET @fox_sql = IF(
    NOT EXISTS(
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'badgesList'
          AND COLUMN_NAME = 'rewardAmount'
    ),
    "ALTER TABLE `badgesList` ADD COLUMN `rewardAmount` BIGINT UNSIGNED NOT NULL DEFAULT 0 AFTER `rewardCurrency`",
    'SELECT 1'
);
PREPARE fox_stmt FROM @fox_sql;
EXECUTE fox_stmt;
DEALLOCATE PREPARE fox_stmt;

SET @fox_sql = IF(
    NOT EXISTS(
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'badgeClaimKeys'
          AND COLUMN_NAME = 'publicPlacement'
    ),
    "ALTER TABLE `badgeClaimKeys` ADD COLUMN `publicPlacement` VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL AFTER `accessMode`",
    'SELECT 1'
);
PREPARE fox_stmt FROM @fox_sql;
EXECUTE fox_stmt;
DEALLOCATE PREPARE fox_stmt;

-- Preserve data from the earlier development shape, where reward fields lived
-- on the key rather than on the badge.
SET @fox_sql = IF(
    EXISTS(
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'badgeClaimKeys'
          AND COLUMN_NAME = 'rewardCurrency'
    )
    AND EXISTS(
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'badgeClaimKeys'
          AND COLUMN_NAME = 'rewardAmount'
    ),
    "UPDATE `badgesList` AS `badge`
     INNER JOIN `badgeClaimKeys` AS `claimKey` ON `claimKey`.`badgeId` = `badge`.`id`
     SET `badge`.`rewardCurrency` = NULLIF(`claimKey`.`rewardCurrency`, ''),
         `badge`.`rewardAmount` = `claimKey`.`rewardAmount`
     WHERE `claimKey`.`rewardAmount` > 0",
    'SELECT 1'
);
PREPARE fox_stmt FROM @fox_sql;
EXECUTE fox_stmt;
DEALLOCATE PREPARE fox_stmt;

SET @fox_sql = IF(
    EXISTS(
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'badgeClaimKeys'
          AND COLUMN_NAME = 'rewardCurrency'
    ),
    'ALTER TABLE `badgeClaimKeys` DROP COLUMN `rewardCurrency`',
    'SELECT 1'
);
PREPARE fox_stmt FROM @fox_sql;
EXECUTE fox_stmt;
DEALLOCATE PREPARE fox_stmt;

SET @fox_sql = IF(
    EXISTS(
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'badgeClaimKeys'
          AND COLUMN_NAME = 'rewardAmount'
    ),
    'ALTER TABLE `badgeClaimKeys` DROP COLUMN `rewardAmount`',
    'SELECT 1'
);
PREPARE fox_stmt FROM @fox_sql;
EXECUTE fox_stmt;
DEALLOCATE PREPARE fox_stmt;

SET @fox_sql = IF(
    NOT EXISTS(
        SELECT 1 FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'badgeClaimKeys'
          AND INDEX_NAME = 'uq_badge_claim_public_placement'
    ),
    'ALTER TABLE `badgeClaimKeys` ADD UNIQUE KEY `uq_badge_claim_public_placement` (`publicPlacement`)',
    'SELECT 1'
);
PREPARE fox_stmt FROM @fox_sql;
EXECUTE fox_stmt;
DEALLOCATE PREPARE fox_stmt;

INSERT INTO `badgesList` (`badgeName`, `description`, `img`, `rewardCurrency`, `rewardAmount`)
VALUES (
    'Раннее Возрождение',
    'Памятный бейдж раннего возрождения FoxesCraft 3.0.',
    '',
    'crystals',
    3
)
ON DUPLICATE KEY UPDATE
    `description` = VALUES(`description`),
    `rewardCurrency` = VALUES(`rewardCurrency`),
    `rewardAmount` = VALUES(`rewardAmount`);


-- Replace the legacy name-based rules offer with an explicit placement key.
UPDATE `badgeClaimKeys` AS `claimKey`
INNER JOIN `badgesList` AS `badge` ON `badge`.`id` = `claimKey`.`badgeId`
SET `claimKey`.`enabled` = 0,
    `claimKey`.`updatedAt` = UNIX_TIMESTAMP()
WHERE `badge`.`badgeName` = 'Знаток правил'
  AND `claimKey`.`accessMode` = 'public'
  AND `claimKey`.`publicPlacement` IS NULL;

INSERT INTO `badgeClaimKeys`
    (`badgeId`, `tokenHash`, `tokenHint`, `usageMode`, `accessMode`, `publicPlacement`, `usesCount`, `enabled`, `createdAt`, `updatedAt`, `createdByUuid`)
SELECT
    `id`,
    SHA2(RANDOM_BYTES(32), 256),
    LOWER(HEX(RANDOM_BYTES(5))),
    'reusable',
    'public',
    'rules',
    0,
    1,
    UNIX_TIMESTAMP(),
    UNIX_TIMESTAMP(),
    NULL
FROM `badgesList`
WHERE `badgeName` = 'Знаток правил'
ON DUPLICATE KEY UPDATE
    `badgeId` = VALUES(`badgeId`),
    `tokenHash` = VALUES(`tokenHash`),
    `tokenHint` = VALUES(`tokenHint`),
    `usageMode` = 'reusable',
    `accessMode` = 'public',
    `enabled` = 1,
    `updatedAt` = VALUES(`updatedAt`);

-- A placement key is the actual reusable cryptographic authorization record.
-- Its plaintext is discarded; each user redeems the stored SHA-256 digest once
-- through BadgeClaimService::claimByHash().
INSERT INTO `badgeClaimKeys`
    (`badgeId`, `tokenHash`, `tokenHint`, `usageMode`, `accessMode`, `publicPlacement`, `usesCount`, `enabled`, `createdAt`, `updatedAt`, `createdByUuid`)
SELECT
    `id`,
    SHA2(RANDOM_BYTES(32), 256),
    LOWER(HEX(RANDOM_BYTES(5))),
    'reusable',
    'public',
    'welcome-native',
    0,
    1,
    UNIX_TIMESTAMP(),
    UNIX_TIMESTAMP(),
    NULL
FROM `badgesList`
WHERE `badgeName` = 'Раннее Возрождение'
ON DUPLICATE KEY UPDATE
    `badgeId` = VALUES(`badgeId`),
    `tokenHash` = VALUES(`tokenHash`),
    `tokenHint` = VALUES(`tokenHint`),
    `usageMode` = 'reusable',
    `accessMode` = 'public',
    `enabled` = 1,
    `updatedAt` = VALUES(`updatedAt`);

SELECT
    `badge`.`id`,
    `badge`.`badgeName`,
    `badge`.`rewardCurrency`,
    `badge`.`rewardAmount`,
    `claimKey`.`publicPlacement`,
    `claimKey`.`tokenHint`,
    `claimKey`.`enabled`
FROM `badgesList` AS `badge`
LEFT JOIN `badgeClaimKeys` AS `claimKey`
    ON `claimKey`.`badgeId` = `badge`.`id`
   AND `claimKey`.`publicPlacement` = 'welcome-native'
WHERE `badge`.`badgeName` = 'Раннее Возрождение'
LIMIT 1;
