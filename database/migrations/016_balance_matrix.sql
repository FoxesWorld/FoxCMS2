-- FoxCMS migration 016: upgrade legacy user balances to the canonical Units/Crystals JSON matrix.
-- Supported legacy shape: [{"crystals":200},{"units":1000}] (in either order),
-- flat objects such as {"Units":1000,"Crystals":200}, empty/invalid JSON, and users.units fallback.
-- Already canonical rows with a $.currencies array are left unchanged.

DROP TEMPORARY TABLE IF EXISTS `fox_balance_upgrade_016`;

CREATE TEMPORARY TABLE `fox_balance_upgrade_016` (
    `user_id` BIGINT UNSIGNED NOT NULL,
    `balance_text` LONGTEXT NULL,
    `units_fallback_text` LONGTEXT NULL,
    `units_raw` VARCHAR(128) NULL,
    `crystals_raw` VARCHAR(128) NULL,
    `units_amount` DECIMAL(20, 0) NOT NULL DEFAULT 0,
    `crystals_amount` DECIMAL(20, 0) NOT NULL DEFAULT 0,
    `is_canonical` TINYINT(1) NOT NULL DEFAULT 0,
    PRIMARY KEY (`user_id`)
) ENGINE=InnoDB;

-- Stage raw legacy values as text. No arithmetic or implicit numeric coercion is
-- performed against users.balance or users.units at this point.
INSERT INTO `fox_balance_upgrade_016` (`user_id`, `balance_text`, `units_fallback_text`)
SELECT
    `user_id`,
    CAST(`balance` AS CHAR CHARACTER SET utf8mb4),
    CAST(`units` AS CHAR CHARACTER SET utf8mb4)
FROM `users`;

-- JSON functions are called only after invalid source values have been replaced
-- by a valid empty object.
UPDATE `fox_balance_upgrade_016`
SET `balance_text` = '{}'
WHERE `balance_text` IS NULL
   OR JSON_VALID(`balance_text`) = 0;

UPDATE `fox_balance_upgrade_016`
SET `is_canonical` = CASE
    WHEN JSON_TYPE(JSON_EXTRACT(`balance_text`, '$.currencies')) = 'ARRAY' THEN 1
    ELSE 0
END;

-- Read both historical singleton-object arrays and flat objects. All COALESCE
-- operands are strings, so MariaDB cannot coerce the complete JSON document to DOUBLE.
UPDATE `fox_balance_upgrade_016`
SET
    `units_raw` = COALESCE(
        NULLIF(JSON_UNQUOTE(JSON_EXTRACT(`balance_text`, '$.units')), 'null'),
        NULLIF(JSON_UNQUOTE(JSON_EXTRACT(`balance_text`, '$.Units')), 'null'),
        NULLIF(JSON_UNQUOTE(JSON_EXTRACT(`balance_text`, '$[0].units')), 'null'),
        NULLIF(JSON_UNQUOTE(JSON_EXTRACT(`balance_text`, '$[1].units')), 'null'),
        NULLIF(JSON_UNQUOTE(JSON_EXTRACT(`balance_text`, '$[0].Units')), 'null'),
        NULLIF(JSON_UNQUOTE(JSON_EXTRACT(`balance_text`, '$[1].Units')), 'null'),
        NULLIF(TRIM(`units_fallback_text`), ''),
        '0'
    ),
    `crystals_raw` = COALESCE(
        NULLIF(JSON_UNQUOTE(JSON_EXTRACT(`balance_text`, '$.crystals')), 'null'),
        NULLIF(JSON_UNQUOTE(JSON_EXTRACT(`balance_text`, '$.Crystals')), 'null'),
        NULLIF(JSON_UNQUOTE(JSON_EXTRACT(`balance_text`, '$[0].crystals')), 'null'),
        NULLIF(JSON_UNQUOTE(JSON_EXTRACT(`balance_text`, '$[1].crystals')), 'null'),
        NULLIF(JSON_UNQUOTE(JSON_EXTRACT(`balance_text`, '$[0].Crystals')), 'null'),
        NULLIF(JSON_UNQUOTE(JSON_EXTRACT(`balance_text`, '$[1].Crystals')), 'null'),
        '0'
    )
WHERE `is_canonical` = 0;

UPDATE `fox_balance_upgrade_016`
SET
    `units_raw` = TRIM(COALESCE(`units_raw`, '0')),
    `crystals_raw` = TRIM(COALESCE(`crystals_raw`, '0'))
WHERE `is_canonical` = 0;

-- Numeric conversion is executed only for rows that already passed a strict
-- digits-only check. Invalid legacy values remain zero.
UPDATE `fox_balance_upgrade_016`
SET `units_amount` = LEAST(CAST(`units_raw` AS DECIMAL(20, 0)), 9007199254740991)
WHERE `is_canonical` = 0
  AND `units_raw` REGEXP '^[0-9]+$';

UPDATE `fox_balance_upgrade_016`
SET `crystals_amount` = LEAST(CAST(`crystals_raw` AS DECIMAL(20, 0)), 9007199254740991)
WHERE `is_canonical` = 0
  AND `crystals_raw` REGEXP '^[0-9]+$';

UPDATE `users` AS `user`
INNER JOIN `fox_balance_upgrade_016` AS `upgrade`
    ON `upgrade`.`user_id` = `user`.`user_id`
   AND `upgrade`.`is_canonical` = 0
SET `user`.`balance` = CONCAT(
    '{"version":1,"currencies":[',
    '{"code":"units","name":"Units","amount":', `upgrade`.`units_amount`, ',"symbol":"U","primary":true},',
    '{"code":"crystals","name":"Crystals","amount":', `upgrade`.`crystals_amount`, ',"symbol":"C","primary":false}',
    ']}'
);

DROP TEMPORARY TABLE `fox_balance_upgrade_016`;

ALTER TABLE `users`
    MODIFY COLUMN `balance` LONGTEXT NOT NULL
    DEFAULT '{"version":1,"currencies":[{"code":"units","name":"Units","amount":0,"symbol":"U","primary":true},{"code":"crystals","name":"Crystals","amount":0,"symbol":"C","primary":false}]}';

SELECT `balance` FROM `users` LIMIT 0;
