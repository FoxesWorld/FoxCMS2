<?php

declare(strict_types=1);

final class UploadService
{
    private const IMAGE_EXTENSIONS = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
        'image/avif' => 'avif',
    ];

    public function __construct(
        private db $db,
        private UserSession $session,
        private Logger $logger,
        private HttpRequest $request,
    ) {
    }

    public function store(string $purpose, ?array $file, array $context = []): UploadResult
    {
        $purpose = trim($purpose);
        $audit = $this->baseAudit($purpose, $file, $context);
        try {
            $purpose = UploadPurpose::assert($purpose);
            $this->assertRequestSecurity($purpose);
            $policy = $this->policy($purpose, $context);
            $inspected = $this->inspect($file, $policy);
            $directory = $this->resolveDestinationDirectory($policy, $context);
            $storedName = $this->storedName($purpose, $inspected, $context);
            $destination = $this->resolveNewFilePath($directory, $storedName);
            $result = $this->publish(
                $purpose,
                $inspected,
                $destination,
                (bool)($policy['overwrite'] ?? false),
            );

            $this->logger->logInfo('Upload accepted.', array_merge($audit, [
                'event' => 'upload.accepted',
                'purpose' => $purpose,
                'mime' => $result->mime(),
                'size' => $result->size(),
                'target' => $result->relativePath(),
                'sha256Prefix' => substr($result->sha256(), 0, 16),
            ]));
            return $result;
        } catch (UploadException $error) {
            $this->logger->logWarn('Upload rejected.', array_merge(
                $audit,
                ['event' => 'upload.rejected', 'reason' => $error->getMessage(), 'status' => $error->httpStatus()],
                $error->auditContext(),
            ));
            throw $error;
        } catch (Throwable $error) {
            $this->logger->logError('Upload failed unexpectedly.', array_merge($audit, [
                'event' => 'upload.failed',
                'exception' => $error::class,
                'reason' => $error->getMessage(),
            ]));
            throw new UploadException('Не удалось сохранить загруженный файл.', 500, [], $error);
        }
    }

    public function validateReference(string $purpose, string $publicPath, array $context = []): string
    {
        $purpose = UploadPurpose::assert($purpose);
        $policy = $this->policy($purpose, $context, false);
        $relative = $this->relativeFromPublicPath($publicPath);
        $expectedDirectory = $this->policyDirectory($policy, $context);
        if ($expectedDirectory !== ''
            && $relative !== $expectedDirectory
            && !str_starts_with($relative, $expectedDirectory . '/')) {
            throw new UploadException('Файл не относится к разрешённому назначению.', 403, [
                'purpose' => $purpose,
                'path' => $relative,
            ]);
        }

        $root = $this->uploadsRoot();
        $this->rejectSymlinkPath($relative, $root);
        $candidate = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
        $resolved = realpath($candidate);
        if (!is_string($resolved) || !is_file($resolved) || !$this->insideRoot($resolved, $root)) {
            throw new UploadException('Файл загрузки не найден.', 404, ['purpose' => $purpose, 'path' => $relative]);
        }
        $mime = $this->detectMime($resolved, ($policy['allowAnyType'] ?? false) === true);
        $this->assertMimeForPolicy($mime, $policy, pathinfo($resolved, PATHINFO_EXTENSION));
        return $this->publicPath($relative);
    }

    public function removeReference(string $purpose, string $publicPath, array $context = []): void
    {
        if (trim($publicPath) === '') {
            return;
        }
        $normalized = $this->validateReference($purpose, $publicPath, $context);
        $relative = $this->relativeFromPublicPath($normalized);
        $root = $this->uploadsRoot();
        $path = realpath($root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative));
        if (is_string($path) && is_file($path) && $this->insideRoot($path, $root)) {
            @unlink($path);
        }
    }

    private function assertRequestSecurity(string $purpose): void
    {
        if (!$this->request->isPost()) {
            throw new UploadException('Для загрузки требуется POST-запрос.', 405, ['purpose' => $purpose]);
        }
        if (!$this->session->isLogged()) {
            throw new UploadException('Для загрузки необходимо войти в аккаунт.', 401, ['purpose' => $purpose]);
        }
        if (!CsrfToken::validate($this->request->csrfToken())) {
            throw new UploadException('Недействительный токен защиты запроса.', 403, ['purpose' => $purpose]);
        }
    }

    private function policy(string $purpose, array $context, bool $authorize = true): array
    {
        $ownerUuid = '';
        if (in_array($purpose, [UploadPurpose::PROFILE_PHOTO, UploadPurpose::MINECRAFT_SKIN, UploadPurpose::MINECRAFT_CAPE], true)) {
            $ownerUuid = $this->ownerUuid($context);
            if ($authorize) {
                $this->assertOwnerOrPermission($ownerUuid, match ($purpose) {
                    UploadPurpose::PROFILE_PHOTO => UploadPermission::PROFILE_ANY,
                    default => UploadPermission::MINECRAFT_ANY,
                });
            }
        }

        return match ($purpose) {
            UploadPurpose::NEWS_COVER => $this->adminImagePolicy(
                'news',
                8_388_608,
                64,
                8192,
                UploadPermission::NEWS_COVER,
                $authorize,
            ),
            UploadPurpose::SLIDER_IMAGE => $this->adminImagePolicy(
                'slides',
                12_582_912,
                320,
                8192,
                UploadPermission::SLIDER_IMAGE,
                $authorize,
            ),
            UploadPurpose::SERVER_IMAGE => $this->serverImagePolicy($authorize),
            UploadPurpose::PROFILE_PHOTO => [
                'directory' => 'users/' . Uuid::canonical($ownerUuid),
                'createDirectory' => true,
                'maximumBytes' => 5_242_880,
                'mimeExtensions' => array_diff_key(self::IMAGE_EXTENSIONS, ['image/avif' => true]),
                'minimumWidth' => 64,
                'minimumHeight' => 64,
                'maximumWidth' => 4096,
                'maximumHeight' => 4096,
                'maximumPixels' => 12_000_000,
                'overwrite' => false,
                'image' => true,
            ],
            UploadPurpose::MINECRAFT_SKIN, UploadPurpose::MINECRAFT_CAPE => [
                'directory' => 'users/' . Uuid::canonical($ownerUuid),
                'createDirectory' => true,
                'maximumBytes' => 2_097_152,
                'mimeExtensions' => ['image/png' => 'png'],
                'minimumWidth' => 16,
                'minimumHeight' => 16,
                'maximumWidth' => 1024,
                'maximumHeight' => 1024,
                'maximumPixels' => 1_048_576,
                'overwrite' => true,
                'image' => true,
                'minecraftType' => $purpose === UploadPurpose::MINECRAFT_SKIN ? 'skin' : 'cape',
            ],
            UploadPurpose::ADMIN_FILE => $this->adminFilePolicy($authorize),
            default => throw new UploadException('Неизвестное назначение загрузки.', 400),
        };
    }

    private function adminImagePolicy(
        string $directory,
        int $maximumBytes,
        int $minimumDimension,
        int $maximumDimension,
        string $permission,
        bool $authorize,
    ): array {
        if ($authorize) {
            $this->assertAdminOrPermission($permission);
        }
        return [
            'directory' => $directory,
            'createDirectory' => true,
            'maximumBytes' => $maximumBytes,
            'mimeExtensions' => self::IMAGE_EXTENSIONS,
            'minimumWidth' => $minimumDimension,
            'minimumHeight' => $minimumDimension,
            'maximumWidth' => $maximumDimension,
            'maximumHeight' => $maximumDimension,
            'maximumPixels' => $directory === 'news' ? 24_000_000 : $maximumDimension * $maximumDimension,
            'overwrite' => false,
            'image' => true,
        ];
    }

    private function serverImagePolicy(bool $authorize): array
    {
        if ($authorize) {
            $this->assertAdminOrPermission(UploadPermission::SERVER_IMAGE);
        }
        return [
            'directory' => 'servers',
            'createDirectory' => true,
            'maximumBytes' => 12_582_912,
            'mimeExtensions' => [
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/webp' => 'webp',
            ],
            'minimumWidth' => 320,
            'minimumHeight' => 180,
            'maximumWidth' => 8192,
            'maximumHeight' => 8192,
            'maximumPixels' => 33_554_432,
            'overwrite' => false,
            'image' => true,
        ];
    }

    private function adminFilePolicy(bool $authorize): array
    {
        if ($authorize) {
            $this->assertAdminOrPermission(UploadPermission::ADMIN_FILES);
        }
        return [
            'directory' => null,
            'createDirectory' => false,
            'maximumBytes' => 67_108_864,
            'allowAnyType' => true,
            'overwrite' => false,
            'image' => false,
        ];
    }

    private function assertOwnerOrPermission(string $ownerUuid, string $permission): void
    {
        if (Uuid::equals($this->session->uuid(), $ownerUuid)
            || $this->session->isAdmin()
            || $this->hasPermission($permission)) {
            return;
        }
        throw new UploadException('Недостаточно прав для загрузки в каталог другого пользователя.', 403, [
            'ownerUuid' => $ownerUuid,
            'requiredPermission' => $permission,
        ]);
    }

    private function assertAdminOrPermission(string $permission): void
    {
        if ($this->session->isAdmin() || $this->hasPermission($permission)) {
            return;
        }
        throw new UploadException('Недостаточно прав для этого назначения загрузки.', 403, [
            'requiredPermission' => $permission,
        ]);
    }

    private function hasPermission(string $permission): bool
    {
        $raw = $this->session->get('userPerms', '{}');
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            $permissions = is_array($decoded) ? $decoded : [];
        } elseif (is_array($raw)) {
            $permissions = $raw;
        } else {
            $permissions = [];
        }
        if (in_array('*', $permissions, true) || in_array($permission, $permissions, true)) {
            return true;
        }
        if (($permissions['*'] ?? false) === true || ($permissions[$permission] ?? false) === true) {
            return true;
        }
        $segments = explode('.', $permission);
        $cursor = $permissions;
        foreach ($segments as $segment) {
            if (!is_array($cursor) || !array_key_exists($segment, $cursor)) {
                return false;
            }
            $cursor = $cursor[$segment];
        }
        return $cursor === true;
    }

    private function inspect(?array $file, array $policy): array
    {
        if (!is_array($file)) {
            throw new UploadException('Файл не передан.', 400);
        }
        $error = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error !== UPLOAD_ERR_OK) {
            throw new UploadException($this->uploadErrorMessage($error), 400, ['uploadError' => $error]);
        }
        $temporary = (string)($file['tmp_name'] ?? '');
        if ($temporary === '' || !is_uploaded_file($temporary)) {
            throw new UploadException('Переданный файл не является HTTP-загрузкой.', 400);
        }
        $actualSize = filesize($temporary);
        $reportedSize = max(0, (int)($file['size'] ?? 0));
        $maximumBytes = max(1, (int)($policy['maximumBytes'] ?? 1));
        if (!is_int($actualSize) || $actualSize < 1 || $actualSize > $maximumBytes) {
            throw new UploadException('Файл пуст или превышает допустимый размер.', 413, [
                'actualSize' => is_int($actualSize) ? $actualSize : -1,
                'maximumBytes' => $maximumBytes,
            ]);
        }
        if ($reportedSize > 0 && $reportedSize !== $actualSize) {
            throw new UploadException('Размер загруженного файла не совпадает с заявленным.', 400, [
                'reportedSize' => $reportedSize,
                'actualSize' => $actualSize,
            ]);
        }

        $originalName = $this->safeFileName(basename(str_replace('\\', '/', (string)($file['name'] ?? 'upload.bin'))));
        $mime = $this->detectMime($temporary, ($policy['allowAnyType'] ?? false) === true);
        $originalExtension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $extension = $this->assertMimeForPolicy($mime, $policy, $originalExtension);

        if (($policy['image'] ?? false) === true) {
            $dimensions = @getimagesize($temporary);
            if (!is_array($dimensions)) {
                throw new UploadException('Декодер изображений отклонил файл.', 422, ['mime' => $mime]);
            }
            $width = (int)($dimensions[0] ?? 0);
            $height = (int)($dimensions[1] ?? 0);
            if ($width < (int)$policy['minimumWidth'] || $height < (int)$policy['minimumHeight']
                || $width > (int)$policy['maximumWidth'] || $height > (int)$policy['maximumHeight']) {
                throw new UploadException('Недопустимые размеры изображения.', 422, [
                    'width' => $width,
                    'height' => $height,
                    'minimumWidth' => (int)$policy['minimumWidth'],
                    'minimumHeight' => (int)$policy['minimumHeight'],
                    'maximumWidth' => (int)$policy['maximumWidth'],
                    'maximumHeight' => (int)$policy['maximumHeight'],
                ]);
            }
            $pixels = $width * $height;
            $maximumPixels = max(1, (int)($policy['maximumPixels'] ?? 12_000_000));
            if ($pixels > $maximumPixels) {
                throw new UploadException('Изображение содержит слишком много пикселей.', 422, [
                    'width' => $width,
                    'height' => $height,
                    'pixels' => $pixels,
                    'maximumPixels' => $maximumPixels,
                ]);
            }
            $minecraftType = (string)($policy['minecraftType'] ?? '');
            if ($minecraftType !== '' && !$this->validMinecraftDimensions($minecraftType, $width, $height)) {
                throw new UploadException('Размеры текстуры не поддерживаются Minecraft.', 422, [
                    'textureType' => $minecraftType,
                    'width' => $width,
                    'height' => $height,
                ]);
            }
            $this->probeImage($temporary, $actualSize, $pixels);
        }

        $hash = hash_file('sha256', $temporary);
        if (!is_string($hash) || strlen($hash) !== 64) {
            throw new UploadException('Не удалось проверить целостность файла.', 500);
        }

        return [
            'temporary' => $temporary,
            'originalName' => $originalName,
            'mime' => $mime,
            'extension' => $extension,
            'size' => $actualSize,
            'sha256' => $hash,
        ];
    }

    private function assertMimeForPolicy(string $mime, array $policy, string $originalExtension): string
    {
        $mimeExtensions = $policy['mimeExtensions'] ?? null;
        if (is_array($mimeExtensions)) {
            $extension = $mimeExtensions[$mime] ?? null;
            if (!is_string($extension) || $extension === '') {
                throw new UploadException('Тип файла не разрешён для этого назначения.', 415, ['mime' => $mime]);
            }
            return $extension;
        }
        $blockedMime = is_array($policy['blockedMime'] ?? null) ? $policy['blockedMime'] : [];
        if (in_array(strtolower($mime), array_map('strtolower', $blockedMime), true)) {
            throw new UploadException('Этот MIME-тип запрещён для загрузки.', 415, ['mime' => $mime]);
        }
        $blockedExtensions = is_array($policy['blockedExtensions'] ?? null) ? $policy['blockedExtensions'] : [];
        if ($originalExtension !== '' && in_array(strtolower($originalExtension), $blockedExtensions, true)) {
            throw new UploadException('Это расширение запрещено для загрузки.', 415, ['extension' => $originalExtension]);
        }
        return $originalExtension;
    }

    private function resolveDestinationDirectory(array $policy, array $context): string
    {
        $root = $this->uploadsRoot();
        $relative = $this->policyDirectory($policy, $context);
        $this->rejectSymlinkPath($relative, $root);
        $candidate = $relative === ''
            ? $root
            : $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);

        if (($policy['createDirectory'] ?? false) === true) {
            if (!is_dir($candidate) && !mkdir($candidate, 0755, true) && !is_dir($candidate)) {
                throw new UploadException('Не удалось создать целевой каталог.', 500, ['directory' => $relative]);
            }
        }
        $resolved = realpath($candidate);
        if (!is_string($resolved) || !is_dir($resolved) || is_link($candidate) || !$this->insideRoot($resolved, $root)) {
            throw new UploadException('Загрузка в указанный каталог запрещена.', 403, ['directory' => $relative]);
        }
        if (!is_writable($resolved)) {
            throw new UploadException('Целевой каталог недоступен для записи.', 503, ['directory' => $relative]);
        }
        return $resolved;
    }

    private function policyDirectory(array $policy, array $context): string
    {
        if (is_string($policy['directory'] ?? null)) {
            return $this->safeRelativePath((string)$policy['directory']);
        }
        return $this->safeRelativePath((string)($context['directory'] ?? ''));
    }

    private function storedName(string $purpose, array $inspected, array $context): string
    {
        $extension = (string)$inspected['extension'];
        return match ($purpose) {
            UploadPurpose::NEWS_COVER => 'news-' . bin2hex(random_bytes(16)) . '.' . $extension,
            UploadPurpose::SLIDER_IMAGE => 'slide-' . bin2hex(random_bytes(16)) . '.' . $extension,
            UploadPurpose::SERVER_IMAGE => 'server-' . bin2hex(random_bytes(16)) . '.' . $extension,
            UploadPurpose::PROFILE_PHOTO => 'profile-photo-' . bin2hex(random_bytes(12)) . '.' . $extension,
            UploadPurpose::MINECRAFT_SKIN => Uuid::canonical($this->ownerUuid($context)) . '-skin.png',
            UploadPurpose::MINECRAFT_CAPE => Uuid::canonical($this->ownerUuid($context)) . '-cape.png',
            UploadPurpose::ADMIN_FILE => $this->safeFileName((string)$inspected['originalName']),
            default => throw new UploadException('Неизвестное назначение загрузки.', 400),
        };
    }

    private function publish(string $purpose, array $inspected, string $destination, bool $overwrite): UploadResult
    {
        if (file_exists($destination) && !$overwrite) {
            throw new UploadException('Файл с таким именем уже существует.', 409, [
                'target' => $this->relativePath($destination),
            ]);
        }
        $directory = dirname($destination);
        $staging = $directory . DIRECTORY_SEPARATOR . '.upload-' . bin2hex(random_bytes(12)) . '.tmp';
        $backup = null;
        try {
            if (!move_uploaded_file((string)$inspected['temporary'], $staging)) {
                throw new UploadException('Не удалось принять загруженный файл.', 500);
            }
            @chmod($staging, 0640);
            $this->verifyPublishedFile($staging, (int)$inspected['size'], (string)$inspected['sha256']);

            if ($overwrite && is_file($destination)) {
                $backup = $directory . DIRECTORY_SEPARATOR . '.upload-backup-' . bin2hex(random_bytes(12)) . '.tmp';
                if (!rename($destination, $backup)) {
                    throw new UploadException('Не удалось подготовить замену существующего файла.', 500);
                }
            }
            if (!rename($staging, $destination)) {
                if (is_string($backup) && is_file($backup)) {
                    @rename($backup, $destination);
                }
                throw new UploadException('Не удалось атомарно опубликовать файл.', 500);
            }
            if (is_string($backup) && is_file($backup)) {
                @unlink($backup);
            }
            @chmod($destination, 0644);
            $this->verifyPublishedFile($destination, (int)$inspected['size'], (string)$inspected['sha256']);
        } catch (Throwable $error) {
            if (is_file($staging)) {
                @unlink($staging);
            }
            if (is_string($backup) && is_file($backup) && !is_file($destination)) {
                @rename($backup, $destination);
            }
            if ($error instanceof UploadException) {
                throw $error;
            }
            throw new UploadException('Не удалось опубликовать файл.', 500, [], $error);
        }

        $relative = $this->relativePath($destination);
        return new UploadResult(
            $purpose,
            $destination,
            $relative,
            $this->publicPath($relative),
            (string)$inspected['originalName'],
            basename($destination),
            (string)$inspected['mime'],
            (int)$inspected['size'],
            (string)$inspected['sha256'],
        );
    }

    private function verifyPublishedFile(string $path, int $size, string $sha256): void
    {
        clearstatcache(true, $path);
        $actualSize = filesize($path);
        $actualHash = hash_file('sha256', $path);
        if (!is_int($actualSize) || $actualSize !== $size
            || !is_string($actualHash) || !hash_equals($sha256, $actualHash)) {
            throw new UploadException('Проверка целостности опубликованного файла завершилась ошибкой.', 500);
        }
    }

    private function resolveNewFilePath(string $directory, string $name): string
    {
        $name = $this->safeFileName($name);
        $candidate = $directory . DIRECTORY_SEPARATOR . $name;
        if (is_link($candidate)) {
            throw new UploadException('Запись через символическую ссылку запрещена.', 409);
        }
        $root = $this->uploadsRoot();
        if (!$this->insideRoot($directory, $root)) {
            throw new UploadException('Целевой путь находится вне uploads.', 403);
        }
        return $candidate;
    }

    private function uploadsRoot(): string
    {
        $candidate = ROOT_DIR . str_replace('/', DIRECTORY_SEPARATOR, UPLOADS_DIR);
        if (!is_dir($candidate) && !mkdir($candidate, 0755, true) && !is_dir($candidate)) {
            throw new UploadException('Корневой каталог uploads недоступен.', 503);
        }
        $root = realpath($candidate);
        if (!is_string($root) || !is_dir($root) || is_link($candidate)) {
            throw new UploadException('Корневой каталог uploads недоступен.', 503);
        }
        return rtrim($root, '/\\');
    }

    private function relativeFromPublicPath(string $publicPath): string
    {
        $path = parse_url(trim(str_replace('\\', '/', $publicPath)), PHP_URL_PATH);
        $prefix = '/' . trim(str_replace('\\', '/', UPLOADS_DIR), '/') . '/';
        if (!is_string($path) || !str_starts_with($path, $prefix)) {
            throw new UploadException('Некорректный публичный путь загрузки.', 400);
        }
        return $this->safeRelativePath(rawurldecode(substr($path, strlen($prefix))));
    }

    private function relativePath(string $absolutePath): string
    {
        $root = $this->uploadsRoot();
        if (!$this->insideRoot($absolutePath, $root)) {
            throw new UploadException('Файл находится вне uploads.', 500);
        }
        return str_replace(DIRECTORY_SEPARATOR, '/', ltrim(substr($absolutePath, strlen($root)), '/\\'));
    }

    private function publicPath(string $relative): string
    {
        $segments = array_map('rawurlencode', explode('/', $relative));
        return '/' . trim(str_replace('\\', '/', UPLOADS_DIR), '/') . '/' . implode('/', $segments);
    }

    private function safeRelativePath(string $value): string
    {
        $value = trim(str_replace('\\', '/', $value), '/');
        if ($value === '') {
            return '';
        }
        if (str_contains($value, "\0")) {
            throw new UploadException('Недопустимый путь загрузки.', 400);
        }
        $segments = explode('/', $value);
        foreach ($segments as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..' || str_starts_with($segment, '.')) {
                throw new UploadException('Недопустимый путь загрузки.', 400, ['segment' => $segment]);
            }
            $this->safeFileName($segment);
        }
        return implode('/', $segments);
    }

    private function safeFileName(string $name): string
    {
        $name = trim($name);
        $stem = strtoupper(pathinfo($name, PATHINFO_FILENAME));
        $reservedWindowsName = preg_match('/^(?:CON|PRN|AUX|NUL|COM[1-9]|LPT[1-9])$/D', $stem) === 1;
        if ($name === '' || $name === '.' || $name === '..' || str_starts_with($name, '.')
            || rtrim($name, ". ") !== $name
            || str_contains($name, '/') || str_contains($name, '\\') || str_contains($name, "\0")
            || preg_match('/[<>:"|?*\x00-\x1f\x7f]/u', $name) === 1
            || $reservedWindowsName || mb_strlen($name) > 180) {
            throw new UploadException('Недопустимое имя файла.', 400, ['name' => mb_substr($name, 0, 180)]);
        }
        return $name;
    }

    private function rejectSymlinkPath(string $relative, string $root): void
    {
        if ($relative === '') {
            return;
        }
        $cursor = $root;
        foreach (explode('/', $relative) as $segment) {
            $cursor .= DIRECTORY_SEPARATOR . $segment;
            if (is_link($cursor)) {
                throw new UploadException('Переход через символическую ссылку запрещён.', 409, ['path' => $relative]);
            }
        }
    }

    private function insideRoot(string $path, string $root): bool
    {
        $path = rtrim($path, '/\\');
        $root = rtrim($root, '/\\');
        return $path === $root || str_starts_with($path, $root . DIRECTORY_SEPARATOR);
    }

    private function detectMime(string $path, bool $fallbackToBinary = false): string
    {
        try {
            $mime = (new finfo(FILEINFO_MIME_TYPE))->file($path);
        } catch (Throwable $error) {
            if ($fallbackToBinary) {
                return 'application/octet-stream';
            }
            throw new UploadException('Не удалось определить MIME-тип файла.', 415, [], $error);
        }
        if (!is_string($mime) || trim($mime) === '') {
            if ($fallbackToBinary) {
                return 'application/octet-stream';
            }
            throw new UploadException('Не удалось определить MIME-тип файла.', 415);
        }
        return strtolower(trim($mime));
    }

    private function probeImage(string $path, int $size, int $pixels): void
    {
        if (!extension_loaded('gd') || $size > 8_388_608 || $pixels > 12_000_000) {
            return;
        }
        $data = file_get_contents($path);
        if (!is_string($data)) {
            throw new UploadException('Не удалось прочитать изображение.', 422);
        }
        $image = @imagecreatefromstring($data);
        if (!$image instanceof GdImage) {
            throw new UploadException('Декодер изображений отклонил файл.', 422);
        }
        imagedestroy($image);
    }

    private function validMinecraftDimensions(string $type, int $width, int $height): bool
    {
        if ($type === 'skin') {
            return $this->isPowerOfTwo($width) && ($height === $width || $height * 2 === $width);
        }
        return ($width === 22 && $height === 17)
            || ($this->isPowerOfTwo($width) && $height * 2 === $width);
    }

    private function isPowerOfTwo(int $value): bool
    {
        return $value > 0 && ($value & ($value - 1)) === 0;
    }

    private function ownerUuid(array $context): string
    {
        $ownerUuid = trim((string)($context['ownerUuid'] ?? ''));
        if (!Uuid::isValid($ownerUuid)) {
            throw new UploadException('Некорректный владелец загрузки.', 400, ['ownerUuid' => $ownerUuid]);
        }
        return Uuid::normalize($ownerUuid);
    }

    private function uploadErrorMessage(int $error): string
    {
        return match ($error) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Файл превышает серверный лимит загрузки.',
            UPLOAD_ERR_PARTIAL => 'Файл был загружен не полностью.',
            UPLOAD_ERR_NO_FILE => 'Файл не выбран.',
            UPLOAD_ERR_NO_TMP_DIR => 'На сервере отсутствует временный каталог загрузок.',
            UPLOAD_ERR_CANT_WRITE => 'Сервер не смог записать временный файл.',
            UPLOAD_ERR_EXTENSION => 'Загрузка остановлена серверным расширением.',
            default => 'Неизвестная ошибка загрузки файла.',
        };
    }

    private function baseAudit(string $purpose, ?array $file, array $context): array
    {
        $target = isset($context['directory']) ? (string)$context['directory'] : '';
        $ownerUuid = isset($context['ownerUuid']) ? (string)$context['ownerUuid'] : '';
        return [
            'actorUuid' => $this->session->uuid(),
            'actorLogin' => $this->session->login(),
            'actorGroup' => $this->session->group(),
            'purpose' => $purpose,
            'ownerUuid' => $ownerUuid,
            'requestedDirectory' => $target,
            'originalName' => is_array($file) ? basename(str_replace('\\', '/', (string)($file['name'] ?? ''))) : '',
            'reportedSize' => is_array($file) ? max(0, (int)($file['size'] ?? 0)) : 0,
            'clientIp' => $this->request->clientIp(),
        ];
    }
}
