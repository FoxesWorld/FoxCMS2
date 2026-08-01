<?php

if (!defined('ADMIN')) {
    die();
}

final class AdminOptions {
    private const LOG_FILES = ['lastlog', 'error', 'access'];
    private const SERVER_FIELDS = [
        'serverName', 'host', 'port', 'ignoreDirs', 'enabled', 'checkLib',
        'serverGroups', 'serverDescription', 'serverVersion', 'jreVersion',
        'serverImage', 'modsInfo'
    ];
    private const USER_FIELDS = [
        'login', 'realname', 'email', 'userStatus', 'groupTag', 'balance', 'badges', 'serversOnline'
    ];
    private const CATALOGS = [
        'infobox' => [
            'table' => 'infobox',
            'key' => 'group_name',
            'fields' => ['group_name', 'start_timestamp', 'end_timestamp', 'title', 'text', 'image', 'button_text', 'button_url'],
        ],
        'badges' => [
            'table' => 'badgesList',
            'key' => 'badgeName',
            'fields' => ['badgeName', 'description', 'img'],
        ],
        'groups' => [
            'table' => 'groupAssociation',
            'key' => 'groupTag',
            'fields' => ['groupTag', 'groupName', 'groupColor'],
        ],
    ];

    private $db;
    private array $request;
    private UserSession $session;
    private ?Logger $logger;
    private MaintenanceModeRepository $maintenanceRepository;
    private GroupRepository $groupRepository;
    private UploadService $uploads;
    private ThemeSlidesRepository $slidesRepository;
    private ThemeContentRepository $contentRepository;
    private ThemeBadgePageRepository $badgePageRepository;

    public function __construct(
        array $request,
        $db,
        UserSession $session,
        ?Logger $logger = null,
        ?HttpRequest $httpRequest = null,
        array $config = [],
    ) {
        if (!$session->isAdmin()) {
            $this->respond(['message' => 'Недостаточно прав.', 'type' => 'error'], 403);
        }

        $this->db = $db;
        $this->session = $session;
        $this->logger = $logger ?? new Logger('lastlog');
        $this->request = $request;
        if (!$httpRequest instanceof HttpRequest) {
            throw new RuntimeException('Admin uploads require the original HTTP request.');
        }
        $this->uploads = new UploadService($db, $session, $this->logger, $httpRequest);
        $site = is_array($config['siteSettings'] ?? null) ? $config['siteSettings'] : [];
        $this->slidesRepository = new ThemeSlidesRepository(
            TEMPLATE_DIR,
            (string)($site['siteTpl'] ?? ''),
        );
        $this->contentRepository = new ThemeContentRepository(
            TEMPLATE_DIR,
            (string)($site['siteTpl'] ?? ''),
        );
        $this->badgePageRepository = new ThemeBadgePageRepository(
            TEMPLATE_DIR,
            (string)($site['siteTpl'] ?? ''),
        );
        $this->maintenanceRepository = new MaintenanceModeRepository($db);
        $this->groupRepository = new GroupRepository($db);
        $action = (string)($this->request['admPanel'] ?? '');

        try {
            switch ($action) {
                case 'overview':
                    $this->overview();
                    break;
                case 'users':
                    $this->users();
                    break;
                case 'updateUser':
                    $this->updateUser();
                    break;
                case 'servers':
                    $this->servers();
                    break;
                case 'saveServer':
                    $this->saveServer();
                    break;
                case 'deleteServer':
                    $this->deleteServer();
                    break;
                case 'hardware':
                    $this->hardware();
                    break;
                case 'maintenance':
                    $this->maintenance();
                    break;
                case 'saveMaintenance':
                    $this->saveMaintenance();
                    break;
                case 'log':
                    $this->log(false);
                    break;
                case 'clearLog':
                    $this->log(true);
                    break;
                case 'slides':
                    $this->slides();
                    break;
                case 'saveSlides':
                    $this->saveSlides();
                    break;
                case 'uploadSlideImage':
                    $this->uploadSlideImage();
                    break;
                case 'content':
                    $this->content();
                    break;
                case 'saveProjectPages':
                    $this->saveProjectPages();
                    break;
                case 'saveBadgePage':
                    $this->saveBadgePage();
                    break;
                case 'deleteBadgePage':
                    $this->deleteBadgePage();
                    break;
                case 'fileList':
                    $this->fileList();
                    break;
                case 'fileCreateDirectory':
                    $this->fileCreateDirectory();
                    break;
                case 'fileUpload':
                    $this->fileUpload();
                    break;
                case 'fileRename':
                    $this->fileRename();
                    break;
                case 'fileDelete':
                    $this->fileDelete();
                    break;
                case 'catalog':
                    $this->catalog();
                    break;
                case 'saveCatalogEntry':
                    $this->saveCatalogEntry();
                    break;
                case 'deleteCatalogEntry':
                    $this->deleteCatalogEntry();
                    break;
                default:
                    $this->respond(['message' => 'Неизвестная административная операция.', 'type' => 'error'], 400);
            }
        } catch (Throwable $error) {
            $requestId = $this->errorRequestId();
            $exception = $error::class;
            $detail = $this->exceptionDetail($error);
            try {
                $this->logger?->logError('Admin operation failed.', [
                    'requestId' => $requestId,
                    'action' => $action,
                    'exception' => $exception,
                    'message' => $detail,
                    'file' => $error->getFile(),
                    'line' => $error->getLine(),
                    'trace' => $error->getTraceAsString(),
                ]);
            } catch (Throwable $loggingError) {
                error_log('[FoxesCraft admin][' . $requestId . '] Logger failed: '
                    . $loggingError::class . ': ' . $loggingError->getMessage());
            }
            $this->respond([
                'message' => 'Ошибка операции «' . ($action !== '' ? $action : 'unknown') . '»: '
                    . $exception . ' — ' . $detail . ' Код события: ' . $requestId . '.',
                'type' => 'error',
                'requestId' => $requestId,
                'error' => [
                    'action' => $action !== '' ? $action : 'unknown',
                    'exception' => $exception,
                    'detail' => $detail,
                    'requestId' => $requestId,
                ],
            ], 500);
        }
    }

    private function overview(): void {
        $users = (int)$this->scalar('SELECT COUNT(*) FROM `users`');
        $recent = (int)$this->scalar('SELECT COUNT(*) FROM `users` WHERE `last_date` >= :threshold', [':threshold' => time() - 86400]);
        $servers = (int)$this->scalar('SELECT COUNT(*) FROM `servers`');
        $enabledServers = (int)$this->scalar("SELECT COUNT(*) FROM `servers` WHERE `enabled` = 'true'");
        $hardware = (int)$this->scalar('SELECT COUNT(*) FROM `user_hardware_reports`');

        $this->respond([
            'users' => $users,
            'recentUsers' => $recent,
            'servers' => $servers,
            'enabledServers' => $enabledServers,
            'hardwareReports' => $hardware,
        ]);
    }

