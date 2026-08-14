<?php

declare(strict_types=1);

if (!defined('profile')) {
    http_response_code(403);
    exit('{"message":"Profile module is unavailable","type":"error"}');
}

final class EmailVerification
{
    private const TOKEN_TTL = 3600;

    public function __construct(
        private db $db,
        private Logger $logger,
        private HttpRequest $request,
        private UserSession $session,
        private array $config = [],
    ) {
    }

    public function requestVerification(): never
    {
        if (!$this->session->isLogged()) {
            $this->respond('Нужно войти в аккаунт.', 'error', 401);
        }
        CsrfToken::requireValid($this->request->csrfToken());

        $sessionUuid = $this->session->uuid();
        if (!Uuid::isValid($sessionUuid)) {
            $this->respond('Некорректный UUID пользователя.', 'error', 400);
        }

        $user = $this->loadUser($sessionUuid);
        if ($user === null) {
            $this->respond('Пользователь не найден.', 'error', 404);
        }
        if (($user['emailVerifiedAt'] ?? null) !== null) {
            $this->respond('Email уже подтверждён.', 'success');
        }

        $email = filter_var(mb_strtolower(trim((string)($user['email'] ?? ''))), FILTER_VALIDATE_EMAIL);
        if (!is_string($email)) {
            $this->respond('Сначала укажите и сохраните корректный email.', 'error', 422);
        }

        $userUuid = (string)$user['uuid'];
        $token = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $token);
        $expiresAt = CURRENT_TIME + self::TOKEN_TTL;

