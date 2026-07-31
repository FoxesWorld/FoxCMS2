<?php

declare(strict_types=1);

require_once __DIR__ . '/classes/services/PlayTimeService.php';
require_once __DIR__ . '/classes/services/LauncherSessionService.class.php';

final class SystemRequests
{
    private const REQUEST_HEADER = 'sysRequest';

    private LauncherSessionService $launcherSessions;

    public function __construct(
        private db $db,
        private Logger $logger,
        private HttpRequest $request,
        private UserSession $userSession,
        private array $config = [],
    ) {
        $this->launcherSessions = new LauncherSessionService($db);
    }

    public function requestListener(): void
    {
        $action = $this->request->string(self::REQUEST_HEADER);
        if ($action === '') {
            return;
        }
        if (!$this->request->isPost()) {
            $this->jsonError('Method not allowed.', 405, ['Allow' => 'POST']);
        }

        try {
            switch ($action) {
                case 'getJre':
                    $this->handleGetJre();
                    break;
                case 'parseServers':
                    $this->handleParseServers();
                    break;
                case 'getLangPack':
                    $this->handleGetLangPack();
                    break;
                case 'parseMonitor':
                    $this->handleParseMonitor();
                    break;
                case 'topPlayers':
                    (new UserTop($this->db, $this->logger))->getTopPlayers();
                    break;
                case 'infoBox':
                    $this->handleInfoBox();
                    break;
                case 'skin':
                    $this->handleSkinPreview($this->request->string('show') === 'head');
                    break;
                case 'userHead':
                    $this->handleUserHead();
                    break;
                case 'skinPath':
                    $this->handleSkinPath();
                    break;
                case 'skinPreview':
                    $this->handleSkinPreview(false);
                    break;
                case 'serverImage':
                    $this->handleServerImage();
                    break;
                case 'uploadFile':
                    $this->handleUploadFile();
                    break;
                case 'deleteFile':
                    $this->handleDeleteFile();
                    break;
                case 'loadFiles':
                    $this->handleLoadFiles();
                    break;
                case 'downloadLatest':
                    $this->handleDownloadLatest();
                    break;
                case 'downloadUpdater':
                    $this->handleDownloadUpdater();
                    break;
                case 'startedPlaying':
                    $this->playTime()->start(
                        $this->authenticatedLauncherUuid(),
                        $this->request->string('serverName'),
                        $this->request->string('uuid'),
                    );
                    break;
                case 'playing':
                    $this->playTime()->heartbeat(
                        $this->authenticatedLauncherUuid(),
                        $this->request->string('uuid'),
                    );
                    break;
                case 'checkStatus':
                    $this->playTime()->status(
                        $this->authenticatedLauncherUuid(),
                        $this->request->string('serverName'),
                        $this->request->string('uuid'),
                    );
                    break;
                case 'donePlaying':
                    $this->playTime()->finish(
                        $this->authenticatedLauncherUuid(),
                        $this->request->string('serverName'),
                        $this->request->string('uuid'),
                    );
                    break;
                case 'getUserData':
                    $this->handleLauncherUserData();
                    break;
                default:
                    $this->jsonError('Unknown system request.', 400);
            }
        } catch (DomainException | InvalidArgumentException $exception) {
            $this->jsonError($exception->getMessage(), 400);
        }
    }

    private function handleGetJre(): never
    {
        UtilityLoader::load('GetJre', '1.0.0');
        $runtime = new GetJre($this->request->string('jreVersion'), $this->config);
        $this->json($runtime->jsonSerialize());
    }

    private function handleParseServers(): never
    {
        $serverName = $this->request->string('serverName');
        if ($serverName !== '' && preg_match('/^[\p{L}\p{N}_ .-]{1,64}$/uD', $serverName) !== 1) {
            throw new InvalidArgumentException('Некорректное имя сервера.');
        }

        UtilityLoader::load('ServerParser', '1.0.0');
        $parser = new ServerParser($this->db, $this->userSession->uuid());
        $this->rawJson($parser->parseServers($serverName !== '' ? $serverName : null));
    }

    private function handleGetLangPack(): never
    {
        global $lang;

        $key = $this->request->string('langPackKey');
        if (preg_match('/^[A-Za-z0-9_.-]{1,64}$/D', $key) !== 1 || !array_key_exists($key, $lang)) {
            $this->jsonError('Language entry not found.', 404);
        }
        $this->json(['key' => $key, 'value' => $lang[$key]]);
    }