    private function users(): void {
        $search = trim((string)($this->request['search'] ?? ''));
        $limit = max(1, min(200, (int)($this->request['limit'] ?? 100)));
        $offset = max(0, (int)($this->request['offset'] ?? 0));
        $where = '';
        $params = [];
        if ($search !== '') {
            $where = ' WHERE `user`.`login` LIKE :search OR `user`.`email` LIKE :search OR `user`.`realname` LIKE :search';
            $params[':search'] = '%' . $search . '%';
        }

        $sql = 'SELECT `user`.`uuid`, `user`.`user_id`, `user`.`login`, `user`.`email`, '
            . '`user`.`realname`, `user`.`groupTag`, `user`.`last_date`, `user`.`reg_date`, '
            . '`user`.`profilePhoto`, `user`.`userStatus`, `user`.`balance`, `user`.`badges`, '
            . '`user`.`serversOnline`, `group`.`groupName`, `group`.`groupColor` '
            . 'FROM `users` AS `user` '
            . 'LEFT JOIN `groupAssociation` AS `group` ON `group`.`groupTag` = `user`.`groupTag`'
            . $where . ' ORDER BY `user`.`last_date` DESC LIMIT ' . $limit . ' OFFSET ' . $offset;
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $this->respond([
            'items' => $rows ?: [],
            'groups' => $this->groupRepository->all(),
            'badgeOptions' => $this->badgeOptions(),
            'limit' => $limit,
            'offset' => $offset,
        ]);
    }

    private function updateUser(): void {
        $userUuid = (string)($this->request['userUuid'] ?? '');
        if (!Uuid::isValid($userUuid)) {
            $this->respond(['message' => 'Некорректный UUID пользователя.', 'type' => 'error'], 400);
        }
        $userUuid = $this->resolveStoredUserUuid($userUuid);

        $payload = $this->decodeObject('entry');
        $updates = [];
        foreach (self::USER_FIELDS as $field) {
            if (!array_key_exists($field, $payload)) continue;
            $value = $payload[$field];
            if ($field === 'login') {
                $value = trim((string)$value);
                if (preg_match('/^[A-Za-z0-9_.-]{3,64}$/D', $value) !== 1) {
                    $this->respond(['message' => 'Некорректный логин.', 'type' => 'error'], 400);
                }
                $duplicate = $this->db->prepare('SELECT `uuid` FROM `users` WHERE `login` = :login AND `uuid` <> :userUuid LIMIT 1');
                $duplicate->execute([':login' => $value, ':userUuid' => $userUuid]);
                if ($duplicate->fetchColumn() !== false) {
                    $this->respond(['message' => 'Логин уже используется.', 'type' => 'error'], 409);
                }
            }
            if ($field === 'email') {
                $value = mb_strtolower(trim((string)$value));
                if (filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
                    $this->respond(['message' => 'Некорректный email.', 'type' => 'error'], 400);
                }
                $duplicate = $this->db->prepare('SELECT `uuid` FROM `users` WHERE `email` = :email AND `uuid` <> :userUuid LIMIT 1');
                $duplicate->execute([':email' => $value, ':userUuid' => $userUuid]);
                if ($duplicate->fetchColumn() !== false) {
                    $this->respond(['message' => 'Email уже используется.', 'type' => 'error'], 409);
                }
            }
            if ($field === 'groupTag') {
                $value = GroupRepository::normalizeTag($value, '');
                if ($value === '' || !$this->groupRepository->exists($value)) {
                    $this->respond(['message' => 'Выбранная группа не существует.', 'type' => 'error'], 400);
                }
            }
            if (in_array($field, ['balance', 'badges', 'serversOnline'], true)) {
                if (is_array($value) || is_object($value)) {
                    $value = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
                }
                $value = (string)$value;
                if ($value !== '' && in_array($value[0], ['{', '['], true)) {
                    json_decode($value, true, 32, JSON_THROW_ON_ERROR);
                }
            }
            $updates[$field] = is_string($value) ? trim($value) : $value;
        }
        if ($updates === []) {
            $this->respond(['message' => 'Нет данных для обновления.', 'type' => 'error'], 400);
        }

        $parts = [];
        $parameters = [':userUuid' => $userUuid];
        foreach ($updates as $field => $value) {
            $placeholder = ':field_' . $field;
            $parts[] = '`' . $field . '` = ' . $placeholder;
            $parameters[$placeholder] = $value;
        }
        $statement = $this->db->prepare(
            'UPDATE `users` SET ' . implode(', ', $parts) . ' WHERE `uuid` = :userUuid'
        );
        $statement->execute($parameters);
        $this->respond(['message' => 'Пользователь обновлён.', 'type' => 'success']);
    }

    private function servers(): void {
        $stmt = $this->db->prepare('SELECT `id`, ' . $this->quotedFields(self::SERVER_FIELDS) . ' FROM `servers` ORDER BY `serverName`');
        $stmt->execute();
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($items as &$server) {
            $server['serverGroups'] = $this->normalizeGroupList($server['serverGroups'] ?? []);
        }
        unset($server);
        $this->respond(['items' => $items, 'groups' => $this->groupRepository->all()]);
    }

    private function saveServer(): void {
        $payload = $this->decodeObject('entry');
        $originalName = trim((string)($this->request['originalName'] ?? ''));
        $serverName = trim((string)($payload['serverName'] ?? ''));
        if (!preg_match('/^[\p{L}\p{N}_ -]{1,64}$/u', $serverName)) {
            $this->respond(['message' => 'Некорректное имя сервера.', 'type' => 'error'], 400);
        }

        $data = [];
        foreach (self::SERVER_FIELDS as $field) {
            if (!array_key_exists($field, $payload)) continue;
            $value = $payload[$field];
            if (in_array($field, ['enabled', 'checkLib'], true)) {
                $value = filter_var($value, FILTER_VALIDATE_BOOLEAN) ? 'true' : 'false';
            }
            if ($field === 'port') {
                $value = filter_var($value, FILTER_VALIDATE_INT);
                if ($value === false || $value < 1 || $value > 65535) {
                    $this->respond(['message' => 'Некорректный порт.', 'type' => 'error'], 400);
                }
            }
            if ($field === 'serverGroups') {
                $value = json_encode(
                    $this->normalizeGroupList($value),
                    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
                );
            } elseif (in_array($field, ['ignoreDirs', 'modsInfo'], true) && (is_array($value) || is_object($value))) {
                $value = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
            }
            $data[$field] = is_string($value) ? trim($value) : $value;
        }
        $data['serverName'] = $serverName;

        if ($originalName !== '') {
            $parts = [];
            $params = [':originalName' => $originalName];
            foreach ($data as $field => $value) {
                $placeholder = ':field_' . $field;
                $parts[] = '`' . $field . '` = ' . $placeholder;
                $params[$placeholder] = $value;
            }
            $stmt = $this->db->prepare('UPDATE `servers` SET ' . implode(', ', $parts) . ' WHERE `serverName` = :originalName');
            $stmt->execute($params);
        } else {
            $fields = array_keys($data);
            $placeholders = array_map(fn($field) => ':' . $field, $fields);
            $params = [];
            foreach ($data as $field => $value) $params[':' . $field] = $value;
            $stmt = $this->db->prepare('INSERT INTO `servers` (' . $this->quotedFields($fields) . ') VALUES (' . implode(', ', $placeholders) . ')');
            $stmt->execute($params);
        }
        $this->respond(['message' => 'Сервер сохранён.', 'type' => 'success']);
    }

