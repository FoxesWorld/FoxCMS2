-- FoxCMS migration 026: localized achievement category labels.

ALTER TABLE `gameAchievements`
    ADD COLUMN `categoryLabel` VARCHAR(190) NOT NULL DEFAULT '' AFTER `category`;

-- Keep the label empty until Fox Achievements supplies a localized value.
-- Public API fallback resolves legacy rows from localized root advancement titles.
