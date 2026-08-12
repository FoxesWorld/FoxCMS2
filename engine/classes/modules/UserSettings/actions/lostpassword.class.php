<?php

declare(strict_types=1);

final class LostPassword
{
    public function __construct(
        private db $db,
        private Logger $logger,
        private array $config = [],
    )
    {
    }

    public function resetPass(string $mail): never
    {
        $email = filter_var(mb_strtolower(trim($mail)), FILTER_VALIDATE_EMAIL);
        if (!is_string($email)) {
            $this->accepted();
        }

        $statement = $this->db->prepare(
            'SELECT `uuid`, `login`, `email` FROM `users` WHERE `email` = :email LIMIT 1'
        );
        $statement->execute([':email' => $email]);
        $user = $statement->fetch(PDO::FETCH_ASSOC);
        if (!is_array($user)) {
            $this->accepted();
        }

        $userUuid = Uuid::normalize((string)$user['uuid']);
        $token = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $token);
        $expiresAt = CURRENT_TIME + 3600;

        try {
            $this->db->transactional(function () use ($userUuid, $tokenHash, $expiresAt): void {
                $cleanup = $this->db->prepare(
                    'DELETE FROM `password_reset_tokens` WHERE `expiresAt` <= :currentTime OR `userUuid` = :userUuid'
                );
                $cleanup->execute([
                    ':currentTime' => CURRENT_TIME,
                    ':userUuid' => $userUuid,
                ]);
                $insert = $this->db->prepare(
                    'INSERT INTO `password_reset_tokens` (`userUuid`, `tokenHash`, `expiresAt`) '
                    . 'VALUES (:userUuid, :tokenHash, :expiresAt)'
                );
                $insert->execute([
                    ':userUuid' => $userUuid,
                    ':tokenHash' => $tokenHash,
                    ':expiresAt' => $expiresAt,
                ]);
            });

            UtilityLoader::load('FoxMail', '1.0.0');
            $baseUrl = rtrim((string)($this->config['environment']['publicBaseUrl'] ?? ''), '/');
            if ($baseUrl === '') {
                $baseUrl = rtrim((string)foxEnv('FOXESCRAFT_PUBLIC_BASE_URL', 'http://localhost'), '/');
            }
            $settings = is_array($this->config['siteSettings'] ?? null)
                ? $this->config['siteSettings']
                : [];
            $mailer = new FoxMail(true, $settings);
            $sent = $mailer->send(
                (string)$user['email'],
                'Сброс пароля',
                'lostpass.html',
                [
                    'username' => (string)$user['login'],
                    'mail' => (string)$user['email'],
                    'resetToken' => $baseUrl . '/reset-password?token=' . rawurlencode($token),
                ],
            );
            if (!$sent) {
                throw new RuntimeException(
                    $mailer->smtp_msg !== ''
                        ? 'Password reset mail delivery failed: ' . $mailer->smtp_msg
                        : 'Password reset mail delivery failed.'
                );
            }
            $this->logger->event(
                'password_reset.request.delivered',
                'Password reset instruction was generated and sent.',
                [
                    'component' => 'password_reset',
                    'operation' => 'request',
                    'targetUserUuid' => $userUuid,
                ],
                'INFO',
                'success',
            );
        } catch (Throwable $exception) {
            $this->logger->exception(
                'password_reset.request.failed',
                $exception,
                'Password reset request processing failed.',
                ['component' => 'password_reset'],
            );
        }

        $this->accepted();
    }

    private function accepted(): never
    {
        $this->respond('Если адрес связан с аккаунтом, инструкция будет отправлена.', 'success');
    }

    private function respond(string $message, string $type): never
    {
        JsonResponse::send([
            'message' => $message,
            'type' => $type,
        ]);
    }
}
