<?php

declare(strict_types=1);

final class SystemRequests
{
    private const REQUEST_HEADER = 'sysRequest';
    private const ACTION_HANDLERS = [
        'getJre' => 'handleGetJre',
        'parseServers' => 'handleParseServers',
        'getLangPack' => 'handleGetLangPack',
        'parseMonitor' => 'handleParseMonitor',
        'topPlayers' => 'handleTopPlayers',
        'infoBox' => 'handleInfoBox',
        'skin' => 'handleSkin',
        'userHead' => 'handleUserHead',
        'skinPath' => 'handleSkinPath',
        'skinPreview' => 'handleSkinPreviewRequest',
        'serverImage' => 'handleServerImage',
        'uploadFile' => 'handleUploadFile',
        'deleteFile' => 'handleDeleteFile',
        'loadFiles' => 'handleLoadFiles',
        'downloadLatest' => 'handleDownloadLatest',
        'downloadUpdater' => 'handleDownloadUpdater',
        'startedPlaying' => 'handleStartedPlaying',
        'playing' => 'handlePlaying',
        'checkStatus' => 'handleCheckStatus',
        'donePlaying' => 'handleDonePlaying',
        'getUserData' => 'handleLauncherUserData',
    ];

    private LauncherSessionService $launcherSessions;
    private UploadService $uploads;
    private FoxCMS\Engine\Launcher\LauncherAccess $launcherAccess;
    private FoxCMS\Engine\Launcher\LauncherRequestController $launcherController;
    private HardwareReportService $hardwareReports;
    private PublicFileLocator $publicFiles;
    private ArtifactRepository $artifacts;
    private UserTextureLocator $textures;

    public function __construct(
        private db $db,
        private Logger $logger,
        private HttpRequest $request,
        private UserSession $userSession,
        private array $config = [],
    ) {
        $this->launcherSessions = new LauncherSessionService($db, $logger);
        $this->uploads = new UploadService($db, $userSession, $logger, $request);
        $this->launcherAccess = new FoxCMS\Engine\Launcher\LauncherAccess(
            $request,
            $userSession,
            $this->launcherSessions,
        );
        $this->launcherController = new FoxCMS\Engine\Launcher\LauncherRequestController(
            $db,
            $request,
            $this->launcherAccess,
            new PlayTimeService($db, $logger),
        );
        $this->hardwareReports = new HardwareReportService($db, $logger);
        $this->publicFiles = new PublicFileLocator(ROOT_DIR);
        $this->artifacts = new ArtifactRepository(ROOT_DIR, ROOT_DIR . UPLOADS_DIR);
        $this->textures = new UserTextureLocator(ROOT_DIR . UPLOADS_DIR . USR_SUBFOLDER);
    }

    public function requestListener(): void
    {
        $action = $this->request->string(self::REQUEST_HEADER);
        if ($action === '') {
            return;
        }
        $handler = self::ACTION_HANDLERS[$action] ?? null;
        RequestTelemetry::identify('system_requests.' . $action, [
            'component' => 'system_requests',
            'action' => $action,
            'handler' => is_string($handler) ? $handler : 'unresolved',
            'moduleName' => 'SystemRequests',
        ]);
        if (!$this->request->isPost()) {
            RequestTelemetry::rejectHttp(
                'system_request.rejected',
                405,
                'System request used an unsupported HTTP method.',
                ['action' => $action],
            );
            JsonResponse::error('Method not allowed.', 405, ['Allow' => 'POST']);
        }

        if (!is_string($handler) || !method_exists($this, $handler)) {
            RequestTelemetry::rejectHttp(
                'system_request.rejected',
                400,
                'Unknown system request action.',
                ['action' => $action],
            );
            JsonResponse::error('Unknown system request.', 400);
        }

        try {
            $this->{$handler}();
        } catch (HttpException $error) {
            RequestTelemetry::rejectHttp(
                'system_request.rejected',
                $error->status(),
                $error->getMessage(),
                ['action' => $action],
            );
            JsonResponse::error($error->getMessage(), $error->status(), $error->headers());
        } catch (DomainException | InvalidArgumentException $error) {
            RequestTelemetry::rejectHttp(
                'system_request.rejected',
                400,
                $error->getMessage(),
                ['action' => $action],
            );
            JsonResponse::error($error->getMessage(), 400);
        } catch (Throwable $error) {
            RequestTelemetry::failure(
                'system_request.failed',
                $error,
                'System request failed unexpectedly.',
                ['action' => $action],
            );
            $requestId = RequestTelemetry::requestId();
            if ($requestId === '') {
                $requestId = ExceptionContext::requestId('system-request');
            }
            JsonResponse::send(
                \FoxCMS\Shared\Error\ThrowableDiagnostic::payload(
                    $error,
                    $requestId,
                    ROOT_DIR,
                    false,
                    ['type' => 'error', 'error' => 'system_request_failed', 'action' => $action],
                ),
                500,
            );
        }
    }