        try {
            $this->db->transactional(function () use ($userUuid, $email, $tokenHash, $expiresAt): void {
                $cleanup = $this->db->prepare(
                    'DELETE FROM `email_verification_tokens` WHERE `expiresAt` <= :currentTime OR `userUuid` = :userUuid'
                );
                $cleanup->execute([
                    ':currentTime' => CURRENT_TIME,
                    ':userUuid' => $userUuid,
                ]);

                $insert = $this->db->prepare(
                    'INSERT INTO `email_verification_tokens` (`userUuid`, `email`, `tokenHash`, `expiresAt`) '
                    . 'VALUES (:userUuid, :email, :tokenHash, :expiresAt)'
                );
                $insert->execute([
                    ':userUuid' => $userUuid,
                    ':email' => $email,
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
                $email,
                'Подтверждение email — FoxesCraft',
                'verify-email.html',
                [
                    'username' => (string)$user['login'],
                    'mail' => $email,
                    'verificationUrl' => $baseUrl . '/verify-email?token=' . rawurlencode($token),
                ],
            );
            if (!$sent) {
                $delete = $this->db->prepare(
                    'DELETE FROM `email_verification_tokens` WHERE `userUuid` = :userUuid AND `tokenHash` = :tokenHash'
                );
                $delete->execute([':userUuid' => $userUuid, ':tokenHash' => $tokenHash]);
                throw new RuntimeException(
                    $mailer->smtp_msg !== ''
                        ? 'Email verification delivery failed: ' . $mailer->smtp_msg
                        : 'Email verification delivery failed.'
                );
            }

            $this->logger->event(
                'email_verification.request.delivered',
                'Email verification instruction was generated and sent.',
                [
                    'component' => 'email_verification',
                    'operation' => 'request',
                    'targetUserUuid' => Uuid::normalize($userUuid),
                ],
                'INFO',
                'success',
            );
        } catch (Throwable $exception) {
            $this->logger->exception(
                'email_verification.request.failed',
                $exception,
                'Email verification request processing failed.',
                ['component' => 'email_verification'],
            );
            $this->respond('Не удалось отправить письмо подтверждения. Повторите попытку позже.', 'error', 503);
        }

        $this->respond('Письмо с подтверждением отправлено на ' . $email . '.', 'success');
    }

    public function verify(string $token): never
    {
        $token = strtolower(trim($token));
        if (preg_match('/^[0-9a-f]{64}$/D', $token) !== 1) {
            $this->respond('Ссылка подтверждения недействительна.', 'error', 422);
        }

        $tokenHash = hash('sha256', $token);
        $cleanup = $this->db->prepare(
            'DELETE FROM `email_verification_tokens` WHERE `expiresAt` <= :currentTime'
        );
        $cleanup->execute([':currentTime' => CURRENT_TIME]);

        $statement = $this->db->prepare(
            'SELECT `verification`.`userUuid`, `verification`.`email`, `user`.`email` AS `currentEmail` '
            . 'FROM `email_verification_tokens` AS `verification` '
            . 'INNER JOIN `users` AS `user` ON `user`.`uuid` = `verification`.`userUuid` '
            . 'WHERE `verification`.`tokenHash` = :tokenHash AND `verification`.`expiresAt` > :currentTime LIMIT 1'
        );
        $statement->execute([
            ':tokenHash' => $tokenHash,
            ':currentTime' => CURRENT_TIME,
        ]);
        $verification = $statement->fetch(PDO::FETCH_ASSOC);
        if (!is_array($verification)) {
            $this->respond('Ссылка подтверждения недействительна или уже истекла.', 'error', 422);
        }

        $email = mb_strtolower(trim((string)$verification['email']));
        $storedEmail = trim((string)$verification['currentEmail']);
        $currentEmail = mb_strtolower($storedEmail);
        if ($email === '' || $email !== $currentEmail) {
            $delete = $this->db->prepare('DELETE FROM `email_verification_tokens` WHERE `tokenHash` = :tokenHash');
            $delete->execute([':tokenHash' => $tokenHash]);
            $this->respond('Email был изменён. Запросите новую ссылку подтверждения.', 'error', 409);
        }

        try {
            $this->db->transactional(function () use ($verification, $storedEmail, $tokenHash): void {
                $update = $this->db->prepare(
                    'UPDATE `users` SET `emailVerifiedAt` = :verifiedAt '
                    . 'WHERE `uuid` = :userUuid AND `email` = :email'
                );
                $update->execute([
                    ':verifiedAt' => CURRENT_TIME,
                    ':userUuid' => (string)$verification['userUuid'],
                    ':email' => $storedEmail,
                ]);
                if ($update->rowCount() !== 1) {
                    throw new RuntimeException('Email verification target changed before confirmation.');
                }

                $delete = $this->db->prepare(
                    'DELETE FROM `email_verification_tokens` WHERE `userUuid` = :userUuid OR `tokenHash` = :tokenHash'
                );
                $delete->execute([
                    ':userUuid' => (string)$verification['userUuid'],
                    ':tokenHash' => $tokenHash,
                ]);
            });

            $this->logger->event(
                'email_verification.completed',
                'User email address verified.',
                [
                    'component' => 'email_verification',
                    'operation' => 'verify',
                    'targetUserUuid' => Uuid::normalize((string)$verification['userUuid']),
                ],
                'INFO',
                'success',
            );
        } catch (Throwable $exception) {
            $this->logger->exception(
                'email_verification.failed',
                $exception,
                'Email verification failed.',
                ['component' => 'email_verification'],
            );
            $this->respond('Не удалось подтвердить email. Повторите попытку позже.', 'error', 500);
        }

        $this->respond('Email успешно подтверждён.', 'success');
    }

    private function loadUser(string $userUuid): ?array
    {
        $placeholders = [];
        $parameters = [];
        foreach (Uuid::databaseCandidates($userUuid) as $index => $candidate) {
            $placeholder = ':userUuid_' . $index;
            $placeholders[] = $placeholder;
            $parameters[$placeholder] = $candidate;
        }

        $statement = $this->db->prepare(
            'SELECT `uuid`, `login`, `email`, `emailVerifiedAt` FROM `users` '
            . 'WHERE `uuid` IN (' . implode(', ', $placeholders) . ') LIMIT 1'
        );
        $statement->execute($parameters);
        $user = $statement->fetch(PDO::FETCH_ASSOC);
        return is_array($user) ? $user : null;
    }

    private function respond(string $message, string $type, int $status = 200): never
    {
        if ($status >= 400) {
            RequestTelemetry::rejectHttp('email_verification.rejected', $status, $message);
        }
        JsonResponse::send([
            'message' => $message,
            'type' => $type,
        ], $status);
    }
}
