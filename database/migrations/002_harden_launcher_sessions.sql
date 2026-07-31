-- FoxCMS migration 002: launcher session token hardening and expiry.
-- Run through scripts/migrate.php with a schema-owner account.

UPDATE `usersession`
SET `accessToken` = SHA2(`accessToken`, 256)
WHERE `accessToken` IS NOT NULL
  AND CHAR_LENGTH(`accessToken`) <> 64;

ALTER TABLE `usersession`
    MODIFY `user` VARCHAR(64)
        CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
    MODIFY `userMd5` CHAR(32)
        CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    MODIFY `accessToken` CHAR(64)
        CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    MODIFY `serverId` VARCHAR(41)
        CHARACTER SET ascii COLLATE ascii_bin NULL,
    ADD COLUMN `expiresAt` BIGINT UNSIGNED NOT NULL DEFAULT 0
        AFTER `accessToken`,
    ADD COLUMN `updatedAt` DATETIME(4) NOT NULL
        DEFAULT CURRENT_TIMESTAMP(4)
        ON UPDATE CURRENT_TIMESTAMP(4);

UPDATE `usersession`
SET `expiresAt` = 0,
    `serverId` = NULL;