    private function handleGetJre(): never
    {
        UtilityLoader::load('GetJre', '1.0.0');
        $runtime = new GetJre(
            $this->request->string('jreVersion'),
            $this->request->string('platform'),
            $this->config,
        );
        JsonResponse::send($runtime->jsonSerialize());
    }

    private function handleParseServers(): never
    {
        $serverName = $this->request->string('serverName');
        if ($serverName !== '' && preg_match('/^[\p{L}\p{N}_ .-]{1,64}$/uD', $serverName) !== 1) {
            throw new InvalidArgumentException('Некорректное имя сервера.');
        }

        UtilityLoader::load('ServerParser', '1.0.0');
        $parser = new ServerParser($this->db, $this->userSession->uuid());
        JsonResponse::rawJson($parser->parseServers($serverName !== '' ? $serverName : null));
    }

    private function handleGetLangPack(): never
    {
        global $lang;

        $key = $this->request->string('langPackKey');
        if (preg_match('/^[A-Za-z0-9_.-]{1,64}$/D', $key) !== 1 || !array_key_exists($key, $lang)) {
            throw new HttpException('Language entry not found.', 404);
        }
        JsonResponse::send(['key' => $key, 'value' => $lang[$key]]);
    }

    private function handleParseMonitor(): never
    {
        UtilityLoader::load('ServerParser', '1.0.0');
        $parser = new ServerParser($this->db, $this->userSession->uuid());
        $serversJson = $parser->parseServers();
        $servers = json_decode($serversJson, true);
        if (is_array($servers) && ($servers['error'] ?? null) === 'ServerNotFound') {
            JsonResponse::send([
                'servers' => [],
                'totalPlayersOnline' => 0,
                'totalPlayersMax' => 0,
                'absoluteRecord' => 0,
                'todaysRecord' => 0,
                'emptyReason' => 'no_accessible_servers',
                'message' => 'Для вашей группы сейчас нет доступных серверов.',
            ]);
        }
        if (!is_array($servers) || !array_is_list($servers)) {
            throw new RuntimeException('Server parser returned an invalid monitoring payload.');
        }
        $monitorConfig = is_array($this->config['monitor'] ?? null) ? $this->config['monitor'] : [];
        $monitor = new FoxesMon($this->logger, $serversJson, $monitorConfig);
        JsonResponse::send($monitor->outputMonitoringData());
    }

