-- FoxCMS migration 014: expose the rules badge through the generic hashed claim-key flow.
-- The public claim token is embedded on the rules page; only its SHA-256 digest is persisted.

INSERT INTO `badgeClaimKeys`
    (`badgeId`, `tokenHash`, `tokenHint`, `usageMode`, `usesCount`, `enabled`, `createdAt`, `updatedAt`, `createdByUuid`)
SELECT
    `id`,
    '4d7b99804414da0ffa5f5e435ff04f2a43bf3669e78d7c61dceb1fc667cfa26c',
    '0kjzOIY2bE',
    'reusable',
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
    `enabled` = 1,
    `updatedAt` = VALUES(`updatedAt`);

SELECT `key`.`id`, `key`.`badgeId`, `key`.`tokenHint`, `key`.`usageMode`, `key`.`enabled`
FROM `badgeClaimKeys` AS `key`
INNER JOIN `badgesList` AS `badge` ON `badge`.`id` = `key`.`badgeId`
WHERE `key`.`tokenHash` = '4d7b99804414da0ffa5f5e435ff04f2a43bf3669e78d7c61dceb1fc667cfa26c'
  AND `badge`.`badgeName` = 'Знаток правил'
LIMIT 1;
