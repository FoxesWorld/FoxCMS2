<?php

declare(strict_types=1);

namespace FoxCMS\Engine\User;

use \BalanceMatrix;
use \PDO;
use \Uuid;

final class UserProfileQueryController
{
    private const PUBLIC_PROFILE_FIELDS = [
        'uuid',
        'login',
        'groupTag',
        'realname',
        'reg_date',
        'last_date',
        'profilePhoto',
        'userStatus',
        'land',
        'colorScheme',
        'badges',
        'serversOnline',
    ];

    public function __construct(
        private readonly \db $db,
        private readonly \HttpRequest $request,
        private readonly \UserSession $session,
        private readonly UserActionResponder $responder,
    ) {
    }

    public function getUserSettings(): never
    {
        if (!$this->session->isLogged()) {
            $this->responder->send(['message' => 'Нужно войти в аккаунт.', 'type' => 'error'], 401);
        }

        $requestedUuid = $this->request->string('userUuid');
        $login = $this->request->string('login');
        if ($requestedUuid !== '' && !Uuid::isValid($requestedUuid)) {
            $this->responder->send(['message' => 'Некорректный UUID пользователя.', 'type' => 'error'], 400);
        }
        if ($requestedUuid === '' && preg_match('/^[A-Za-z0-9_.-]{3,64}$/D', $login) !== 1) {
            $this->responder->send(['message' => 'Некорректный логин.', 'type' => 'error'], 400);
        }

        $parameters = [];
        if ($requestedUuid !== '') {
            $placeholders = [];
            foreach (Uuid::databaseCandidates($requestedUuid) as $index => $candidate) {
                $placeholder = ':identity_' . $index;
                $placeholders[] = $placeholder;
                $parameters[$placeholder] = $candidate;
            }
            $where = '`uuid` IN (' . implode(', ', $placeholders) . ')';
        } else {
            $where = '`login` = :identity';
            $parameters[':identity'] = $login;
        }
        $statement = $this->db->prepare(
            'SELECT `uuid`, `login`, `groupTag`, `realname`, `email`, `profilePhoto`, '
            . '`userStatus`, `land`, `colorScheme` FROM `users` WHERE ' . $where . ' LIMIT 1'
        );
        $statement->execute($parameters);
        $user = $statement->fetch(PDO::FETCH_ASSOC);
        if (!is_array($user)) {
            $this->responder->send(['message' => 'Пользователь не найден.', 'type' => 'error'], 404);
        }

        $isOwner = Uuid::equals($this->session->uuid(), (string)$user['uuid']);
        if (!$isOwner && !$this->session->isAdmin()) {
            $this->responder->send(['message' => 'Недостаточно прав для изменения этого профиля.', 'type' => 'error'], 403);
        }
        $this->responder->send($user);
    }

    public function getUserData(): never
    {
        $login = $this->request->string('login');
        if (preg_match('/^[A-Za-z0-9_.-]{1,64}$/', $login) !== 1) {
            $this->responder->send(['message' => 'Некорректный логин.', 'type' => 'error'], 400);
        }

        $fields = implode(', ', array_map(
            static fn (string $field): string => '`user`.`' . $field . '` AS `' . $field . '`',
            self::PUBLIC_PROFILE_FIELDS,
        ));
        $statement = $this->db->prepare(
            'SELECT ' . $fields . ', '
            . '`user`.`balance` AS `balance`, '
            . '`group`.`groupName` AS `groupName`, '
            . '`group`.`groupColor` AS `groupColor` '
            . 'FROM `users` AS `user` '
            . 'LEFT JOIN `groupAssociation` AS `group` ON `group`.`groupTag` = `user`.`groupTag` '
            . 'WHERE `user`.`login` = :login LIMIT 1'
        );
        $statement->execute([':login' => $login]);
        $user = $statement->fetch(PDO::FETCH_ASSOC);
        if (!is_array($user)) {
            $this->responder->send(['message' => 'Пользователь не найден.', 'type' => 'error'], 404);
        }

        $user['balance'] = BalanceMatrix::normalize($user['balance'] ?? null);
        foreach (['badges', 'serversOnline'] as $jsonField) {
            if (isset($user[$jsonField]) && is_string($user[$jsonField])) {
                $user[$jsonField] = json_decode($user[$jsonField], true) ?? [];
            }
        }

        $isOwner = $this->session->isLogged()
            && Uuid::equals($this->session->uuid(), (string)$user['uuid']);
        if (!$isOwner && !$this->session->isAdmin()) {
            unset($user['balance']);
        }

        $this->responder->send($user);
    }
}
