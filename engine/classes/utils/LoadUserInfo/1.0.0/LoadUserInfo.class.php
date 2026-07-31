<?php

declare(strict_types=1);

final class LoadUserInfo
{
    private array $userInfo = [];

    public function __construct(
        string $identity,
        private db $database,
        string $field = 'uuid',
    ) {
        if (!in_array($field, ['uuid', 'login', 'email'], true)) {
            throw new InvalidArgumentException('Unsupported user lookup field.');
        }

        $identity = trim($identity);
        if ($identity === '') {
            return;
        }
        if ($field === 'uuid') {
            $placeholders = [];
            $parameters = [];
            foreach (Uuid::databaseCandidates($identity) as $index => $candidate) {
                $placeholder = ':identity_' . $index;
                $placeholders[] = $placeholder;
                $parameters[$placeholder] = $candidate;
            }
            $statement = $this->database->prepare(
                'SELECT * FROM `users` WHERE `uuid` IN (' . implode(', ', $placeholders) . ') LIMIT 1'
            );
            $statement->execute($parameters);
        } else {
            if ($field === 'login' && preg_match('/^[A-Za-z0-9_.-]{1,64}$/D', $identity) !== 1) {
                return;
            }
            if ($field === 'email' && filter_var($identity, FILTER_VALIDATE_EMAIL) === false) {
                return;
            }
            $statement = $this->database->prepare(
                'SELECT * FROM `users` WHERE `' . $field . '` = :identity LIMIT 1'
            );
            $statement->execute([':identity' => $identity]);
        }
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if (is_array($row)) {
            $row['uuid'] = Uuid::normalize((string)$row['uuid']);
            $row['isLogged'] = true;
            $this->userInfo = $row;
        }
    }

    public static function byUuid(string $uuid, db $database): self
    {
        return new self($uuid, $database, 'uuid');
    }

    public static function byLogin(string $login, db $database): self
    {
        return new self($login, $database, 'login');
    }

    public static function byEmail(string $email, db $database): self
    {
        return new self($email, $database, 'email');
    }

    public function userInfoArray(): array
    {
        return $this->userInfo;
    }
}