    private function deleteServer(): void {
        $serverName = trim((string)($this->request['serverName'] ?? ''));
        if ($serverName === '') $this->respond(['message' => 'Имя сервера не указано.', 'type' => 'error'], 400);
        $stmt = $this->db->prepare('DELETE FROM `servers` WHERE `serverName` = :serverName');
        $stmt->execute([':serverName' => $serverName]);
        $this->respond(['message' => 'Сервер удалён.', 'type' => 'success']);
    }


    private function maintenance(): void {
        $this->respond([
            'settings' => $this->maintenanceRepository->current(true),
            'groups' => $this->groupRepository->all(),
        ]);
    }

    private function saveMaintenance(): void {
        $payload = $this->decodeObject('entry');
        $enabled = filter_var($payload['enabled'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $requestedGroups = is_array($payload['allowedGroups'] ?? null)
            ? $payload['allowedGroups']
            : [];
        $allowedGroups = ['admin'];
        foreach ($requestedGroups as $group) {
            $tag = GroupRepository::normalizeTag($group, '');
            if ($tag !== '' && $this->groupRepository->exists($tag)) {
                $allowedGroups[] = $tag;
            }
        }

        $title = trim((string)($payload['title'] ?? ''));
        $message = trim((string)($payload['message'] ?? ''));
        if (mb_strlen($title) > 160 || mb_strlen($message) > 1200) {
            $this->respond([
                'message' => 'Текст режима техработ превышает допустимую длину.',
                'type' => 'error',
            ], 400);
        }

        $settings = $this->maintenanceRepository->save(
            $enabled,
            array_values(array_unique($allowedGroups)),
            $title,
            $message,
            $this->session->uuid(),
        );
        $this->respond([
            'message' => $enabled ? 'Режим технических работ включён.' : 'Режим технических работ отключён.',
            'type' => 'success',
            'settings' => $settings,
        ]);
    }

    private function hardware(): void {
        $stmt = $this->db->prepare('SELECT `cpu`, `gpus` FROM `user_hardware_reports`');
        $stmt->execute();
        $cpu = ['AMD' => 0, 'Intel' => 0, 'Other' => 0];
        $gpu = ['NVIDIA' => 0, 'AMD' => 0, 'Intel' => 0, 'Other' => 0];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $cpuName = (string)($row['cpu'] ?? '');
            $cpuVendor = preg_match('/AMD|Ryzen/i', $cpuName) ? 'AMD' : (preg_match('/Intel/i', $cpuName) ? 'Intel' : 'Other');
            $cpu[$cpuVendor]++;
            $gpus = json_decode((string)($row['gpus'] ?? '[]'), true);
            foreach (is_array($gpus) ? $gpus : [] as $gpuName) {
                $vendor = preg_match('/NVIDIA/i', (string)$gpuName) ? 'NVIDIA' : (preg_match('/AMD|Radeon/i', (string)$gpuName) ? 'AMD' : (preg_match('/Intel/i', (string)$gpuName) ? 'Intel' : 'Other'));
                $gpu[$vendor]++;
            }
        }
        $this->respond(['cpu' => $cpu, 'gpu' => $gpu]);
    }

    private function log(bool $clear): void {
        $name = (string)($this->request['file'] ?? 'lastlog');
        if (!in_array($name, self::LOG_FILES, true)) {
            $this->respond(['message' => 'Недопустимый log-файл.', 'type' => 'error'], 400);
        }
        $path = ENGINE_DIR . 'cache/logs/' . $name . '.log';
        if ($clear) {
            if (is_file($path) && !file_put_contents($path, '', LOCK_EX)) {
                $this->respond(['message' => 'Не удалось очистить log.', 'type' => 'error'], 500);
            }
            $this->respond(['message' => 'Log очищен.', 'type' => 'success']);
        }
        $lineCount = max(1, min(500, (int)($this->request['lines'] ?? 100)));
        $lines = is_file($path) ? $this->tail($path, $lineCount) : [];
        $this->respond([
            'file' => $name,
            'entries' => array_map(fn(string $line): array => $this->parseLogLine($line), $lines),
        ]);
    }

    private function slides(): void {
        $this->respond([
            'settings' => $this->slidesRepository->read(),
            'routes' => $this->slidesRepository->routes(),
        ]);
    }

    private function saveSlides(): void {
        $payload = $this->decodeObject('entry');
        try {
            $settings = $this->slidesRepository->save($payload);
        } catch (InvalidArgumentException $error) {
            $this->respond(['message' => $error->getMessage(), 'type' => 'error'], 400);
        }
        $this->logger?->logInfo('Theme slides saved.', [
            'event' => 'theme.slides.saved',
            'administratorUuid' => $this->session->uuid(),
            'slidesCount' => count($settings['slides'] ?? []),
            'enabledCount' => count(array_filter(
                $settings['slides'] ?? [],
                static fn (array $slide): bool => ($slide['enabled'] ?? false) === true,
            )),
        ]);
        $this->respond([
            'message' => 'Слайды сохранены в JSON.',
            'type' => 'success',
            'settings' => $settings,
        ]);
    }

    private function uploadSlideImage(): void {
        try {
            $result = $this->uploads->store(
                UploadPurpose::SLIDER_IMAGE,
                is_array($this->request['_slideUpload'] ?? null) ? $this->request['_slideUpload'] : null,
            );
        } catch (UploadException $error) {
            $this->respond(['message' => $error->getMessage(), 'type' => 'error'], $error->httpStatus());
        }
        $this->respond([
            'message' => 'Изображение слайда загружено.',
            'type' => 'success',
            'image' => $result->publicPath(),
            'upload' => $result,
        ], 201);
    }

    private function content(): void {
        $statement = $this->db->prepare(
            'SELECT `id`, `badgeName`, `description`, `img` FROM `badgesList` ORDER BY `badgeName`, `id`'
        );
        $statement->execute();
        $badges = BadgeSlug::assign($statement->fetchAll(PDO::FETCH_ASSOC) ?: []);
        $badgePages = [];

        foreach ($badges as &$badge) {
            $slug = (string)($badge['pageSlug'] ?? '');
            $configured = $slug !== '' && $this->badgePageRepository->exists($slug);
            $badge['pageConfigured'] = $configured;
            if (!$configured) {
                continue;
            }
            try {
                $page = $this->badgePageRepository->read($slug);
                if (is_array($page)) {
                    $page['badgeName'] = (string)($badge['badgeName'] ?? '');
                    $page['slug'] = $slug;
                    $badgePages[] = $page;
                }
            } catch (Throwable $error) {
                $badge['pageConfigured'] = false;
                $this->logger?->logWarn('Invalid badge HTML page skipped in admin content.', [
                    'event' => 'theme.content.badge_html.invalid',
                    'badgeName' => (string)($badge['badgeName'] ?? ''),
                    'slug' => $slug,
                    'exception' => $error::class,
                    'message' => $error->getMessage(),
                ]);
            }
        }
        unset($badge);

        $this->respond([
            'projectPages' => $this->contentRepository->readProjectPages(),
            'badgePages' => ['pages' => $badgePages],
            'badges' => array_values($badges),
        ]);
    }

    private function saveProjectPages(): void {
        $payload = $this->decodeObject('entry');
        try {
            $document = $this->contentRepository->saveProjectPages($payload);
        } catch (InvalidArgumentException $error) {
            $this->respond(['message' => $error->getMessage(), 'type' => 'error'], 400);
        }
        $this->logger?->logInfo('Theme project pages saved.', [
            'event' => 'theme.content.project_pages.saved',
            'administratorUuid' => $this->session->uuid(),
            'pagesCount' => count($document['pages'] ?? []),
        ]);
        $this->respond([
            'message' => 'HTML-страницы проекта сохранены в data/pages.',
            'type' => 'success',
            'document' => $document,
        ]);
    }

    private function saveBadgePage(): void {
        $payload = $this->decodeObject('entry');
        $requestedName = trim((string)($payload['badgeName'] ?? ''));
        $statement = $this->db->prepare(
            'SELECT `id`, `badgeName`, `description`, `img` FROM `badgesList` ORDER BY `badgeName`, `id`'
        );
        $statement->execute();
        $badges = BadgeSlug::assign($statement->fetchAll(PDO::FETCH_ASSOC) ?: []);
        $badge = null;
        foreach ($badges as $candidate) {
            if (is_array($candidate) && hash_equals((string)($candidate['badgeName'] ?? ''), $requestedName)) {
                $badge = $candidate;
                break;
            }
        }
        if (!is_array($badge)) {
            $this->respond(['message' => 'Бейдж для HTML-страницы не найден в badgesList.', 'type' => 'error'], 404);
        }

        $slug = (string)($badge['pageSlug'] ?? '');
        try {
            $page = $this->badgePageRepository->save(
                $payload,
                (string)$badge['badgeName'],
                $slug,
            );
        } catch (InvalidArgumentException $error) {
            $this->logger?->logWarn('Badge HTML page validation rejected.', [
                'event' => 'theme.content.badge_html.rejected',
                'administratorUuid' => $this->session->uuid(),
                'badgeName' => (string)$badge['badgeName'],
                'slug' => $slug,
                'message' => $error->getMessage(),
            ]);
            $this->respond(['message' => $error->getMessage(), 'type' => 'error'], 400);
        } catch (RuntimeException $error) {
            $this->logger?->logError('Badge HTML page storage failed.', [
                'event' => 'theme.content.badge_html.storage_failed',
                'administratorUuid' => $this->session->uuid(),
                'badgeName' => (string)$badge['badgeName'],
                'slug' => $slug,
                'exception' => $error::class,
                'message' => $error->getMessage(),
                'file' => $error->getFile(),
                'line' => $error->getLine(),
            ]);
            $this->respond(['message' => $error->getMessage(), 'type' => 'error'], 500);
        }
        $this->logger?->logInfo('Individual theme badge HTML page saved.', [
            'event' => 'theme.content.badge_html.saved',
            'administratorUuid' => $this->session->uuid(),
            'badgeName' => (string)$page['badgeName'],
            'slug' => (string)$page['slug'],
            'file' => 'data/badges/' . (string)$page['slug'] . '.html',
        ]);
        $this->respond([
            'message' => 'HTML-страница бейджа сохранена.',
            'type' => 'success',
            'page' => $page,
        ]);
    }

    private function deleteBadgePage(): void {
        $slug = trim((string)($this->request['slug'] ?? ''));
        try {
            $this->badgePageRepository->delete($slug);
        } catch (InvalidArgumentException $error) {
            $this->respond(['message' => $error->getMessage(), 'type' => 'error'], 400);
        }
        $this->logger?->logInfo('Individual theme badge HTML page deleted.', [
            'event' => 'theme.content.badge_html.deleted',
            'administratorUuid' => $this->session->uuid(),
            'slug' => $slug,
            'file' => 'data/badges/' . $slug . '.html',
        ]);
        $this->respond([
            'message' => 'HTML-файл страницы удалён. Запись бейджа в БД сохранена.',
            'type' => 'success',
            'slug' => $slug,
        ]);
    }

    private function fileList(): void {
        $directory = $this->resolveUploadPath($this->request['path'] ?? '', true);
        if (!is_dir($directory)) {
            $this->respond(['message' => 'Каталог не найден.', 'type' => 'error'], 404);
        }

        $root = $this->uploadsRoot();
        $items = [];
        foreach (new DirectoryIterator($directory) as $entry) {
            if ($entry->isDot() || $entry->isLink()) {
                continue;
            }
            $name = $entry->getFilename();
            if ($name === '' || str_starts_with($name, '.')) {
                continue;
            }
            $absolute = $entry->getPathname();
            $relative = $this->relativeUploadPath($absolute);
            $directoryEntry = $entry->isDir();
            $items[] = [
                'name' => $name,
                'path' => $relative,
                'type' => $directoryEntry ? 'directory' : 'file',
                'size' => $directoryEntry ? 0 : max(0, (int)$entry->getSize()),
                'modified' => max(0, (int)$entry->getMTime()),
                'extension' => $directoryEntry ? '' : strtolower($entry->getExtension()),
                'mime' => $directoryEntry ? 'inode/directory' : $this->fileMime($absolute),
                'url' => $directoryEntry ? '' : $this->publicUploadUrl($relative),
            ];
        }
        usort($items, static function (array $left, array $right): int {
            if ($left['type'] !== $right['type']) {
                return $left['type'] === 'directory' ? -1 : 1;
            }
            return strnatcasecmp((string)$left['name'], (string)$right['name']);
        });

        $relative = $this->relativeUploadPath($directory);
        $parent = $relative === '' ? null : dirname(str_replace('/', DIRECTORY_SEPARATOR, $relative));
        if ($parent === '.' || $parent === DIRECTORY_SEPARATOR) {
            $parent = '';
        }
        $this->respond([
            'root' => '/uploads',
            'path' => $relative,
            'parent' => is_string($parent) ? str_replace(DIRECTORY_SEPARATOR, '/', $parent) : null,
            'items' => $items,
            'writable' => is_writable($directory),
            'totalBytes' => array_sum(array_column($items, 'size')),
        ]);
    }

    private function fileCreateDirectory(): void {
        $directory = $this->resolveUploadPath($this->request['path'] ?? '', true);
        $name = $this->safeFileName($this->request['name'] ?? '');
        $target = $directory . DIRECTORY_SEPARATOR . $name;
        if (file_exists($target)) {
            $this->respond(['message' => 'Файл или каталог с таким именем уже существует.', 'type' => 'error'], 409);
        }
        if (!mkdir($target, 0755)) {
            $this->respond(['message' => 'Не удалось создать каталог.', 'type' => 'error'], 500);
        }
        $this->logger?->logInfo('Admin file directory created', [
            'adminUuid' => $this->session->uuid(),
            'path' => $this->relativeUploadPath($target),
        ]);
        $this->respond(['message' => 'Каталог создан.', 'type' => 'success']);
    }

    private function fileUpload(): void {
        try {
            $result = $this->uploads->store(
                UploadPurpose::ADMIN_FILE,
                is_array($this->request['_upload'] ?? null) ? $this->request['_upload'] : null,
                ['directory' => (string)($this->request['path'] ?? '')],
            );
        } catch (UploadException $error) {
            $this->respond([
                'message' => $error->getMessage(),
                'type' => 'error',
            ], $error->httpStatus());
        }

        $this->respond([
            'message' => 'Файл загружен без изменений.',
            'type' => 'success',
            'path' => $result->relativePath(),
            'url' => $result->publicPath(),
            'size' => $result->size(),
            'sha256' => $result->sha256(),
            'mime' => $result->mime(),
            'upload' => $result,
        ], 201);
    }

    private function fileRename(): void {
        $source = $this->resolveUploadPath($this->request['path'] ?? '', false);
        $root = $this->uploadsRoot();
        if ($source === $root) {
            $this->respond(['message' => 'Корневой каталог переименовать нельзя.', 'type' => 'error'], 409);
        }
        $name = $this->safeFileName($this->request['name'] ?? '');
        $target = dirname($source) . DIRECTORY_SEPARATOR . $name;
        if (file_exists($target)) {
            $this->respond(['message' => 'Файл или каталог с таким именем уже существует.', 'type' => 'error'], 409);
        }
        if (!rename($source, $target)) {
            $this->respond(['message' => 'Не удалось переименовать объект.', 'type' => 'error'], 500);
        }
        $this->logger?->logInfo('Admin file renamed', [
            'adminUuid' => $this->session->uuid(),
            'from' => $this->relativeUploadPath($source),
            'to' => $this->relativeUploadPath($target),
        ]);
        $this->respond(['message' => 'Объект переименован.', 'type' => 'success']);
    }

    private function fileDelete(): void {
        $target = $this->resolveUploadPath($this->request['path'] ?? '', false);
        if ($target === $this->uploadsRoot()) {
            $this->respond(['message' => 'Корневой каталог удалить нельзя.', 'type' => 'error'], 409);
        }
        $relative = $this->relativeUploadPath($target);
        $this->deleteUploadTree($target);
        $this->logger?->logInfo('Admin file deleted', [
            'adminUuid' => $this->session->uuid(),
            'path' => $relative,
        ]);
        $this->respond(['message' => 'Объект удалён.', 'type' => 'success']);
    }

    private function uploadsRoot(): string {
        $path = ROOT_DIR . UPLOADS_DIR;
        if (!is_dir($path) && !mkdir($path, 0755, true) && !is_dir($path)) {
            $this->respond(['message' => 'Каталог uploads недоступен.', 'type' => 'error'], 500);
        }
        $root = realpath($path);
        if (!is_string($root) || !is_dir($root)) {
            $this->respond(['message' => 'Каталог uploads недоступен.', 'type' => 'error'], 500);
        }
        return rtrim($root, '/\\');
    }

    private function resolveUploadPath(mixed $value, bool $directory): string {
        $root = $this->uploadsRoot();
        $relative = $this->safeRelativeUploadPath($value);
        $candidate = $relative === ''
            ? $root
            : $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
        $this->rejectUploadSymlinkPath($relative, $root);
        $resolved = realpath($candidate);
        if (!is_string($resolved) || is_link($candidate) || !$this->insideUploadsRoot($resolved, $root)) {
            $this->respond(['message' => 'Файл или каталог не найден.', 'type' => 'error'], 404);
        }
        if ($directory && !is_dir($resolved)) {
            $this->respond(['message' => 'Каталог не найден.', 'type' => 'error'], 404);
        }
        return $resolved;
    }

    private function rejectUploadSymlinkPath(string $relative, string $root): void {
        if ($relative === '') {
            return;
        }
        $cursor = $root;
        foreach (explode('/', $relative) as $segment) {
            $cursor .= DIRECTORY_SEPARATOR . $segment;
            if (is_link($cursor)) {
                $this->respond(['message' => 'Переход через символическую ссылку запрещён.', 'type' => 'error'], 409);
            }
        }
    }

    private function safeRelativeUploadPath(mixed $value): string {
        $value = trim(str_replace('\\', '/', (string)$value), '/');
        if ($value === '') {
            return '';
        }
        if (str_contains($value, "\0")) {
            $this->respond(['message' => 'Недопустимый путь.', 'type' => 'error'], 400);
        }
        $segments = explode('/', $value);
        foreach ($segments as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..' || str_starts_with($segment, '.')) {
                $this->respond(['message' => 'Недопустимый путь.', 'type' => 'error'], 400);
            }
            $this->safeFileName($segment);
        }
        return implode('/', $segments);
    }

