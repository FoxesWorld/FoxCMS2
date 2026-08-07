<?php

declare(strict_types=1);

namespace FoxCMS\Api\User;

use PDO;

final class PublicUserProfileRepository
{
    public function __construct(private readonly \db $database)
    {
    }

    /** @return array<string, mixed>|null */
    public function findByUuid(string $uuid): ?array
    {
        $placeholders = [];
        $parameters = [];
        foreach (\Uuid::databaseCandidates($uuid) as $index => $candidate) {
            $placeholder = ':uuid_' . $index;
            $placeholders[] = $placeholder;
            $parameters[$placeholder] = $candidate;
        }

        $statement = $this->database->prepare(
            'SELECT '
            . '`user`.`uuid`, `user`.`login`, `user`.`realname`, '
            . '`user`.`userStatus`, `user`.`land`, `user`.`colorScheme`, '
            . '`user`.`profilePhoto`, `user`.`reg_date`, `user`.`last_date`, '
            . '`user`.`badges`, `user`.`serversOnline`, `user`.`groupTag`, '
            . '`user_group`.`groupName`, `user_group`.`groupColor` '
            . 'FROM `users` AS `user` '
            . 'LEFT JOIN `groupAssociation` AS `user_group` '
            . 'ON `user_group`.`groupTag` = `user`.`groupTag` '
            . 'WHERE `user`.`uuid` IN (' . implode(', ', $placeholders) . ') '
            . 'LIMIT 1'
        );
        $statement->execute($parameters);
        $profile = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($profile) ? $profile : null;
    }
}
