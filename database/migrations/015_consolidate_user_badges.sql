-- FoxCMS migration 015: consolidate badge ownership into users.badges.
-- users.badges is the canonical source of truth. A legacy userBadges table is
-- imported only when the canonical field is empty, then removed.

SET @fox_user_badges_exists = IF(
    EXISTS(
        SELECT 1 FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'userBadges'
    ),
    1,
    0
);

SET @fox_user_badges_has_uuid = IF(
    EXISTS(
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'userBadges'
          AND COLUMN_NAME = 'userUuid'
    ),
    1,
    0
);

SET @fox_user_badges_has_login = IF(
    EXISTS(
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'userBadges'
          AND COLUMN_NAME = 'userLogin'
    ),
    1,
    0
);

SET @fox_user_badges_has_badges = IF(
    EXISTS(
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'userBadges'
          AND COLUMN_NAME = 'badges'
    ),
    1,
    0
);

SET @fox_sql = IF(
    @fox_user_badges_exists = 1
    AND @fox_user_badges_has_uuid = 1
    AND @fox_user_badges_has_badges = 1,
    'UPDATE `users` AS `user` INNER JOIN `userBadges` AS `assignment` ON `assignment`.`userUuid` = `user`.`uuid` SET `user`.`badges` = `assignment`.`badges` WHERE COALESCE(NULLIF(TRIM(`user`.`badges`), ''''), ''[]'') = ''[]'' AND COALESCE(NULLIF(TRIM(`assignment`.`badges`), ''''), ''[]'') <> ''[]''',
    'SELECT 1'
);
PREPARE fox_stmt FROM @fox_sql;
EXECUTE fox_stmt;
DEALLOCATE PREPARE fox_stmt;

SET @fox_sql = IF(
    @fox_user_badges_exists = 1
    AND @fox_user_badges_has_uuid = 0
    AND @fox_user_badges_has_login = 1
    AND @fox_user_badges_has_badges = 1,
    'UPDATE `users` AS `user` INNER JOIN `userBadges` AS `assignment` ON `assignment`.`userLogin` = `user`.`login` SET `user`.`badges` = `assignment`.`badges` WHERE COALESCE(NULLIF(TRIM(`user`.`badges`), ''''), ''[]'') = ''[]'' AND COALESCE(NULLIF(TRIM(`assignment`.`badges`), ''''), ''[]'') <> ''[]''',
    'SELECT 1'
);
PREPARE fox_stmt FROM @fox_sql;
EXECUTE fox_stmt;
DEALLOCATE PREPARE fox_stmt;

DROP TABLE IF EXISTS `userBadges`;

SELECT `uuid`, `badges` FROM `users` LIMIT 0;