    private function safeFileName(mixed $value): string {
        $name = trim((string)$value);
        if ($name === '' || $name === '.' || $name === '..' || str_starts_with($name, '.')
            || str_contains($name, '/') || str_contains($name, '\\') || str_contains($name, "\0")
            || preg_match('/[\x00-\x1f\x7f]/u', $name) === 1 || mb_strlen($name) > 180) {
            $this->respond(['message' => 'Недопустимое имя файла или каталога.', 'type' => 'error'], 400);
        }
        return $name;
    }

    private function insideUploadsRoot(string $path, string $root): bool {
        return $path === $root || str_starts_with($path, $root . DIRECTORY_SEPARATOR);
    }

    private function relativeUploadPath(string $path): string {
        $root = $this->uploadsRoot();
        $relative = ltrim(substr($path, strlen($root)), '/\\');
        return str_replace(DIRECTORY_SEPARATOR, '/', $relative);
    }

    private function publicUploadUrl(string $relative): string {
        $segments = array_map('rawurlencode', explode('/', $relative));
        return rtrim(UPLOADS_DIR, '/') . '/' . implode('/', $segments);
    }

    private function fileMime(string $path): string {
        try {
            $mime = (new finfo(FILEINFO_MIME_TYPE))->file($path);
            return is_string($mime) && $mime !== '' ? $mime : 'application/octet-stream';
        } catch (Throwable) {
            return 'application/octet-stream';
        }
    }

