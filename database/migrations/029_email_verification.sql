-- Email verification state and one-time verification tokens.

ALTER TABLE `users`
    ADD COLUMN `emailVerifiedAt` BIGINT UNSIGNED NULL AFTER `email`;

CREATE TABLE `email_verification_tokens` (
    `userUuid` CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    `email` VARCHAR(254) NOT NULL,
    `tokenHash` CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    `expiresAt` BIGINT UNSIGNED NOT NULL,
    `createdAt` DATETIME(4) NOT NULL DEFAULT CURRENT_TIMESTAMP(4),
    PRIMARY KEY (`userUuid`),
    UNIQUE KEY `ux_email_verification_token_hash` (`tokenHash`),
    KEY `ix_email_verification_expires_at` (`expiresAt`),
    CONSTRAINT `fk_email_verification_user_uuid`
        FOREIGN KEY (`userUuid`) REFERENCES `users` (`uuid`)
        ON UPDATE RESTRICT ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
