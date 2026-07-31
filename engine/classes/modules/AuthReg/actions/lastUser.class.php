<?php

declare(strict_types=1);

if (!defined('auth')) {
    http_response_code(403);
    exit('Forbidden');
}

final class LastUser
{
    public function __construct(private $db)
    {
    }

    public function toArray(): array
    {
        $statement = $this->db->prepare(
            'SELECT `colorScheme`, `realname`, `login`, `profilePhoto`, `reg_date` '
            . 'FROM `users` ORDER BY `user_id` DESC LIMIT 1'
        );
        $statement->execute();
        $user = $statement->fetch(PDO::FETCH_ASSOC);
        return $user ?: [];
    }
}