    private function deleteUploadTree(string $path): void {
        if (is_link($path)) {
            $this->respond(['message' => 'Символические ссылки удалять через File Manager запрещено.', 'type' => 'error'], 409);
        }
        if (is_file($path)) {
            if (!unlink($path)) {
                $this->respond(['message' => 'Не удалось удалить файл.', 'type' => 'error'], 500);
            }
            return;
        }
        if (!is_dir($path)) {
            $this->respond(['message' => 'Файл или каталог не найден.', 'type' => 'error'], 404);
        }
        foreach (new DirectoryIterator($path) as $entry) {
            if ($entry->isDot()) {
                continue;
            }
            $child = $entry->getPathname();
            if ($entry->isLink()) {
                $this->respond(['message' => 'Каталог содержит символическую ссылку и не может быть удалён.', 'type' => 'error'], 409);
            }
            $this->deleteUploadTree($child);
        }
        if (!rmdir($path)) {
            $this->respond(['message' => 'Не удалось удалить каталог.', 'type' => 'error'], 500);
        }
    }

    private function catalog(): void {
        [$spec] = $this->catalogSpec();
        $stmt = $this->db->prepare('SELECT ' . $this->quotedFields($spec['fields']) . ' FROM `' . $spec['table'] . '` ORDER BY `' . $spec['key'] . '`');
        $stmt->execute();
        $this->respond([
            'items' => $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [],
            'fields' => array_values($spec['fields']),
        ]);
    }

