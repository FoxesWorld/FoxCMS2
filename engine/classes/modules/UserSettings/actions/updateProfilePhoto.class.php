<?php

declare(strict_types=1);

final class UpdateProfilePhoto
{
    private const MAX_FILE_SIZE = 5 * 1024 * 1024;
    private const MAX_DIMENSION = 4096;
    private const MIME_EXTENSIONS = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
    ];

    public function __construct(
        private db $db,
        private HttpRequest $request,
        private UserSession $session,
    ) {
    }

    public function upload(): never
    {
        if (!$this->session->isLogged()) {
            $this->respond('Нужно войти в аккаунт.', 'error', null, 401);
        }
        CsrfToken::requireValid($this->request->csrfToken());

        $target = $this->resolveTarget();
        $file = $this->request->file('image');
        if ($file === null) {
            $this->respond('Файл не выбран.', 'error', null, 400);
        }
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file((string)$file['tmp_name'])) {
            $this->respond('Файл не был загружен.', 'error', null, 400);
        }
        if (($file['size'] ?? 0) <= 0 || $file['size'] > self::MAX_FILE_SIZE) {
            $this->respond('Размер изображения должен быть не больше 5 МБ.', 'error', null, 400);
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file((string)$file['tmp_name']);
        $extension = self::MIME_EXTENSIONS[$mime] ?? null;
        if ($extension === null) {
            $this->respond('Разрешены JPEG, PNG, WebP и GIF.', 'error', null, 400);
        }

        $dimensions = @getimagesize((string)$file['tmp_name']);
        if (!$dimensions
            || $dimensions[0] < 64
            || $dimensions[1] < 64
            || $dimensions[0] > self::MAX_DIMENSION
            || $dimensions[1] > self::MAX_DIMENSION) {
            $this->respond('Изображение должно быть от 64×64 до 4096×4096 пикселей.', 'error', null, 400);
        }

        $targetUuid = (string)$target['uuid'];
        $storageUuid = (string)$target['storageUuid'];
        $directory = ROOT_DIR . UPLOADS_DIR . USR_SUBFOLDER . $targetUuid . DIRECTORY_SEPARATOR;
        if (!is_dir($directory) && !mkdir($directory, 0755, true)) {
            $this->respond('Не удалось создать каталог пользователя.', 'error', null, 500);
        }

        $destination = $directory . 'profilePhoto.' . $extension;
        if (!move_uploaded_file((string)$file['tmp_name'], $destination)) {
            $this->respond('Не удалось сохранить изображение.', 'error', null, 500);
        }
        @chmod($destination, 0644);

        $publicFolder = rtrim(UPLOADS_DIR, '/')
            . '/' . trim(USR_SUBFOLDER, '/')
            . '/' . rawurlencode($targetUuid) . '/';
        $publicPath = $publicFolder . 'profilePhoto.' . $extension;
        $currentPath = (string)($target['profilePhoto'] ?? '');

        try {
            $statement = $this->db->prepare(
                'UPDATE `users` SET `profilePhoto` = :path WHERE `uuid` = :userUuid'
            );
            $statement->execute([
                ':path' => $publicPath,
                ':userUuid' => $storageUuid,
            ]);
        } catch (Throwable) {
            @unlink($destination);
            $this->respond('Не удалось обновить профиль.', 'error', null, 500);
        }

        if ($currentPath !== ''
            && str_starts_with($currentPath, $publicFolder)
            && $currentPath !== $publicPath) {
            $oldAbsolutePath = ROOT_DIR . $currentPath;
            if (is_file($oldAbsolutePath)) {
                @unlink($oldAbsolutePath);
            }
        }

        if (Uuid::equals($this->session->uuid(), $targetUuid)) {
            $this->session->set('profilePhoto', $publicPath, true);
        }

        $this->respond('Фото профиля обновлено.', 'success', $publicPath . '?v=' . time());
    }

    /** @return array{uuid:string,storageUuid:string,profilePhoto:string|null} */
    private function resolveTarget(): array
    {
        $sessionUuid = $this->session->uuid();
        if (!Uuid::isValid($sessionUuid)) {
            $this->respond('Некорректный аккаунт.', 'error', null, 400);
        }

        $requestedUuid = $this->request->string('userUuid');
        if ($requestedUuid !== '' && !Uuid::isValid($requestedUuid)) {
            $this->respond('Некорректный UUID пользователя.', 'error', null, 400);
        }

        $targetIdentity = $requestedUuid !== '' ? $requestedUuid : $sessionUuid;
        if (!$this->session->isAdmin() && !Uuid::equals($sessionUuid, $targetIdentity)) {
            $this->respond('Недостаточно прав для изменения этого профиля.', 'error', null, 403);
        }

        $placeholders = [];
        $parameters = [];
        foreach (Uuid::databaseCandidates($targetIdentity) as $index => $candidate) {
            $placeholder = ':userUuid_' . $index;
            $placeholders[] = $placeholder;
            $parameters[$placeholder] = $candidate;
        }

        $statement = $this->db->prepare(
            'SELECT `uuid`, `profilePhoto` FROM `users` '
            . 'WHERE `uuid` IN (' . implode(', ', $placeholders) . ') LIMIT 1'
        );
        $statement->execute($parameters);
        $target = $statement->fetch(PDO::FETCH_ASSOC);
        if (!is_array($target)) {
            $this->respond('Пользователь не найден.', 'error', null, 404);
        }

        $storageUuid = (string)$target['uuid'];
        return [
            'uuid' => Uuid::normalize($storageUuid),
            'storageUuid' => $storageUuid,
            'profilePhoto' => isset($target['profilePhoto']) ? (string)$target['profilePhoto'] : null,
        ];
    }

    private function respond(string $message, string $type, ?string $url = null, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=UTF-8');
        header('Cache-Control: no-store');
        $response = ['message' => $message, 'type' => $type];
        if ($url !== null) {
            $response['url'] = $url;
        }
        exit(json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
}
