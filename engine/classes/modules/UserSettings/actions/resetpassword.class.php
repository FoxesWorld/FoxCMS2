<?php

declare(strict_types=1);

final class ResetPassword
{
    public function __construct(private db $db)
    {
    }

    public function reset(string $token, string $password, string $confirmation): never
    {
        $token = strtolower(trim($token));
        if (preg_match('/^[a-f0-9]{64}$/D', $token) !== 1) {
            $this->respond('Ссылка восстановления недействительна.', 'error', 422);
        }
        if ($password !== $confirmation) {
            $this->respond('Пароли не совпадают.', 'error', 422);
        }
        if (strlen($password) < 10 || strlen($password) > 72 || preg_match('/[А-Яа-яЁё]/u', $password)) {
            $this->respond('Пароль должен содержать от 10 до 72 символов без кириллицы.', 'error', 422);
        }

        try {
            $this->db->transactional(function () use ($token, $password): void {
                $select = $this->db->prepare(
                    'SELECT `userUuid` FROM `password_reset_tokens` '
                    . 'WHERE `tokenHash` = :tokenHash AND `expiresAt` > :currentTime '
                    . 'LIMIT 1 FOR UPDATE'
                );
                $select->execute([
                    ':tokenHash' => hash('sha256', $token),
                    ':currentTime' => CURRENT_TIME,
                ]);
                $userUuid = $select->fetchColumn();
                if (!is_string($userUuid) || !Uuid::isValid($userUuid)) {
                    throw new DomainException('Ссылка истекла или уже использована.');
                }
                $userUuid = Uuid::normalize($userUuid);

                $placeholders = [];
                $parameters = [
                    ':password' => authorize::hashPassword($password),
                    ':rememberToken' => '',
                ];
                foreach (Uuid::databaseCandidates($userUuid) as $index => $candidate) {
                    $placeholder = ':userUuid_' . $index;
                    $placeholders[] = $placeholder;
                    $parameters[$placeholder] = $candidate;
                }
                $update = $this->db->prepare(
                    'UPDATE `users` SET `password` = :password, `token` = :rememberToken '
                    . 'WHERE `uuid` IN (' . implode(', ', $placeholders) . ')'
                );
                $update->execute($parameters);
                if ($update->rowCount() !== 1) {
                    throw new RuntimeException('Password reset target disappeared.');
                }

                $deleteTokens = $this->db->prepare(
                    'DELETE FROM `password_reset_tokens` WHERE `userUuid` = :userUuid'
                );
                $deleteTokens->execute([':userUuid' => $userUuid]);

                $deleteLauncherSessions = $this->db->prepare(
                    'DELETE FROM `usersession` WHERE `userUuid` = :userUuid'
                );
                $deleteLauncherSessions->execute([':userUuid' => $userUuid]);
            });
            $this->respond('Пароль изменён. Теперь можно войти.', 'success');
        } catch (DomainException $exception) {
            $this->respond($exception->getMessage(), 'error', 422);
        } catch (Throwable) {
            $this->respond('Не удалось изменить пароль.', 'error', 500);
        }
    }

    private function respond(string $message, string $type, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=UTF-8');
        exit(json_encode([
            'message' => $message,
            'type' => $type,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
}