    private function handleTopPlayers(): void
    {
        (new UserTop($this->db, $this->logger))->getTopPlayers();
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

    private function handleSkin(): never
    {
        $this->renderSkinPreview($this->request->string('show') === 'head');
    }

    private function handleSkinPreviewRequest(): never
    {
        $this->renderSkinPreview(false);
    }

    private function renderSkinPreview(bool $headOnly): never
    {
        if (!extension_loaded('gd')) {
            throw new HttpException('GD extension is unavailable.', 503);
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

        $defaultSkin = ROOT_DIR . UPLOADS_DIR . USR_SUBFOLDER . 'default_skin.png';
        $identityUuid = (string)($identity['uuid'] ?? '');
        if (Uuid::isValid($identityUuid)) {
            $files = $this->textures->locate($identityUuid);
            $skin = $files['skin'];
            $cape = $files['cape'];
        } else {
            $skin = $defaultSkin;
            $cape = '';
        }
        if (!is_file($skin) || !skinViewer2D::isValidSkin($skin)) {
            $skin = $defaultSkin;
        }
        if (!is_file($skin) || !skinViewer2D::isValidSkin($skin)) {
            throw new HttpException('Skin is unavailable.', 404);
        }

        $image = $headOnly
            ? skinViewer2D::createHead($skin, 64)
            : skinViewer2D::createPreview($skin, is_file($cape) ? $cape : false, $side ?: false);
        if (!$image instanceof GdImage) {
            throw new RuntimeException('Unable to render skin preview.');
        }
        try {
            ob_start();
            imagepng($image);
            $content = ob_get_clean();
        } finally {
            imagedestroy($image);
        }
        if (!is_string($content)) {
            throw new RuntimeException('Unable to encode skin preview.');
        }
        JsonResponse::text(base64_encode($content), 'text/plain; charset=US-ASCII');
    }

    private function handleUserHead(): never
    {
        $login = $this->request->string('login');
        if (preg_match('/^[A-Za-z0-9_.-]{1,64}$/D', $login) !== 1) {
            throw new InvalidArgumentException('Некорректный логин.');
        }

        UtilityLoader::load('LoadUserInfo', '1.0.0');
        $userData = LoadUserInfo::byLogin($login, $this->db)->userInfoArray();
        $path = $this->publicFiles->resolve(
            (string)($userData['profilePhoto'] ?? ''),
            ROOT_DIR . UPLOADS_DIR,
            5_242_880,
        );
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($path);
        if (!is_string($mime) || !str_starts_with($mime, 'image/')) {
            throw new HttpException('Invalid profile image.', 415);
        }
        $content = file_get_contents($path);
        if (!is_string($content)) {
            throw new RuntimeException('Unable to read profile image.');
        }
        JsonResponse::text(base64_encode($content), 'text/plain; charset=US-ASCII');
    }

    private function handleSkinPath(): never
    {
        if (!$this->userSession->isLogged()) {
            throw new HttpException('Authentication required.', 401);
        }
        $files = $this->userSession->gameFiles();
        JsonResponse::send([
            'skin' => str_replace('\\', '/', substr($files['skin'], strlen(ROOT_DIR))),
            'cape' => str_replace('\\', '/', substr($files['cape'], strlen(ROOT_DIR))),
        ]);
    }

    private function handleServerImage(): never
    {
        $reference = trim(str_replace('\\', '/', $this->request->string('srvImgName')));
        if (str_starts_with($reference, 'uploads/')) {
            $reference = '/' . $reference;
        }

        if (str_starts_with($reference, '/uploads/servers/')) {
            if (preg_match('#^/uploads/servers/[A-Za-z0-9_.-]{1,180}\.(?:png|jpe?g|webp)$#iD', $reference) !== 1) {
                throw new InvalidArgumentException('Invalid uploaded server image path.');
            }
            $path = $this->publicFiles->resolve(
                ltrim($reference, '/'),
                ROOT_DIR . UPLOADS_DIR . 'servers',
                12_582_912,
            );
        } else {
            if (preg_match('/^[A-Za-z0-9_.-]{1,96}\.(?:png|jpe?g|webp)$/iD', $reference) !== 1) {
                throw new InvalidArgumentException('Invalid server image name.');
            }
            $path = $this->publicFiles->resolve(
                'templates/' . (string)$this->config['siteSettings']['siteTpl']
                    . '/assets/img/servers/' . $reference,
                TEMPLATE_DIR,
                10_485_760,
            );
        }

        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($path);
        if (!is_string($mime) || !in_array(strtolower($mime), ['image/jpeg', 'image/png', 'image/webp'], true)) {
            throw new HttpException('Invalid server image.', 415);
        }
        $content = file_get_contents($path);
        if (!is_string($content)) {
            throw new RuntimeException('Unable to read server image.');
        }
        JsonResponse::text(base64_encode($content), 'text/plain; charset=US-ASCII');
    }

    private function handleUploadFile(): never
    {
        if (!$this->userSession->isLogged()) {
            throw new HttpException('Authentication required.', 401);
        }
        $type = $this->request->string('type');
        $purpose = match ($type) {
            'skin' => UploadPurpose::MINECRAFT_SKIN,
            'cloak' => UploadPurpose::MINECRAFT_CAPE,
            default => throw new InvalidArgumentException('Unknown file type.'),
        };
        $targetUuid = $this->resolveMutationUserUuid(false);
        try {
            $result = $this->uploads->store(
                $purpose,
                $this->request->file('0') ?? $this->request->file('file'),
                ['ownerUuid' => $targetUuid],
            );
        } catch (UploadException $error) {
            throw new HttpException($error->getMessage(), $error->httpStatus(), [], $error);
        }

        JsonResponse::send([
            'message' => $type === 'skin' ? 'Скин загружен.' : 'Плащ загружен.',
            'type' => 'success',
            'upload' => $result,
        ]);
    }

    private function handleDeleteFile(): never
    {
        global $lang;

        $this->requireBrowserMutation();
        $type = $this->request->string('type');
        $targetUuid = $this->resolveMutationUserUuid();
        $files = $this->textures->locate($targetUuid);
        $path = match ($type) {
            'skin' => $files['skin'],
            'cloak' => $files['cape'],
            default => null,
        };
        if (!is_string($path)) {
            throw new InvalidArgumentException('Unknown file type.');
        }

        $label = $type === 'skin'
            ? ($lang['userProfile']['skin'] ?? 'skin')
            : ($lang['userProfile']['cape'] ?? 'cape');
        if (!is_file($path)) {
            throw new HttpException('У вас нет ' . $label . '.', 404);
        }
        if (is_link($path) || !unlink($path)) {
            throw new RuntimeException('Unable to delete user texture.');
        }
        $this->logger->event('user.texture.deleted', 'User texture deleted.', [
            'component' => 'user_texture',
            'operation' => 'delete',
            'targetUserUuid' => $targetUuid,
            'textureType' => $type,
        ], 'INFO', 'success');
        JsonResponse::send(['message' => ucfirst($label) . ' удалён.', 'type' => 'success']);
    }

    private function handleLoadFiles(): never
    {
        $scanner = new GameScanner(
            $this->request->string('client'),
            $this->request->string('version'),
            $this->request->integer('platform', 0),
            $this->config,
        );
        $scanner->scan();
        JsonResponse::rawJson($scanner->toJson());
    }

    private function handleDownloadLatest(): never
    {
        $platform = $this->safeDirectorySegment($this->request->string('platform'), 'platform');
        JsonResponse::send($this->artifacts->latest('uploads/files/launcher/' . $platform, ['jar']));
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
            $launcher = $this->launcherSessions->authenticate($this->launcherAccess->token());
            if ($launcher !== null) {
                $this->hardwareReports->store($systemInformation, $launcher['userUuid']);
            }
        }

        JsonResponse::send($this->artifacts->latest($root, ['jar', 'exe', 'zip', 'msi', 'AppImage']));
    }

    private function handleStartedPlaying(): void
    {
        $this->launcherController->startedPlaying();
    }

    private function handlePlaying(): void
    {
        $this->launcherController->playing();
    }

    private function handleCheckStatus(): void
    {
        $this->launcherController->checkStatus();
    }

    private function handleDonePlaying(): void
    {
        $this->launcherController->donePlaying();
    }

    private function handleLauncherUserData(): never
    {
        $this->launcherController->userData();
    }

    private function resolveMutationUserUuid(bool $authorize = true): string
    {
        $requestedUuid = $this->request->string('userUuid');
        $targetIdentity = $requestedUuid !== '' ? $requestedUuid : $this->userSession->uuid();
        if (!Uuid::isValid($targetIdentity)) {
            throw new InvalidArgumentException('Некорректный UUID пользователя.');
        }
        $targetIdentity = Uuid::normalize($targetIdentity);
        if ($authorize && !$this->userSession->isAdmin()
            && !Uuid::equals($this->userSession->uuid(), $targetIdentity)) {
            throw new HttpException('Insufficient rights.', 403);
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
            throw new HttpException('User not found.', 404);
        }
        return Uuid::normalize($storedUuid);
    }

    private function requireBrowserMutation(): void
    {
        if (!$this->userSession->isLogged()) {
            throw new HttpException('Authentication required.', 401);
        }
        CsrfToken::requireValid($this->request->csrfToken());
    }

    private function safeDirectorySegment(string $value, string $name): string
    {
        if (preg_match('/^[A-Za-z0-9_-]{1,32}$/D', $value) !== 1) {
            throw new InvalidArgumentException('Invalid ' . $name . '.');
        }
        return $value;
    }
}
