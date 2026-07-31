-- UUID is the immutable FoxCMS user identity. Login remains unique, but mutable.

ALTER TABLE `users`
    MODIFY COLUMN `login` VARCHAR(64) NOT NULL,
    MODIFY COLUMN `uuid` CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NULL;

UPDATE `users`
SET `uuid` = LOWER(UUID())
WHERE `uuid` IS NULL
   OR `uuid` NOT REGEXP '^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[1-8][0-9a-fA-F]{3}-[89aAbB][0-9a-fA-F]{3}-[0-9a-fA-F]{12}$';

ALTER TABLE `users`
    MODIFY COLUMN `uuid` CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    ADD UNIQUE KEY `ux_users_uuid` (`uuid`),
    ADD UNIQUE KEY `ux_users_login` (`login`),
    DROP COLUMN `hash`;

ALTER TABLE `usersession`
    ADD COLUMN `userUuid` CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NULL AFTER `id`;

UPDATE `usersession` AS `session`
INNER JOIN `users` AS `user` ON `user`.`login` = `session`.`user`
SET `session`.`userUuid` = `user`.`uuid`;

DELETE FROM `usersession` WHERE `userUuid` IS NULL;

ALTER TABLE `usersession`
    MODIFY COLUMN `userUuid` CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    ADD UNIQUE KEY `ux_usersession_user_uuid` (`userUuid`),
    ADD CONSTRAINT `fk_usersession_user_uuid`
        FOREIGN KEY (`userUuid`) REFERENCES `users` (`uuid`)
        ON UPDATE RESTRICT ON DELETE CASCADE,
    DROP COLUMN `user`,
    DROP COLUMN `userMd5`,
    DROP COLUMN `passMd5`;

ALTER TABLE `userBadges`
    ADD COLUMN `userUuid` CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NULL AFTER `id`;

UPDATE `userBadges` AS `assignment`
INNER JOIN `users` AS `user` ON `user`.`login` = `assignment`.`userLogin`
SET `assignment`.`userUuid` = `user`.`uuid`;

DELETE FROM `userBadges` WHERE `userUuid` IS NULL;

ALTER TABLE `userBadges`
    MODIFY COLUMN `userUuid` CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    ADD UNIQUE KEY `ux_user_badges_user_uuid` (`userUuid`),
    ADD CONSTRAINT `fk_user_badges_user_uuid`
        FOREIGN KEY (`userUuid`) REFERENCES `users` (`uuid`)
        ON UPDATE RESTRICT ON DELETE CASCADE,
    DROP COLUMN `userLogin`;

DROP TABLE IF EXISTS `password_resets`;

CREATE TABLE `password_reset_tokens` (
    `userUuid` CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    `tokenHash` CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    `expiresAt` BIGINT UNSIGNED NOT NULL,
    `createdAt` DATETIME(4) NOT NULL DEFAULT CURRENT_TIMESTAMP(4),
    PRIMARY KEY (`userUuid`),
    UNIQUE KEY `ux_password_reset_token_hash` (`tokenHash`),
    KEY `ix_password_reset_expires_at` (`expiresAt`),
    CONSTRAINT `fk_password_reset_user_uuid`
        FOREIGN KEY (`userUuid`) REFERENCES `users` (`uuid`)
        ON UPDATE RESTRICT ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `user_hardware_reports` (
    `userUuid` CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    `cpuIdHash` CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL,
    `cpu` VARCHAR(255) NOT NULL DEFAULT '',
    `gpus` JSON NOT NULL,
    `payload` JSON NOT NULL,
    `updatedAt` DATETIME(4) NOT NULL DEFAULT CURRENT_TIMESTAMP(4) ON UPDATE CURRENT_TIMESTAMP(4),
    PRIMARY KEY (`userUuid`),
    KEY `ix_user_hardware_cpu_id_hash` (`cpuIdHash`),
    CONSTRAINT `fk_user_hardware_user_uuid`
        FOREIGN KEY (`userUuid`) REFERENCES `users` (`uuid`)
        ON UPDATE RESTRICT ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
