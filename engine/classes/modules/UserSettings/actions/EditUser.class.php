<?php

declare(strict_types=1);

if (!defined('profile')) {
    http_response_code(403);
    exit('{"message":"Profile module is unavailable","type":"error"}');
}

final class EditUser
{
    private const USER_FIELDS = ['login', 'realname', 'email', 'userStatus', 'land', 'colorScheme'];
    private const ADMIN_FIELDS = ['reg_date'];

    public function __construct(
        private HttpRequest $request,
        private db $db,
        private Logger $logger,
        private UserSession $session,
    ) {
    }

    public function update(): never
    {
        RequestTelemetry::identify('profile.update', ['component' => 'profile']);
        try {
            $result = $this->updateProfile();
            $this->logger->event(
                'profile.update.completed',
                'User profile updated.',
                array_merge(['component' => 'profile'], $result),
                'INFO',
                'success',
            );
            $this->respond('Профиль обновлён.', 'success');
        } catch (DomainException | InvalidArgumentException $exception) {
            $this->respond($exception->getMessage(), 'error', 422);
        } catch (Throwable $exception) {
            $this->logger->exception(
                'profile.update.failed',
                $exception,
                'Profile update failed unexpectedly.',
                ['component' => 'profile'],
            );
            $requestId = RequestTelemetry::requestId();
            if ($requestId === '') {
                $requestId = ExceptionContext::requestId('profile-update');
            }
            JsonResponse::send(
                \FoxCMS\Shared\Error\ThrowableDiagnostic::payload(
                    $exception,
                    $requestId,
                    defined('ROOT_DIR') ? ROOT_DIR : '',
                    false,
                    ['type' => 'error', 'operation' => 'profile_update'],
                ),
                500,
            );
        }
    }

    /** @return array{targetUserUuid:string,fields:list<string>,passwordChanged:bool} */
    private function updateProfile(): array
    {
        if (!$this->session->isLogged()) {
            throw new DomainException('Нужно войти в аккаунт.');
        }
        CsrfToken::requireValid($this->request->csrfToken());

        $isAdmin = $this->session->isAdmin();
        $sessionUuid = $this->session->uuid();
        $requestedUuid = $this->request->string('userUuid');
        if (!Uuid::isValid($sessionUuid) || ($requestedUuid !== '' && !Uuid::isValid($requestedUuid))) {
            throw new InvalidArgumentException('Некорректный UUID пользователя.');
        }
        $targetUuid = Uuid::normalize($requestedUuid !== '' ? $requestedUuid : $sessionUuid);
        if (!$isAdmin && !Uuid::equals($sessionUuid, $targetUuid)) {
            throw new DomainException('Недостаточно прав для изменения этого профиля.');
        }

        $target = $this->loadTarget($targetUuid);
        if ($target === null) {
            throw new DomainException('Пользователь не найден.');
        }
        $storageUuid = (string)$target['uuid'];
        if (!$isAdmin) {
            $currentPassword = $this->request->string('password');
            if ($currentPassword === '' || !authorize::passVerify($currentPassword, (string)$target['password'])) {
                throw new DomainException('Текущий пароль указан неверно.');
            }
        }

        $updates = $this->validateFields($isAdmin, $storageUuid);
        $passwordHash = $this->validateNewPassword();
        if ($passwordHash !== null) {
            $updates['password'] = $passwordHash;
            $updates['token'] = '';
        }
        if ($updates === []) {
            throw new DomainException('Нет данных для обновления.');
        }

        $parts = [];
        $parameters = [':userUuid' => $storageUuid];
        foreach ($updates as $field => $value) {
            $placeholder = ':field_' . $field;
            $parts[] = '`' . $field . '` = ' . $placeholder;
            $parameters[$placeholder] = $value;
        }
        $statement = $this->db->prepare(
            'UPDATE `users` SET ' . implode(', ', $parts) . ' WHERE `uuid` = :userUuid'
        );
        $statement->execute($parameters);
        if ($statement->rowCount() > 1) {
            throw new RuntimeException('UUID update affected more than one user.');
        }

        $passwordChanged = array_key_exists('password', $updates);
        if ($passwordChanged) {
            try {
                $context = (new LoginContextResolver())->resolve($this->request);
                (new NotificationService($this->db))->notifyPasswordChanged(
                    $targetUuid,
                    $isAdmin && !Uuid::equals($sessionUuid, $targetUuid) ? 'administrator' : 'profile',
                    $context,
                );
            } catch (Throwable $error) {
                $this->logger->exception(
                    'notifications.password_change.failed',
                    $error,
                    'Profile password changed, but its security notification could not be recorded.',
                    [
                        'component' => 'notifications',
                        'operation' => 'password_changed',
                        'targetUserUuid' => $targetUuid,
                    ],
                );
            }
        }

        if (Uuid::equals($sessionUuid, $targetUuid)) {
            $this->session->refreshFromDatabase();
            RequestTelemetry::annotate([
                'actorUuid' => $this->session->uuid(),
                'actorLogin' => $this->session->login(),
                'actorGroup' => $this->session->group(),
            ]);
        }

        return [
            'targetUserUuid' => $targetUuid,
            'fields' => array_values(array_diff(array_keys($updates), ['password', 'token'])),
            'passwordChanged' => $passwordChanged,
        ];
    }