    private function saveCatalogEntry(): void {
        [$spec, $catalog] = $this->catalogSpec();
        if ($catalog === 'groups') {
            $this->saveGroupCatalogEntry();
        }
        if ($catalog === 'badges') {
            $this->saveBadgeCatalogEntry();
        }

        $payload = $this->decodeObject('entry');
        $originalKey = trim((string)($this->request['originalKey'] ?? ''));
        $keyField = $spec['key'];
        $keyValue = trim((string)($payload[$keyField] ?? ''));
        if ($keyValue === '') $this->respond(['message' => 'Ключ записи не указан.', 'type' => 'error'], 400);
        $data = [];
        foreach ($spec['fields'] as $field) if (array_key_exists($field, $payload)) $data[$field] = is_string($payload[$field]) ? trim($payload[$field]) : $payload[$field];
        $data[$keyField] = $keyValue;

        if ($originalKey !== '') {
            $parts = [];
            $params = [':originalKey' => $originalKey];
            foreach ($data as $field => $value) {
                $placeholder = ':field_' . $field;
                $parts[] = '`' . $field . '` = ' . $placeholder;
                $params[$placeholder] = $value;
            }
            $stmt = $this->db->prepare('UPDATE `' . $spec['table'] . '` SET ' . implode(', ', $parts) . ' WHERE `' . $keyField . '` = :originalKey');
            $stmt->execute($params);
        } else {
            $fields = array_keys($data);
            $placeholders = array_map(fn($field) => ':' . $field, $fields);
            $params = [];
            foreach ($data as $field => $value) $params[':' . $field] = $value;
            $stmt = $this->db->prepare('INSERT INTO `' . $spec['table'] . '` (' . $this->quotedFields($fields) . ') VALUES (' . implode(', ', $placeholders) . ')');
            $stmt->execute($params);
        }
        $this->respond(['message' => 'Запись сохранена.', 'type' => 'success']);
    }

    private function deleteCatalogEntry(): void {
        [$spec, $catalog] = $this->catalogSpec();
        if ($catalog === 'groups') {
            $this->deleteGroupCatalogEntry();
        }
        $key = trim((string)($this->request['key'] ?? ''));
        if ($key === '') $this->respond(['message' => 'Ключ не указан.', 'type' => 'error'], 400);
        $stmt = $this->db->prepare('DELETE FROM `' . $spec['table'] . '` WHERE `' . $spec['key'] . '` = :key');
        $stmt->execute([':key' => $key]);
        $this->respond(['message' => 'Запись удалена.', 'type' => 'success']);
    }

    private function saveBadgeCatalogEntry(): never {
        $payload = $this->decodeObject('entry');
        $originalName = trim((string)($this->request['originalKey'] ?? ''));
        $badgeName = trim((string)($payload['badgeName'] ?? ''));
        $description = trim((string)($payload['description'] ?? ''));
        $image = trim(str_replace('\\', '/', (string)($payload['img'] ?? '')));

        if ($badgeName === '' || mb_strlen($badgeName) > 120
            || preg_match('/[\x00-\x1F\x7F]/u', $badgeName) === 1) {
            $this->respond([
                'message' => 'Название бейджа должно содержать от 1 до 120 печатных Unicode-символов.',
                'type' => 'error',
            ], 400);
        }
        if (mb_strlen($description) > 4000 || str_contains($description, "\0")) {
            $this->respond([
                'message' => 'Краткое описание бейджа не должно превышать 4000 символов.',
                'type' => 'error',
            ], 400);
        }
        $this->validateBadgeImageReference($image);

        $statement = $this->db->prepare(
            'SELECT `id`, `badgeName`, `description`, `img` FROM `badgesList` ORDER BY `badgeName`, `id`'
        );
        $statement->execute();
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $originalRow = null;
        foreach ($rows as $row) {
            if (is_array($row) && hash_equals((string)($row['badgeName'] ?? ''), $originalName)) {
                $originalRow = $row;
                break;
            }
        }
        if ($originalName !== '' && !is_array($originalRow)) {
            $this->respond(['message' => 'Бейдж для обновления не найден.', 'type' => 'error'], 404);
        }
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $rowName = (string)($row['badgeName'] ?? '');
            $sameRecord = is_array($originalRow)
                && (int)($row['id'] ?? 0) === (int)($originalRow['id'] ?? 0);
            if (!$sameRecord && mb_strtolower($rowName, 'UTF-8') === mb_strtolower($badgeName, 'UTF-8')) {
                $this->respond(['message' => 'Бейдж с таким названием уже существует.', 'type' => 'error'], 409);
            }
        }

        $oldSlug = null;
        $newSlug = null;
        if (is_array($originalRow)) {
            foreach (BadgeSlug::assign($rows) as $assigned) {
                if ((int)($assigned['id'] ?? 0) === (int)($originalRow['id'] ?? 0)) {
                    $oldSlug = (string)($assigned['pageSlug'] ?? '');
                    break;
                }
            }
            $prospectiveRows = $rows;
            foreach ($prospectiveRows as &$row) {
                if (is_array($row) && (int)($row['id'] ?? 0) === (int)($originalRow['id'] ?? 0)) {
                    $row['badgeName'] = $badgeName;
                }
            }
            unset($row);
            foreach (BadgeSlug::assign($prospectiveRows) as $assigned) {
                if ((int)($assigned['id'] ?? 0) === (int)($originalRow['id'] ?? 0)) {
                    $newSlug = (string)($assigned['pageSlug'] ?? '');
                    break;
                }
            }
        }

