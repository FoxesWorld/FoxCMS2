-- FoxCMS migration 016: revoke the formerly public badge claim key on databases
-- where migration 014 may already have been recorded before it was hardened.

UPDATE `badgeClaimKeys`
SET `enabled` = 0,
    `updatedAt` = UNIX_TIMESTAMP()
WHERE `tokenHash` = '4d7b99804414da0ffa5f5e435ff04f2a43bf3669e78d7c61dceb1fc667cfa26c';

SELECT COUNT(*) AS `activeLegacyPublicBadgeKeys`
FROM `badgeClaimKeys`
WHERE `tokenHash` = '4d7b99804414da0ffa5f5e435ff04f2a43bf3669e78d7c61dceb1fc667cfa26c'
  AND `enabled` = 1;