    private function loadTarget(string $userUuid): ?array
    {
        $placeholders = [];
        $parameters = [];
        foreach (Uuid::databaseCandidates($userUuid) as $index => $candidate) {
            $placeholder = ':userUuid_' . $index;
            $placeholders[] = $placeholder;
            $parameters[$placeholder] = $candidate;
        }

        $statement = $this->db->prepare(
            'SELECT `uuid`, `login`, `password`, `groupTag`, `email`, `reg_date` '
            . 'FROM `users` WHERE `uuid` IN (' . implode(', ', $placeholders) . ') LIMIT 1'
        );
        $statement->execute($parameters);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    private function validateFields(bool $isAdmin, string $targetUuid): array
    {
        $updates = [];
        $fields = $isAdmin ? array_merge(self::USER_FIELDS, self::ADMIN_FIELDS) : self::USER_FIELDS;
        foreach ($fields as $field) {
            if (!$this->request->has($field)) {
                continue;
            }
            $value = $this->request->input($field);
            $value = is_string($value) ? trim($value) : $value;

            switch ($field) {
                case 'login':
                    $value = (string)$value;
                    if (preg_match('/^[A-Za-z0-9_.-]{3,64}$/D', $value) !== 1) {
                        throw new InvalidArgumentException('Логин должен содержать 3–64 латинских символа, цифры, точки, дефисы или подчёркивания.');
                    }
                    $duplicate = $this->db->prepare(
                        'SELECT `uuid` FROM `users` WHERE `login` = :login AND `uuid` <> :userUuid LIMIT 1'
                    );
                    $duplicate->execute([':login' => $value, ':userUuid' => $targetUuid]);
                    if ($duplicate->fetchColumn() !== false) {
                        throw new DomainException('Этот логин уже используется.');
                    }
                    break;
                case 'email':
                    $value = mb_strtolower((string)$value);
                    if (filter_var($value, FILTER_VALIDATE_EMAIL) === false || mb_strlen($value) > 254) {
                        throw new InvalidArgumentException('Укажите корректный email.');
                    }
                    $duplicate = $this->db->prepare(
                        'SELECT `uuid` FROM `users` WHERE `email` = :email AND `uuid` <> :userUuid LIMIT 1'
                    );
                    $duplicate->execute([':email' => $value, ':userUuid' => $targetUuid]);
                    if ($duplicate->fetchColumn() !== false) {
                        throw new DomainException('Эта почта уже используется.');
                    }
                    break;
                case 'realname':
                    if (mb_strlen((string)$value) > 64) {
                        throw new InvalidArgumentException('Имя слишком длинное.');
                    }
                    break;
                case 'userStatus':
                    if (mb_strlen((string)$value) > 128) {
                        throw new InvalidArgumentException('Статус должен быть не длиннее 128 символов.');
                    }
                    break;
                case 'land':
                    if (mb_strlen((string)$value) > 64) {
                        throw new InvalidArgumentException('Название региона слишком длинное.');
                    }
                    break;
                case 'colorScheme':
                    if (preg_match('/^#[0-9a-f]{6}$/iD', (string)$value) !== 1) {
                        throw new InvalidArgumentException('Некорректный цвет профиля.');
                    }
                    break;
                case 'reg_date':
                    $value = filter_var($value, FILTER_VALIDATE_INT);
                    if ($value === false || $value < 0) {
                        throw new InvalidArgumentException('Некорректная дата регистрации.');
                    }
                    break;
            }
            $updates[$field] = $value;
        }
        return $updates;
    }

    private function validateNewPassword(): ?string
    {
        $newPassword = $this->request->string('newPass');
        $confirmation = $this->request->string('repeatPass');
        if ($newPassword === '' && $confirmation === '') {
            return null;
        }
        if ($newPassword !== $confirmation) {
            throw new DomainException('Новые пароли не совпадают.');
        }
        if (strlen($newPassword) < 10 || strlen($newPassword) > 72 || preg_match('/[А-Яа-яЁё]/u', $newPassword)) {
            throw new InvalidArgumentException('Пароль должен содержать от 10 до 72 символов без кириллицы.');
        }
        return authorize::hashPassword($newPassword);
    }

    private function respond(string $message, string $type, int $status = 200): never
    {
        if ($status >= 400) {
            RequestTelemetry::rejectHttp('profile.update.rejected', $status, $message);
        }
        JsonResponse::send([
            'message' => $message,
            'type' => $type,
        ], $status);
    }
}
