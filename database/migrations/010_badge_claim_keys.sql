-- FoxCMS migration 010: badge-specific single-use and reusable claim keys.
-- Plaintext keys are never stored; only SHA-256 digests and non-secret hints are persisted.
--
-- Legacy FoxCMS installations may already contain badgesList.id with a type or
-- storage engine different from the canonical BIGINT UNSIGNED / InnoDB schema.
-- The core tables are therefore created without mandatory references to
-- badgesList. Cascading badge foreign keys are added only when the legacy table
-- is demonstrably compatible. Application-level deletion still removes keys.

CREATE TABLE IF NOT EXISTS `badgeClaimKeys` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `badgeId` BIGINT UNSIGNED NOT NULL,
    `tokenHash` CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    `tokenHint` VARCHAR(12) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT '',
    `usageMode` VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'single',
    `usesCount` BIGINT UNSIGNED NOT NULL DEFAULT 0,
    `enabled` TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,
    `createdAt` BIGINT UNSIGNED NOT NULL DEFAULT 0,
    `updatedAt` BIGINT UNSIGNED NOT NULL DEFAULT 0,
    `createdByUuid` CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_badge_claim_key_hash` (`tokenHash`),
    KEY `ix_badge_claim_key_badge` (`badgeId`),
    KEY `ix_badge_claim_key_enabled` (`enabled`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `badgeKeyClaims` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `badgeId` BIGINT UNSIGNED NOT NULL,
    `keyId` BIGINT UNSIGNED NOT NULL,
    `userUuid` CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    `claimedAt` BIGINT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (`id`),
    KEY `ix_badge_key_claim_badge_user` (`badgeId`, `userUuid`),
    KEY `ix_badge_key_claim_key` (`keyId`),
    KEY `ix_badge_key_claim_user` (`userUuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- The relation between the two new tables is always expected to be compatible,
-- but it is still added conditionally so this migration can recover from a
-- manually-created or partially-created table.
SET @fox_sql = IF(
    NOT EXISTS(
        SELECT 1
        FROM information_schema.REFERENTIAL_CONSTRAINTS
        WHERE CONSTRAINT_SCHEMA = DATABASE()
          AND TABLE_NAME = 'badgeKeyClaims'
          AND CONSTRAINT_NAME = 'fk_badge_key_claim_key'
    )
    AND EXISTS(
        SELECT 1
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'badgeKeyClaims'
          AND COLUMN_NAME = 'keyId'
          AND LOWER(COLUMN_TYPE) LIKE 'bigint%unsigned'
    )
    AND EXISTS(
        SELECT 1
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'badgeClaimKeys'
          AND COLUMN_NAME = 'id'
          AND LOWER(COLUMN_TYPE) LIKE 'bigint%unsigned'
    ),
    'ALTER TABLE `badgeKeyClaims` ADD CONSTRAINT `fk_badge_key_claim_key` FOREIGN KEY (`keyId`) REFERENCES `badgeClaimKeys` (`id`) ON DELETE CASCADE',
    'SELECT 1'
);
PREPARE fox_stmt FROM @fox_sql;
EXECUTE fox_stmt;
DEALLOCATE PREPARE fox_stmt;

-- Determine whether the existing badge catalog can safely participate in a
-- foreign key. The parent must be InnoDB, its id must be BIGINT UNSIGNED and id
-- must be the leading column of the primary key.
SET @fox_badges_fk_compatible = IF(
    EXISTS(
        SELECT 1
        FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'badgesList'
          AND ENGINE = 'InnoDB'
    )
    AND EXISTS(
        SELECT 1
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'badgesList'
          AND COLUMN_NAME = 'id'
          AND LOWER(COLUMN_TYPE) LIKE 'bigint%unsigned'
    )
    AND EXISTS(
        SELECT 1
        FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'badgesList'
          AND INDEX_NAME = 'PRIMARY'
          AND COLUMN_NAME = 'id'
          AND SEQ_IN_INDEX = 1
    ),
    1,
    0
);

SET @fox_sql = IF(
    @fox_badges_fk_compatible = 1
    AND NOT EXISTS(
        SELECT 1
        FROM information_schema.REFERENTIAL_CONSTRAINTS
        WHERE CONSTRAINT_SCHEMA = DATABASE()
          AND TABLE_NAME = 'badgeClaimKeys'
          AND CONSTRAINT_NAME = 'fk_badge_claim_key_badge'
    ),
    'ALTER TABLE `badgeClaimKeys` ADD CONSTRAINT `fk_badge_claim_key_badge` FOREIGN KEY (`badgeId`) REFERENCES `badgesList` (`id`) ON DELETE CASCADE',
    'SELECT 1'
);
PREPARE fox_stmt FROM @fox_sql;
EXECUTE fox_stmt;
DEALLOCATE PREPARE fox_stmt;

SET @fox_sql = IF(
    @fox_badges_fk_compatible = 1
    AND NOT EXISTS(
        SELECT 1
        FROM information_schema.REFERENTIAL_CONSTRAINTS
        WHERE CONSTRAINT_SCHEMA = DATABASE()
          AND TABLE_NAME = 'badgeKeyClaims'
          AND CONSTRAINT_NAME = 'fk_badge_key_claim_badge'
    ),
    'ALTER TABLE `badgeKeyClaims` ADD CONSTRAINT `fk_badge_key_claim_badge` FOREIGN KEY (`badgeId`) REFERENCES `badgesList` (`id`) ON DELETE CASCADE',
    'SELECT 1'
);
PREPARE fox_stmt FROM @fox_sql;
EXECUTE fox_stmt;
DEALLOCATE PREPARE fox_stmt;

SELECT `id`, `badgeId`, `tokenHash`, `usageMode`, `usesCount`, `enabled`
FROM `badgeClaimKeys`
LIMIT 0;

SELECT `id`, `badgeId`, `keyId`, `userUuid`
FROM `badgeKeyClaims`
LIMIT 0;
