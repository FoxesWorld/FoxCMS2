-- FoxCMS migration 028: clean technical registry ids accidentally stored as localized category labels.

UPDATE `gameAchievements`
SET `categoryLabel` = ''
WHERE `categoryLabel` = `category`;