    private function handleParseMonitor(): never
    {
        UtilityLoader::load('ServerParser', '1.0.0');
        $parser = new ServerParser($this->db, $this->userSession->uuid());
        $monitor = new foxesMon(
            $this->logger,
            $parser->parseServers(),
            ['out' => 2, 'record_day' => 86400],
        );
        $this->rawJson($monitor->outputMonitoringData());
    }

    private function handleInfoBox(): never
    {
        $login = $this->request->string('user', 'anonymous');
        if (preg_match('/^[A-Za-z0-9_.-]{1,64}$/D', $login) !== 1) {
            throw new InvalidArgumentException('Некорректный логин.');
        }

        UtilityLoader::load('LoadUserInfo', '1.0.0');
        $userInfo = LoadUserInfo::byLogin($login, $this->db)->userInfoArray();
        $tag = GroupRepository::normalizeTag($userInfo['groupTag'] ?? 'guest');
        (new InfoBox($this->db, $this->logger, $tag))->getInfoBox();
        throw new LogicException('InfoBox did not terminate the request.');
    }

    private function handleSkinPreview(bool $headOnly): never
    {
        if (!extension_loaded('gd')) {
            $this->jsonError('GD extension is unavailable.', 503);
        }

        $side = $this->request->string('side');
        if (!in_array($side, ['', 'front', 'back'], true)) {
            throw new InvalidArgumentException('Некорректная сторона preview.');
        }

        UtilityLoader::load('SkinViewer', '1.0.0');
        UtilityLoader::load('LoadUserInfo', '1.0.0');
        $requestedUuid = $this->request->string('userUuid');
        if ($requestedUuid !== '') {
            if (!Uuid::isValid($requestedUuid)) {
                throw new InvalidArgumentException('Некорректный UUID пользователя.');
            }
            $identity = LoadUserInfo::byUuid(Uuid::normalize($requestedUuid), $this->db)->userInfoArray();
        } else {
            $login = $this->request->string('login', 'default');
            if (preg_match('/^[A-Za-z0-9_.-]{1,64}$/D', $login) !== 1) {
                throw new InvalidArgumentException('Некорректный логин.');
            }
            $identity = LoadUserInfo::byLogin($login, $this->db)->userInfoArray();
        }
        $identityUuid = (string)($identity['uuid'] ?? '');
        $identityLogin = (string)($identity['login'] ?? '');
        [$skin, $cape] = Uuid::isValid($identityUuid)
            ? $this->skinFiles(Uuid::normalize($identityUuid), $identityLogin !== '' ? $identityLogin : null)
            : [ROOT_DIR . UPLOADS_DIR . USR_SUBFOLDER . 'default_skin.png', ''];
        if (!is_file($skin) || !skinViewer2D::isValidSkin($skin)) {
            $skin = ROOT_DIR . UPLOADS_DIR . USR_SUBFOLDER . 'default_skin.png';
        }
        if (!is_file($skin) || !skinViewer2D::isValidSkin($skin)) {
            $this->jsonError('Skin is unavailable.', 404);
        }

        $image = $headOnly
            ? skinViewer2D::createHead($skin, 64)
            : skinViewer2D::createPreview($skin, is_file($cape) ? $cape : false, $side ?: false);
        if (!$image instanceof GdImage) {
            $this->jsonError('Unable to render skin preview.', 500);
        }
        try {
            ob_start();
            imagepng($image);
            $content = ob_get_clean();
        } finally {
            imagedestroy($image);
        }
        if (!is_string($content)) {
            $this->jsonError('Unable to encode skin preview.', 500);
        }
        $this->text(base64_encode($content), 'text/plain; charset=US-ASCII');
    }

    private function handleUserHead(): never
    {
        $login = $this->request->string('login');
        if (preg_match('/^[A-Za-z0-9_.-]{1,64}$/D', $login) !== 1) {
            throw new InvalidArgumentException('Некорректный логин.');
        }

        UtilityLoader::load('LoadUserInfo', '1.0.0');
        $userData = LoadUserInfo::byLogin($login, $this->db)->userInfoArray();
        $relative = (string)($userData['profilePhoto'] ?? '');
        $path = $this->safePublicFile($relative, ROOT_DIR . UPLOADS_DIR, 5_242_880);
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($path);
        if (!is_string($mime) || !str_starts_with($mime, 'image/')) {
            $this->jsonError('Invalid profile image.', 415);
        }
        $content = file_get_contents($path);
        if (!is_string($content)) {
            $this->jsonError('Unable to read profile image.', 500);
        }
        $this->text(base64_encode($content), 'text/plain; charset=US-ASCII');
    }

