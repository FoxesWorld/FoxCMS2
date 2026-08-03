-- FoxCMS migration 013: seed the badge awarded from the project rules page.

INSERT INTO `badgesList` (`badgeName`, `description`, `img`)
SELECT
    'Знаток правил',
    'Подтверждает, что пользователь ознакомился с правилами проекта.',
    ''
WHERE NOT EXISTS (
    SELECT 1 FROM `badgesList` WHERE `badgeName` = 'Знаток правил'
);

SELECT `id`, `badgeName`, `description`, `img`
FROM `badgesList`
WHERE `badgeName` = 'Знаток правил'
LIMIT 1;
