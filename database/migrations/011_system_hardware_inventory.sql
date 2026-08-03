CREATE TABLE IF NOT EXISTS `system_hardware_inventory` (
    `systemHWID` CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    `schemaVersion` SMALLINT UNSIGNED NOT NULL,
    `updaterVersion` VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    `platform` VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    `osName` VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    `osVersion` VARCHAR(120) NULL,
    `kernelVersion` VARCHAR(120) NULL,
    `architecture` VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    `cpuBrand` VARCHAR(200) NULL,
    `logicalCpuCount` SMALLINT UNSIGNED NOT NULL,
    `memoryBytes` BIGINT UNSIGNED NOT NULL,
    `gpuAdapters` JSON NOT NULL,
    `report` JSON NOT NULL,
    `firstSeenAt` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`systemHWID`),
    KEY `idx_system_hardware_inventory_platform` (`platform`),
    KEY `idx_system_hardware_inventory_first_seen` (`firstSeenAt`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
