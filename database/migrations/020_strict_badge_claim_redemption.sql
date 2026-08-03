-- FoxCMS migration 020: strict cryptographic badge redemption.
-- Public placements redeem their own hidden digest. A profile can acquire each
-- badge only once, regardless of how many claim keys exist for that badge.

-- Remove duplicate historical ledger rows before enforcing uniqueness.
DELETE duplicateClaim
FROM `badgeKeyClaims` AS duplicateClaim
INNER JOIN `badgeKeyClaims` AS originalClaim
    ON originalClaim.`badgeId` = duplicateClaim.`badgeId`
   AND originalClaim.`userUuid` = duplicateClaim.`userUuid`
   AND originalClaim.`id` < duplicateClaim.`id`;

SET @fox_sql = IF(
    EXISTS(
        SELECT 1 FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'badgeKeyClaims'
          AND INDEX_NAME = 'ix_badge_key_claim_badge_user'
    ),
    'ALTER TABLE `badgeKeyClaims` DROP INDEX `ix_badge_key_claim_badge_user`',
    'SELECT 1'
);
PREPARE fox_stmt FROM @fox_sql;
EXECUTE fox_stmt;
DEALLOCATE PREPARE fox_stmt;

SET @fox_sql = IF(
    NOT EXISTS(
        SELECT 1 FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'badgeKeyClaims'
          AND INDEX_NAME = 'uq_badge_key_claim_badge_user'
    ),
    'ALTER TABLE `badgeKeyClaims` ADD UNIQUE KEY `uq_badge_key_claim_badge_user` (`badgeId`, `userUuid`)',
    'SELECT 1'
);
PREPARE fox_stmt FROM @fox_sql;
EXECUTE fox_stmt;
DEALLOCATE PREPARE fox_stmt;

-- Retire legacy public keys that could be resolved only by badge name.
UPDATE `badgeClaimKeys`
SET `enabled` = 0,
    `updatedAt` = UNIX_TIMESTAMP()
WHERE `accessMode` = 'public'
  AND `publicPlacement` IS NULL;

-- Rotate and bind the rules offer to an explicit placement.
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

-- Rotate and preserve the welcome offer. Reward values remain badge-owned and
-- editable through the administrative badge catalog.
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

SELECT `publicPlacement`, `tokenHint`, `enabled`
FROM `badgeClaimKeys`
WHERE `accessMode` = 'public'
  AND `publicPlacement` IN ('rules', 'welcome-native')
ORDER BY `publicPlacement`;