    private function handleSkinPath(): never
    {
        if (!$this->userSession->isLogged()) {
            $this->jsonError('Authentication required.', 401);
        }
        $files = $this->userSession->gameFiles();
        $this->json([
            'skin' => str_replace('\\', '/', substr($files['skin'], strlen(ROOT_DIR))),
            'cape' => str_replace('\\', '/', substr($files['cape'], strlen(ROOT_DIR))),
        ]);
    }

    private function handleServerImage(): never
    {
        $filename = $this->request->string('srvImgName');
        if (preg_match('/^[A-Za-z0-9_.-]{1,96}\.(?:png|jpe?g|webp)$/iD', $filename) !== 1) {
            throw new InvalidArgumentException('Invalid server image name.');
        }
        $path = $this->safePublicFile(
            'templates/' . (string)$this->config['siteSettings']['siteTpl'] . '/assets/img/servers/' . $filename,
            TEMPLATE_DIR,
            10_485_760,
        );
        $content = file_get_contents($path);
        if (!is_string($content)) {
            $this->jsonError('Unable to read server image.', 500);
        }
        $this->text(base64_encode($content), 'text/plain; charset=US-ASCII');
    }

    private function handleUploadFile(): never
    {
        $this->requireBrowserMutation();
        $type = $this->request->string('type');
        if (!in_array($type, ['skin', 'cloak'], true)) {
            throw new InvalidArgumentException('Unknown file type.');
        }

        $targetUuid = $this->resolveMutationUserUuid();

        $file = $this->request->file('0') ?? $this->request->file('file');
        if (!is_array($file)) {
            $this->jsonError('File is missing.', 400);
        }
        $error = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error !== UPLOAD_ERR_OK) {
            $this->jsonError('Upload failed with code ' . $error . '.', 400);
        }
        $temporary = (string)($file['tmp_name'] ?? '');
        $size = (int)($file['size'] ?? 0);
        if ($temporary === '' || !is_uploaded_file($temporary) || $size < 1 || $size > 2_097_152) {
            $this->jsonError('Invalid or oversized upload.', 400);
        }

        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($temporary);
        $dimensions = getimagesize($temporary);
        if ($mime !== 'image/png' || !is_array($dimensions)) {
            $this->jsonError('Only valid PNG images are accepted.', 415);
        }
        $width = (int)($dimensions[0] ?? 0);
        $height = (int)($dimensions[1] ?? 0);
        if (!$this->validMinecraftTextureDimensions($type, $width, $height)) {
            $this->jsonError('Unsupported texture dimensions.', 422);
        }
        $probe = @imagecreatefrompng($temporary);
        if (!$probe instanceof GdImage) {
            $this->jsonError('PNG decoder rejected the image.', 422);
        }
        imagedestroy($probe);

        $folder = ROOT_DIR . UPLOADS_DIR . USR_SUBFOLDER . $targetUuid . DIRECTORY_SEPARATOR;
        if (!is_dir($folder) && !mkdir($folder, 0750, true) && !is_dir($folder)) {
            $this->jsonError('Unable to create user directory.', 500);
        }
        $suffix = $type === 'skin' ? '-skin.png' : '-cape.png';
        $destination = $folder . Uuid::compact($targetUuid) . $suffix;
        $staging = $folder . '.upload-' . bin2hex(random_bytes(8)) . '.tmp';
        if (!move_uploaded_file($temporary, $staging)) {
            $this->jsonError('Unable to stage uploaded file.', 500);
        }
        @chmod($staging, 0640);
        if (!rename($staging, $destination)) {
            @unlink($staging);
            $this->jsonError('Unable to publish uploaded file.', 500);
        }

