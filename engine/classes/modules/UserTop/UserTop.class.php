<?php

declare(strict_types=1);

final class UserTop
{
    public function __construct(private $db, private $logger = null)
    {
    }

    public function getTopPlayers(): never
    {
        $statement = $this->db->prepare(
            'SELECT `login`, `serversOnline`, `colorScheme` FROM `users` '
            . 'WHERE `serversOnline` IS NOT NULL AND `serversOnline` <> :empty '
            . 'ORDER BY `user_id` ASC'
        );
        $statement->execute([':empty' => '']);
        $players = $statement->fetchAll(PDO::FETCH_ASSOC);

        header('Content-Type: application/json; charset=UTF-8');
        die(json_encode($players, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
}