        $pageMoved = false;
        try {
            $this->db->beginTransaction();
            if (is_array($originalRow)) {
                $update = $this->db->prepare(
                    'UPDATE `badgesList` SET `badgeName` = :badgeName, `description` = :description, `img` = :image '
                    . 'WHERE `id` = :id'
                );
                $update->execute([
                    ':badgeName' => $badgeName,
                    ':description' => $description,
                    ':image' => $image,
                    ':id' => (int)$originalRow['id'],
                ]);
                if ($originalName !== $badgeName) {
                    $this->renameBadgeAssignments($originalName, $badgeName);
                }
                if (is_string($oldSlug) && is_string($newSlug) && $oldSlug !== $newSlug
                    && $this->badgePageRepository->exists($oldSlug)) {
                    $this->badgePageRepository->move($oldSlug, $newSlug, $badgeName);
                    $pageMoved = true;
                }
            } else {
                $insert = $this->db->prepare(
                    'INSERT INTO `badgesList` (`badgeName`, `description`, `img`) '
                    . 'VALUES (:badgeName, :description, :image)'
                );
                $insert->execute([
                    ':badgeName' => $badgeName,
                    ':description' => $description,
                    ':image' => $image,
                ]);
                $newId = (int)$this->db->lastInsertId();
                if ($newId <= 0) {
                    $lookup = $this->db->prepare(
                        'SELECT `id` FROM `badgesList` WHERE `badgeName` = :badgeName ORDER BY `id` DESC LIMIT 1'
                    );
                    $lookup->execute([':badgeName' => $badgeName]);
                    $newId = max(0, (int)$lookup->fetchColumn());
                }
                $prospectiveRows = $rows;
                $prospectiveRows[] = [
                    'id' => $newId,
                    'badgeName' => $badgeName,
                    'description' => $description,
                    'img' => $image,
                ];
                foreach (BadgeSlug::assign($prospectiveRows) as $assigned) {
                    if ((int)($assigned['id'] ?? 0) === $newId) {
                        $newSlug = (string)($assigned['pageSlug'] ?? '');
                        break;
                    }
                }
            }
            $this->db->commit();
        } catch (Throwable $error) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            if ($pageMoved && is_string($oldSlug) && is_string($newSlug)) {
                try {
                    $this->badgePageRepository->move($newSlug, $oldSlug, $originalName);
                } catch (Throwable $rollbackError) {
                    $this->logger?->logError('Badge HTML page rename rollback failed.', [
                        'from' => $newSlug,
                        'to' => $oldSlug,
                        'exception' => $rollbackError::class,
                        'message' => $rollbackError->getMessage(),
                    ]);
                }
            }
            throw $error;
        }

        $this->logger?->logInfo('Badge catalog entry saved.', [
            'event' => 'catalog.badges.saved',
            'administratorUuid' => $this->session->uuid(),
            'originalBadgeName' => $originalName,
            'badgeName' => $badgeName,
            'pageSlug' => $newSlug,
            'created' => $originalName === '',
        ]);
        $this->respond([
            'message' => 'Бейдж сохранён. URL страницы: /#/badges/' . (string)$newSlug,
            'type' => 'success',
            'pageSlug' => $newSlug,
        ]);
    }

    private function validateBadgeImageReference(string $image): void {
        if ($image === '') {
            return;
        }
        if (strlen($image) > 1024 || str_contains($image, "\0")
            || preg_match('/[\x00-\x1F\x7F]/u', $image) === 1) {
            $this->respond(['message' => 'Некорректный путь к изображению бейджа.', 'type' => 'error'], 400);
        }
        $decodedPath = rawurldecode((string)(parse_url($image, PHP_URL_PATH) ?? $image));
        foreach (explode('/', str_replace('\\', '/', $decodedPath)) as $segment) {
            if ($segment === '..') {
                $this->respond(['message' => 'Переходы .. в пути изображения запрещены.', 'type' => 'error'], 400);
            }
        }
        $scheme = parse_url($image, PHP_URL_SCHEME);
        if (is_string($scheme) && $scheme !== '' && !in_array(strtolower($scheme), ['http', 'https'], true)) {
            $this->respond(['message' => 'Разрешены только локальные изображения и HTTP(S) URL.', 'type' => 'error'], 400);
        }
        if (is_string($scheme) && $scheme !== '' && filter_var($image, FILTER_VALIDATE_URL) === false) {
            $this->respond(['message' => 'Некорректный URL изображения бейджа.', 'type' => 'error'], 400);
        }
        if (str_starts_with($image, '//')) {
            $this->respond(['message' => 'Protocol-relative URL изображения запрещён.', 'type' => 'error'], 400);
        }
    }

    private function renameBadgeAssignments(string $oldName, string $newName): void {
        if ($oldName === $newName) {
            return;
        }
        $statement = $this->db->prepare(
            'SELECT `uuid`, `badges` FROM `users` WHERE `badges` IS NOT NULL AND `badges` <> :empty'
        );
        $statement->execute([':empty' => '']);
        $update = $this->db->prepare('UPDATE `users` SET `badges` = :badges WHERE `uuid` = :uuid');
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            if (!is_array($row)) {
                continue;
            }
            $raw = (string)($row['badges'] ?? '');
            $changed = false;
            $decoded = json_decode($raw, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $decoded = $this->replaceBadgeAssignmentValue($decoded, $oldName, $newName, $changed);
                $encoded = json_encode(
                    $decoded,
                    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
                );
            } elseif (trim($raw) === $oldName) {
                $encoded = $newName;
                $changed = true;
            } else {
                continue;
            }
            if ($changed) {
                $update->execute([
                    ':badges' => $encoded,
                    ':uuid' => (string)$row['uuid'],
                ]);
            }
        }
    }

    private function replaceBadgeAssignmentValue(
        mixed $value,
        string $oldName,
        string $newName,
        bool &$changed,
    ): mixed {
        if (is_string($value)) {
            if ($value === $oldName) {
                $changed = true;
                return $newName;
            }
            return $value;
        }
        if (!is_array($value)) {
            return $value;
        }
        $result = [];
        foreach ($value as $key => $entry) {
            $newKey = is_string($key) && $key === $oldName ? $newName : $key;
            if ($newKey !== $key) {
                $changed = true;
            }
            $result[$newKey] = $this->replaceBadgeAssignmentValue($entry, $oldName, $newName, $changed);
        }
        return $result;
    }

    private function saveGroupCatalogEntry(): never {
        $payload = $this->decodeObject('entry');
        $originalTag = GroupRepository::normalizeTag($this->request['originalKey'] ?? '', '');
        $groupTag = GroupRepository::normalizeTag($payload['groupTag'] ?? '', '');
        $groupName = trim((string)($payload['groupName'] ?? ''));
        $groupColor = strtolower(trim((string)($payload['groupColor'] ?? '#ffffff')));

        if ($groupTag === '') {
            $this->respond(['message' => 'Тег группы должен начинаться с латинской буквы и содержать только a-z, 0-9, _ или -.', 'type' => 'error'], 400);
        }
        if ($groupName === '' || mb_strlen($groupName) > 64) {
            $this->respond(['message' => 'Название группы должно содержать от 1 до 64 символов.', 'type' => 'error'], 400);
        }
        if (preg_match('/^#[0-9a-f]{6}$/D', $groupColor) !== 1) {
            $this->respond(['message' => 'Цвет группы должен быть записан в формате #RRGGBB.', 'type' => 'error'], 400);
        }

        $duplicateName = $this->db->prepare(
            'SELECT `groupTag` FROM `groupAssociation` WHERE `groupName` = :groupName AND `groupTag` <> :groupTag LIMIT 1'
        );
        $duplicateName->execute([':groupName' => $groupName, ':groupTag' => $originalTag !== '' ? $originalTag : $groupTag]);
        if ($duplicateName->fetchColumn() !== false) {
            $this->respond(['message' => 'Группа с таким названием уже существует.', 'type' => 'error'], 409);
        }

        if ($originalTag !== '') {
            if ($groupTag !== $originalTag) {
                $this->respond(['message' => 'Тег группы является стабильным идентификатором и не может быть изменён.', 'type' => 'error'], 409);
            }
            $statement = $this->db->prepare(
                'UPDATE `groupAssociation` SET `groupName` = :groupName, `groupColor` = :groupColor, `groupType` = :groupTag '
                . 'WHERE `groupTag` = :groupTag'
            );
            $statement->execute([
                ':groupName' => $groupName,
                ':groupColor' => $groupColor,
                ':groupTag' => $groupTag,
            ]);
        } else {
            if ($this->groupRepository->exists($groupTag)) {
                $this->respond(['message' => 'Группа с таким тегом уже существует.', 'type' => 'error'], 409);
            }
            $legacyNumber = max(1, (int)$this->scalar('SELECT COALESCE(MAX(`groupNum`), 0) + 1 FROM `groupAssociation`'));
            $statement = $this->db->prepare(
                'INSERT INTO `groupAssociation` (`groupTag`, `groupName`, `groupColor`, `groupNum`, `groupType`) '
                . 'VALUES (:groupTag, :groupName, :groupColor, :groupNum, :groupType)'
            );
            $statement->execute([
                ':groupTag' => $groupTag,
                ':groupName' => $groupName,
                ':groupColor' => $groupColor,
                ':groupNum' => $legacyNumber,
                ':groupType' => $groupTag,
            ]);
        }
        $this->respond(['message' => 'Группа сохранена.', 'type' => 'success']);
    }

    private function deleteGroupCatalogEntry(): never {
        $groupTag = GroupRepository::normalizeTag($this->request['key'] ?? '', '');
        if ($groupTag === '') {
            $this->respond(['message' => 'Тег группы не указан.', 'type' => 'error'], 400);
        }
        if (in_array($groupTag, ['admin', 'user', 'guest'], true)) {
            $this->respond(['message' => 'Системную группу удалить нельзя.', 'type' => 'error'], 409);
        }
        $assignedUsers = (int)$this->scalar('SELECT COUNT(*) FROM `users` WHERE `groupTag` = :groupTag', [':groupTag' => $groupTag]);
        $registrationCodes = (int)$this->scalar('SELECT COUNT(*) FROM `regCodes` WHERE `groupTag` = :groupTag', [':groupTag' => $groupTag]);
        if ($assignedUsers > 0 || $registrationCodes > 0) {
            $this->respond(['message' => 'Группа используется пользователями или регистрационными кодами.', 'type' => 'error'], 409);
        }
        $maintenance = $this->maintenanceRepository->current();
        if (in_array($groupTag, $maintenance['allowedGroups'] ?? [], true)) {
            $this->respond(['message' => 'Сначала удалите группу из доступа во время техработ.', 'type' => 'error'], 409);
        }
        $statement = $this->db->prepare('SELECT `serverGroups` FROM `servers`');
        $statement->execute();
        foreach ($statement->fetchAll(PDO::FETCH_COLUMN) ?: [] as $serverGroups) {
            if (in_array($groupTag, $this->normalizeGroupList($serverGroups), true)) {
                $this->respond(['message' => 'Сначала удалите группу из списков доступа серверов.', 'type' => 'error'], 409);
            }
        }
        $statement = $this->db->prepare('DELETE FROM `groupAssociation` WHERE `groupTag` = :groupTag');
        $statement->execute([':groupTag' => $groupTag]);
        $this->respond(['message' => 'Группа удалена.', 'type' => 'success']);
    }

    private function catalogSpec(): array {
        $catalog = (string)($this->request['catalog'] ?? '');
        if (!isset(self::CATALOGS[$catalog])) {
            $this->respond(['message' => 'Неизвестный каталог.', 'type' => 'error'], 400);
        }
        return [self::CATALOGS[$catalog], $catalog];
    }

    private function resolveStoredUserUuid(string $userUuid): string {
        $placeholders = [];
        $parameters = [];
        foreach (Uuid::databaseCandidates($userUuid) as $index => $candidate) {
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
            $this->respond(['message' => 'Пользователь не найден.', 'type' => 'error'], 404);
        }
        return $storedUuid;
    }

    private function decodeObject(string $field): array {
        $value = $this->request[$field] ?? null;
        if (is_array($value)) return $value;
        $decoded = json_decode((string)$value, true);
        if (!is_array($decoded)) $this->respond(['message' => 'Некорректный JSON payload.', 'type' => 'error'], 400);
        return $decoded;
    }

    /** @return list<string> */
    private function normalizeGroupList(mixed $value): array {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            $source = is_array($decoded) ? $decoded : explode(',', $value);
        } elseif (is_array($value)) {
            $source = $value;
        } else {
            $source = [];
        }
        $tags = [];
        foreach ($source as $group) {
            $tag = $this->groupRepository->resolveTag($group, '');
            if ($tag !== '' && $this->groupRepository->exists($tag)) {
                $tags[] = $tag;
            }
        }
        $tags = array_values(array_unique($tags));
        sort($tags, SORT_STRING);
        return $tags;
    }

    /** @return list<string> */
    private function badgeOptions(): array {
        $stmt = $this->db->prepare('SELECT `badgeName` FROM `badgesList` ORDER BY `badgeName`');
        $stmt->execute();
        return array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
    }

    /** @return array{timestamp: string, time: string, level: string, message: string, tone: string} */
    private function parseLogLine(string $line): array {
        $record = json_decode($line, true);
        if (is_array($record)) {
            $timestamp = is_string($record['timestamp'] ?? null) ? $record['timestamp'] : '';
            $level = is_string($record['level'] ?? null) ? strtoupper(trim($record['level'])) : 'LOG';
            $message = is_string($record['message'] ?? null) ? $record['message'] : $line;
            $time = $timestamp;
            if ($timestamp !== '') {
                try { $time = (new DateTimeImmutable($timestamp))->format('d.m.Y H:i:s'); } catch (Throwable) {}
            }
            $level = $level ?: 'LOG';
            $tone = match ($level) {
                'ERROR', 'CRITICAL', 'FATAL' => 'error',
                'WARNING', 'WARN' => 'warning',
                'INFO', 'NOTICE' => 'info',
                'DEBUG', 'TRACE' => 'debug',
                default => 'default',
            };
            return ['timestamp' => $timestamp, 'time' => $time, 'level' => $level, 'message' => $message, 'tone' => $tone];
        }
        return ['timestamp' => '', 'time' => '—', 'level' => 'LOG', 'message' => $line, 'tone' => 'default'];
    }

    private function scalar(string $sql, array $params = []) {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchColumn();
    }

    private function quotedFields(array $fields): string {
        return implode(', ', array_map(fn($field) => '`' . $field . '`', $fields));
    }

    private function tail(string $path, int $count): array {
        $file = new SplFileObject($path, 'r');
        $file->seek(PHP_INT_MAX);
        $lastLine = $file->key();
        $start = max(0, $lastLine - $count);
        $lines = [];
        $file->seek($start);
        while (!$file->eof()) {
            $line = rtrim((string)$file->current(), "\r\n");
            if ($line !== '') $lines[] = $line;
            $file->next();
        }
        return array_slice($lines, -$count);
    }

    private function errorRequestId(): string {
        try {
            return bin2hex(random_bytes(8));
        } catch (Throwable) {
            return substr(hash('sha256', uniqid('admin-error-', true)), 0, 16);
        }
    }

    private function exceptionDetail(Throwable $error): string {
        $detail = trim(str_replace(["\r", "\n", "\t"], ' ', $error->getMessage()));
        $detail = preg_replace('/\s+/u', ' ', $detail) ?? $detail;
        if ($detail === '') {
            $detail = 'Исключение не содержит текстового описания.';
        }
        return mb_substr($detail, 0, 3000, 'UTF-8');
    }

    private function respond(array $payload, int $status = 200): never {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        header('X-Content-Type-Options: nosniff');
        if (isset($payload['requestId']) && is_string($payload['requestId'])) {
            header('X-Request-ID: ' . $payload['requestId']);
        }
        die(json_encode(
            $payload,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE,
        ));
    }
}
