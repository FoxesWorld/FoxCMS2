<?php

declare(strict_types=1);

final class GroupRepository
{
    public function __construct(private $db)
    {
    }

    public function find(int $group): array
    {
        $statement = $this->db->prepare(
            'SELECT `groupNum`, `groupName`, `groupType`, `groupColor` '
            . 'FROM `groupAssociation` WHERE `groupNum` = :group LIMIT 1'
        );
        $statement->execute([':group' => $group]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row ?: [
            'groupNum' => $group,
            'groupName' => 'Гость',
            'groupType' => 'guest',
            'groupColor' => '#ffffff',
        ];
    }

    public function tag(int $group): string
    {
        return (string)$this->find($group)['groupType'];
    }
}