        $this->logger->logInfo('User texture updated', [
            'userUuid' => $targetUuid,
            'type' => $type,
            'size' => $size,
        ]);
        $this->json(['message' => 'Файл загружен.', 'type' => 'success']);
    }

    private function handleDeleteFile(): never
    {
        global $lang;

        $this->requireBrowserMutation();
        $type = $this->request->string('type');
        $targetUuid = $this->resolveMutationUserUuid();
        [$skin, $cape] = $this->skinFiles($targetUuid);
        $path = match ($type) {
            'skin' => $skin,
            'cloak' => $cape,
            default => null,
        };
        if (!is_string($path)) {
            throw new InvalidArgumentException('Unknown file type.');
        }

        $label = $type === 'skin'
            ? ($lang['userProfile']['skin'] ?? 'skin')
            : ($lang['userProfile']['cape'] ?? 'cape');
        if (!is_file($path)) {
            $this->jsonError('У вас нет ' . $label . '.', 404);
        }
        if (!unlink($path)) {
            $this->jsonError('Не удалось удалить ' . $label . '.', 500);
        }
        $this->logger->logInfo('User texture deleted', [
            'userUuid' => $targetUuid,
            'type' => $type,
        ]);
        $this->json(['message' => ucfirst($label) . ' удалён.', 'type' => 'success']);
    }

    private function handleLoadFiles(): never
    {
        $client = $this->request->string('client');
        $version = $this->request->string('version');
        $platform = $this->request->integer('platform', 0);
        $scanner = new GameScanner($client, $version, $platform, $this->config);
        $scanner->scan();
        $this->rawJson($scanner->toJson());
    }

    private function handleDownloadLatest(): never
    {
        $platform = $this->safeDirectorySegment($this->request->string('platform'), 'platform');
        $artifact = $this->latestArtifact('uploads/files/launcher/' . $platform, ['jar']);
        $this->json($artifact);
    }

    private function handleDownloadUpdater(): never
    {
        $type = $this->safeDirectorySegment($this->request->string('type'), 'updater type');
        $version = $this->request->string('version');
        $root = $version === ''
            ? 'uploads/updater/' . $type
            : 'uploads/files/updater/' . $type;

        $systemInformation = $this->request->string('systemInformation');
        if ($systemInformation !== '') {
            $token = $this->launcherToken();
            $launcher = $this->launcherSessions->authenticate($token);
            if ($launcher !== null) {
                $this->storeHardwareReport($systemInformation, $launcher['userUuid']);
            }
        }

        $this->json($this->latestArtifact($root, ['jar', 'exe', 'zip', 'msi', 'AppImage']));
    }

    private function handleLauncherUserData(): never
    {
        $launcher = $this->launcherSessions->requireAuthenticated($this->launcherToken());
        $requestedProfile = strtolower($this->request->string('uuid'));
        if ($requestedProfile !== '' && !Uuid::equals($launcher['userUuid'], $requestedProfile)) {
            $this->jsonError('Launcher profile mismatch.', 403);
        }

        UtilityLoader::load('LoadUserInfo', '1.0.0');
        $userData = LoadUserInfo::byUuid($launcher['userUuid'], $this->db)->userInfoArray();
        $group = (new GroupRepository($this->db))->find((string)($userData['groupTag'] ?? 'guest'));
        $this->json([
            'login' => (string)($userData['login'] ?? ''),
            'realname' => (string)($userData['realname'] ?? ''),
            'colorScheme' => (string)($userData['colorScheme'] ?? ''),
            'userStatus' => (string)($userData['userStatus'] ?? ''),
            'land' => (string)($userData['land'] ?? ''),
            'profilePhoto' => (string)($userData['profilePhoto'] ?? ''),
            'groupTag' => (string)($group['groupTag'] ?? 'guest'),
            'groupName' => (string)($group['groupName'] ?? 'Гости'),
        ]);
    }

    private function playTime(): PlayTimeService
    {
        return new PlayTimeService($this->db, $this->logger);
    }

    private function authenticatedLauncherUuid(): string
    {
        if ($this->userSession->isLogged()) {
            return $this->userSession->uuid();
        }
        return $this->launcherSessions->requireAuthenticated($this->launcherToken())['userUuid'];
    }

    private function launcherToken(): string
    {
        $token = strtolower($this->request->string('accessToken'));
        if ($token === '') {
            $authorization = $this->request->header('Authorization');
            if (str_starts_with($authorization, 'Bearer ')) {
                $token = strtolower(trim(substr($authorization, 7)));
            }
        }
        return $token;
    }

    private function resolveMutationUserUuid(): string
    {
        $requestedUuid = $this->request->string('userUuid');
        $targetIdentity = $requestedUuid !== '' ? $requestedUuid : $this->userSession->uuid();
        if (!Uuid::isValid($targetIdentity)) {
            throw new InvalidArgumentException('Некорректный UUID пользователя.');
        }
        $targetIdentity = Uuid::normalize($targetIdentity);
        if (!$this->userSession->isAdmin() && !Uuid::equals($this->userSession->uuid(), $targetIdentity)) {
            $this->jsonError('Insufficient rights.', 403);
        }

        $placeholders = [];
        $parameters = [];
        foreach (Uuid::databaseCandidates($targetIdentity) as $index => $candidate) {
            $placeholder = ':userUuid_' . $index;
            $placeholders[] = $placeholder;
            $parameters[$placeholder] = $candidate;
        }
        $statement = $this->db->prepare(
            'SELECT `uuid` FROM `users` WHERE `uuid` IN (' . implode(', ', $placeholders) . ') LIMIT 1'
        );
        $statement->execute($parameters);
        $storedUuid = $statement->fetchColumn();
        if (!is_string($storedUuid) || !Uuid::isValid($storedUuid)) {
            $this->jsonError('User not found.', 404);
        }
        return Uuid::normalize($storedUuid);
    }

    private function requireBrowserMutation(): void
    {
        if (!$this->userSession->isLogged()) {
            $this->jsonError('Authentication required.', 401);
        }
        CsrfToken::requireValid($this->request->csrfToken());
    }

    /** @return array{0:string,1:string} */
    private function skinFiles(string $userUuid, ?string $legacyLogin = null): array
    {
        $userUuid = Uuid::normalize($userUuid);
        $folder = ROOT_DIR . UPLOADS_DIR . USR_SUBFOLDER . $userUuid . DIRECTORY_SEPARATOR;
        $stem = Uuid::compact($userUuid);
        $skin = $folder . $stem . '-skin.png';
        $cape = $folder . $stem . '-cape.png';
        if (is_file($skin) || $legacyLogin === null) {
            return [$skin, $cape];
        }

        $legacyFolder = ROOT_DIR . UPLOADS_DIR . USR_SUBFOLDER . $legacyLogin . DIRECTORY_SEPARATOR;
        $legacyStem = md5($legacyLogin);
        return [$legacyFolder . $legacyStem . '-skin.png', $legacyFolder . $legacyStem . '-cape.png'];
    }

    private function validMinecraftTextureDimensions(string $type, int $width, int $height): bool
    {
        if ($width < 16 || $height < 16 || $width > 1024 || $height > 1024) {
            return false;
        }
        if ($type === 'skin') {
            return $this->isPowerOfTwo($width)
                && ($height === $width || $height * 2 === $width);
        }
        return ($width === 22 && $height === 17)
            || ($this->isPowerOfTwo($width) && $height * 2 === $width);
    }

    private function isPowerOfTwo(int $value): bool
    {
        return $value > 0 && ($value & ($value - 1)) === 0;
    }

    private function safePublicFile(string $relativePath, string $allowedRoot, int $maximumBytes): string
    {
        $relativePath = str_replace('\\', '/', trim($relativePath));
        if ($relativePath === '' || str_contains($relativePath, "\0")) {
            throw new DomainException('File not found.');
        }
        $root = realpath($allowedRoot);
        $file = realpath(ROOT_DIR . DIRECTORY_SEPARATOR . ltrim($relativePath, '/'));
        if (
            $root === false
            || $file === false
            || !is_file($file)
            || !str_starts_with($file, rtrim($root, '/\\') . DIRECTORY_SEPARATOR)
        ) {
            throw new DomainException('File not found.');
        }
        $size = filesize($file);
        if (!is_int($size) || $size < 0 || $size > $maximumBytes) {
            throw new DomainException('File exceeds the allowed size.');
        }
        return $file;
    }

    /** @return array{filename:string,hash:string,sha256:string,size:int} */
    private function latestArtifact(string $relativeDirectory, array $extensions): array
    {
        $directory = realpath(ROOT_DIR . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativeDirectory));
        $uploadsRoot = realpath(ROOT_DIR . UPLOADS_DIR);
        if (
            $directory === false
            || $uploadsRoot === false
            || !is_dir($directory)
            || !str_starts_with($directory, rtrim($uploadsRoot, '/\\') . DIRECTORY_SEPARATOR)
        ) {
            throw new DomainException('Artifact directory not found.');
        }

        $files = [];
        foreach (new DirectoryIterator($directory) as $entry) {
            if (!$entry->isFile() || $entry->isLink()) {
                continue;
            }
            $extension = $entry->getExtension();
            if (!in_array($extension, $extensions, true) && !in_array(strtolower($extension), array_map('strtolower', $extensions), true)) {
                continue;
            }
            $files[] = $entry->getRealPath();
        }
        $files = array_values(array_filter($files, 'is_string'));
        if ($files === []) {
            throw new DomainException('Artifact not found.');
        }
        usort($files, static function (string $left, string $right): int {
            return version_compare(pathinfo($right, PATHINFO_FILENAME), pathinfo($left, PATHINFO_FILENAME));
        });
        $file = $files[0];
        $md5 = hash_file('md5', $file);
        $sha256 = hash_file('sha256', $file);
        $size = filesize($file);
        if (!is_string($md5) || !is_string($sha256) || !is_int($size)) {
            throw new RuntimeException('Unable to fingerprint artifact.');
        }

        return [
            'filename' => str_replace('\\', '/', substr($file, strlen(ROOT_DIR))),
            'hash' => $md5,
            'sha256' => $sha256,
            'size' => $size,
        ];
    }

    private function safeDirectorySegment(string $value, string $name): string
    {
        if (preg_match('/^[A-Za-z0-9_-]{1,32}$/D', $value) !== 1) {
            throw new InvalidArgumentException('Invalid ' . $name . '.');
        }
        return $value;
    }

    private function storeHardwareReport(string $json, string $userUuid): void
    {
        $userUuid = Uuid::normalize($userUuid);
        if (strlen($json) > 65_536) {
            throw new InvalidArgumentException('Hardware report is too large.');
        }
        try {
            $report = json_decode($json, true, 8, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new InvalidArgumentException('Malformed hardware report.');
        }
        if (!is_array($report) || array_is_list($report)) {
            throw new InvalidArgumentException('Hardware report must be an object.');
        }

        $sanitized = [];
        foreach (array_slice($report, 0, 32, true) as $key => $value) {
            if (!is_string($key) || preg_match('/^[A-Za-z_][A-Za-z0-9_]{0,63}$/D', $key) !== 1) {
                continue;
            }
            if (is_array($value)) {
                $sanitized[$key] = array_slice($value, 0, 32);
            } elseif (is_scalar($value) || $value === null) {
                $sanitized[$key] = is_string($value) ? mb_substr($value, 0, 4096) : $value;
            }
        }

        $cpu = mb_substr((string)($sanitized['cpu'] ?? $sanitized['cpuName'] ?? ''), 0, 255);
        $rawGpus = $sanitized['gpus'] ?? $sanitized['gpu'] ?? [];
        $gpus = is_array($rawGpus) ? array_values(array_map(
            static fn (mixed $value): string => mb_substr((string)$value, 0, 255),
            array_slice($rawGpus, 0, 16),
        )) : [mb_substr((string)$rawGpus, 0, 255)];
        $cpuId = trim((string)($sanitized['cpuId'] ?? ''));
        $payload = json_encode($sanitized, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $gpuJson = json_encode($gpus, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        $statement = $this->db->prepare(
            'INSERT INTO `user_hardware_reports` (`userUuid`, `cpuIdHash`, `cpu`, `gpus`, `payload`) '
            . 'VALUES (:userUuid, :cpuIdHash, :cpu, :gpus, :payload) '
            . 'ON DUPLICATE KEY UPDATE '
            . '`cpuIdHash` = VALUES(`cpuIdHash`), `cpu` = VALUES(`cpu`), '
            . '`gpus` = VALUES(`gpus`), `payload` = VALUES(`payload`), '
            . '`updatedAt` = CURRENT_TIMESTAMP(4)'
        );
        $statement->execute([
            ':userUuid' => $userUuid,
            ':cpuIdHash' => $cpuId === '' ? null : hash('sha256', $cpuId),
            ':cpu' => $cpu,
            ':gpus' => $gpuJson,
            ':payload' => $payload,
        ]);
    }

    private function json(array|JsonSerializable $payload, int $status = 200, array $headers = []): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=UTF-8');
        header('Cache-Control: no-store');
        foreach ($headers as $name => $value) {
            if (is_string($name) && is_scalar($value)) {
                header($name . ': ' . (string)$value);
            }
        }
        $encoded = json_encode(
            $payload,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE,
        );
        if (!is_string($encoded)) {
            throw new RuntimeException('Unable to encode system response.');
        }
        exit($encoded);
    }

    private function rawJson(string $payload, int $status = 200): never
    {
        json_decode($payload, true, 32, JSON_THROW_ON_ERROR);
        http_response_code($status);
        header('Content-Type: application/json; charset=UTF-8');
        header('Cache-Control: no-store');
        exit($payload);
    }

    private function text(string $payload, string $contentType): never
    {
        header('Content-Type: ' . $contentType);
        header('Cache-Control: no-store');
        exit($payload);
    }

    private function jsonError(string $message, int $status, array $headers = []): never
    {
        $this->json(['message' => $message, 'type' => 'error'], $status, $headers);
    }
}
