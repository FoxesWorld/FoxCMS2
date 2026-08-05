<?php

declare(strict_types=1);

final class ResetPassword
{
    public function __construct(
        private db $db,
        private Logger $logger,
        private HttpRequest $request,
    ) {
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
            $userUuid = $this->db->transactional(function () use ($token, $password): string {
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
                return $userUuid;
            });
            try {
                $context = (new LoginContextResolver())->resolve($this->request);
                (new NotificationService($this->db))->notifyPasswordChanged(
                    $userUuid,
                    'recovery',
                    $context,
                );
            } catch (Throwable $notificationError) {
                $this->logger->exception(
                    'notifications.password_reset.failed',
                    $notificationError,
                    'Password reset completed, but its security notification could not be recorded.',
                    [
                        'component' => 'notifications',
                        'operation' => 'password_reset',
                        'targetUserUuid' => $userUuid,
                    ],
                );
            }
            $this->logger->event(
                'password_reset.completed',
                'Password reset completed and active sessions were revoked.',
                [
                    'component' => 'password_reset',
                    'operation' => 'reset',
                    'targetUserUuid' => $userUuid,
                ],
                'INFO',
                'success',
            );
            $this->respond('Пароль изменён. Теперь можно войти.', 'success');
        } catch (DomainException $exception) {
            $this->respond($exception->getMessage(), 'error', 422);
        } catch (Throwable $error) {
            $this->logger->exception(
                'password_reset.failed',
                $error,
                'Password reset failed unexpectedly.',
                ['component' => 'password_reset'],
            );
            $this->respond('Не удалось изменить пароль.', 'error', 500);
        }
    }

    private function respond(string $message, string $type, int $status = 200): never
    {
        if ($status >= 400) {
            RequestTelemetry::rejectHttp('password_reset.rejected', $status, $message);
        }
        JsonResponse::send([
            'message' => $message,
            'type' => $type,
        ], $status);
    }
}
