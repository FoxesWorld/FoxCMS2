-- FoxCMS migration 026: localized achievement category labels.

ALTER TABLE `gameAchievements`
    ADD COLUMN `categoryLabel` VARCHAR(190) NOT NULL DEFAULT '' AFTER `category`;

UPDATE `gameAchievements`
SET `categoryLabel` = `category`
WHERE `categoryLabel` = '';
