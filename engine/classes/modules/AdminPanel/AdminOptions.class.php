<?php

declare(strict_types=1);

if (!defined('ADMIN')) {
    die();
}

require_once __DIR__ . '/AdminFailurePresenter.class.php';

final class AdminOptions {
    private const LOG_FILES = ['lastlog', 'error', 'access'];
    private const ACTION_HANDLERS = [
        'overview' => 'overview',
        'siteSettings' => 'siteSettings',
        'saveSiteSettings' => 'saveSiteSettings',
        'users' => 'users',
        'updateUser' => 'updateUser',
        'grantUserBadge' => 'grantUserBadge',
        'revokeUserBadge' => 'revokeUserBadge',
        'servers' => 'servers',
        'saveServer' => 'saveServer',
        'deleteServer' => 'deleteServer',
        'hardware' => 'hardware',
        'maintenance' => 'maintenance',
        'saveMaintenance' => 'saveMaintenance',
        'log' => 'showLog',
        'clearLog' => 'clearLog',
        'slides' => 'slides',
        'saveSlides' => 'saveSlides',
        'uploadSlideImage' => 'uploadSlideImage',
        'uploadServerImage' => 'uploadServerImage',
        'uploadSiteSocialImage' => 'uploadSiteSocialImage',
        'content' => 'content',
        'saveProjectPages' => 'saveProjectPages',
        'saveBadgePage' => 'saveBadgePage',
        'deleteBadgePage' => 'deleteBadgePage',
        'rewards' => 'rewards',
        'saveReward' => 'saveReward',
        'deleteReward' => 'deleteReward',
        'issueRewardClaimKey' => 'issueRewardClaimKey',
        'revokeRewardClaimKey' => 'revokeRewardClaimKey',
        'fileList' => 'fileList',
        'fileCreateDirectory' => 'fileCreateDirectory',
        'fileUpload' => 'fileUpload',
        'fileRename' => 'fileRename',
        'fileDelete' => 'fileDelete',
        'catalog' => 'catalog',
        'saveCatalogEntry' => 'saveCatalogEntry',
        'deleteCatalogEntry' => 'deleteCatalogEntry',
    ];
    private const SERVER_FIELDS = [
        'serverName', 'host', 'port', 'ignoreDirs', 'enabled', 'checkLib',
        'serverGroups', 'serverDescription', 'serverVersion', 'jreVersion',
        'serverImage', 'modsInfo'
    ];
    private const USER_FIELDS = [
        'login', 'realname', 'email', 'userStatus', 'groupTag', 'balance', 'serversOnline'
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

    private db $db;
    private array $request;
    private UserSession $session;
    private Logger $logger;
    private MaintenanceModeRepository $maintenanceRepository;
    private SiteSettingsRepository $siteSettingsRepository;
    private array $config;
    private GroupRepository $groupRepository;
    private UploadService $uploads;
    private AdminFileManager $fileManager;
    private ThemeSlidesRepository $slidesRepository;
    private ThemeContentRepository $contentRepository;
    private ThemeBadgePageRepository $badgePageRepository;
    private RuntimeJdkCatalog $runtimeJdkCatalog;
    private GameVersionCatalog $gameVersionCatalog;
    private LogQueryService $logQuery;
    private RewardClaimService $rewardClaims;

    public function __construct(
        array $request,
        db $db,
        UserSession $session,
        Logger $logger,
        ?HttpRequest $httpRequest = null,
        array $config = [],
    ) {
        if (!$session->isAdmin()) {
            $this->respond(['message' => 'Недостаточно прав.', 'type' => 'error'], 403);
        }

        $this->db = $db;
        $this->session = $session;
        $this->logger = $logger;
        $this->request = $request;
        $this->config = $config;
        if (!$httpRequest instanceof HttpRequest) {
            throw new RuntimeException('Admin uploads require the original HTTP request.');
        }
        $this->uploads = new UploadService($db, $session, $this->logger, $httpRequest);
        $this->fileManager = new AdminFileManager($this->uploads, $session, $this->logger);
        $this->logQuery = new LogQueryService(self::LOG_FILES);
        $this->rewardClaims = new RewardClaimService($db, $logger);
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
        $this->runtimeJdkCatalog = new RuntimeJdkCatalog($this->bootstrapStorageDirectory());
        $this->gameVersionCatalog = new GameVersionCatalog($this->gameVersionsDirectory());
        $this->maintenanceRepository = new MaintenanceModeRepository($db);
        $this->siteSettingsRepository = new SiteSettingsRepository($db);
        $this->groupRepository = new GroupRepository($db);
        $action = (string)($this->request['admPanel'] ?? '');

        try {
            $handler = self::ACTION_HANDLERS[$action] ?? null;
            RequestTelemetry::identify('admin.' . $action, [
                'component' => 'admin_panel',
                'action' => $action,
                'handler' => is_string($handler) ? $handler : 'unresolved',
                'moduleName' => 'AdminPanel',
            ]);
            if (!is_string($handler) || !method_exists($this, $handler)) {
                throw new HttpException('Неизвестная административная операция.', 400);
            }
            $this->{$handler}();
        } catch (HttpException $error) {
            $requestId = RequestTelemetry::requestId();
            if ($requestId === '') {
                $requestId = ExceptionContext::requestId('admin-rejected');
            }
            $this->respond(
                AdminFailurePresenter::payload($error, $action, $requestId),
                $error->status(),
            );
        } catch (Throwable $error) {
            RequestTelemetry::failure(
                'admin.operation.failed',
                $error,
                'Administrative operation failed unexpectedly.',
                ['action' => $action],
            );
            $requestId = RequestTelemetry::requestId();
            if ($requestId === '') {
                $requestId = ExceptionContext::requestId('admin');
            }
            $this->respond(
                AdminFailurePresenter::payload($error, $action, $requestId),
                AdminFailurePresenter::status($error),
            );
        }
    }

    private function siteSettings(): void {
        $fallback = is_array($this->config['siteSettings'] ?? null)
            ? $this->config['siteSettings']
            : [];
        $this->respond($this->siteSettingsRepository->current($fallback));
    }

    private function saveSiteSettings(): void {
        $entry = $this->decodeObject('entry');
        $fallback = is_array($this->config['siteSettings'] ?? null)
            ? $this->config['siteSettings']
            : [];
        $state = $this->siteSettingsRepository->save(
            $entry,
            $fallback,
            $this->session->uuid(),
        );
        $this->logger->event(
            'admin.site_settings.updated',
            'Site settings updated.',
            [
                'component' => 'site_settings',
                'operation' => 'save',
                'fields' => array_keys($entry),
            ],
            'INFO',
            'success',
        );
        $this->respond(array_merge($state, [
            'message' => 'Настройки сайта и SEO сохранены. Публичные метатеги обновятся при следующей загрузке страницы.',
            'type' => 'success',
        ]));
    }

    private function overview(): void {
        $users = (int)$this->scalar('SELECT COUNT(*) FROM `users`');
        $recent = (int)$this->scalar('SELECT COUNT(*) FROM `users` WHERE `last_date` >= :threshold', [':threshold' => time() - 86400]);
        $servers = (int)$this->scalar('SELECT COUNT(*) FROM `servers`');
        $enabledServers = (int)$this->scalar("SELECT COUNT(*) FROM `servers` WHERE LOWER(CAST(`enabled` AS CHAR)) IN ('true', '1')");
        $hardware = (int)$this->scalar('SELECT COUNT(*) FROM `system_hardware_inventory`');

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
        $badgeExpression = '`user`.`badges`';
        $where = '';
        if ($search !== '') {
            $searchSql = $this->db->safesql('%' . $search . '%');
            $where = " WHERE CONCAT_WS(' ', "
                . "COALESCE(`user`.`login`, ''), "
                . "COALESCE(`user`.`email`, ''), "
                . "COALESCE(`user`.`realname`, ''), "
                . "COALESCE(`user`.`uuid`, ''), "
                . "COALESCE(CAST(`user`.`user_id` AS CHAR), ''), "
                . "COALESCE(`user`.`groupTag`, ''), "
                . "COALESCE(`group`.`groupName`, ''), "
                . 'COALESCE(' . $badgeExpression . ", '')"
                . ') LIKE ' . $searchSql;
        }

        $sql = 'SELECT `user`.`uuid`, `user`.`user_id`, `user`.`login`, `user`.`email`, '
            . '`user`.`realname`, `user`.`groupTag`, `user`.`last_date`, `user`.`reg_date`, '
            . '`user`.`profilePhoto`, `user`.`userStatus`, `user`.`balance`, '
            . $badgeExpression . ' AS `badges`, `user`.`serversOnline`, '
            . '`group`.`groupName`, `group`.`groupColor` '
            . 'FROM `users` AS `user` '
            . 'LEFT JOIN `groupAssociation` AS `group` ON `group`.`groupTag` = `user`.`groupTag` '
            . $where . ' ORDER BY `user`.`last_date` DESC LIMIT ' . $limit . ' OFFSET ' . $offset;

        try {
            $statement = $this->db->query($sql);
            if (!$statement instanceof PDOStatement) {
                throw new RuntimeException('Database query returned no statement.');
            }
            $rows = $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $error) {
            throw new RuntimeException('users.directory query failed: ' . $error->getMessage(), 0, $error);
        }

        foreach ($rows as &$row) {
            if (!is_array($row)) continue;
            $row['balance'] = BalanceMatrix::normalize($row['balance'] ?? null);
            $row['badges'] = $this->decodeAdminJsonField($row['badges'] ?? null);
            $row['serversOnline'] = $this->decodeAdminJsonField($row['serversOnline'] ?? null);
        }
        unset($row);

        try {
            $groups = $this->adminUserGroups();
        } catch (Throwable $error) {
            throw new RuntimeException('users.groups query failed: ' . $error->getMessage(), 0, $error);
        }
        try {
            $badgeOptions = $this->badgeOptions();
        } catch (Throwable $error) {
            throw new RuntimeException('users.badges query failed: ' . $error->getMessage(), 0, $error);
        }

        $this->respond([
            'items' => $rows,
            'groups' => $groups,
            'badgeOptions' => $badgeOptions,
            'limit' => $limit,
            'offset' => $offset,
            'backendVersion' => 'users-directory-v4-direct-query',
        ]);
    }

    private function updateUser(): void {
        $userUuid = (string)($this->request['userUuid'] ?? '');
        if (!Uuid::isValid($userUuid)) {
            $this->respond(['message' => 'Некорректный UUID пользователя.', 'type' => 'error'], 400);
        }
        $userUuid = $this->resolveStoredUserUuid($userUuid);

        $payload = $this->decodeObject('entry');
        if (array_key_exists('badges', $payload)) {
            throw new HttpException(
                'Поле badges нельзя изменять вместе с общими данными пользователя. Используйте отдельные административные действия выдачи и отзыва бейджей.',
                409,
            );
        }
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
            if ($field === 'balance') {
                try {
                    $value = BalanceMatrix::encode($value);
                } catch (InvalidArgumentException $error) {
                    $this->respond([
                        'message' => 'Матрица баланса должна содержать целые неотрицательные значения Units и Crystals.',
                        'type' => 'error',
                    ], 400);
                }
            } elseif ($field === 'serversOnline') {
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

    private function grantUserBadge(): void
    {
        $this->mutateUserBadge(true);
    }

    private function revokeUserBadge(): void
    {
        $this->mutateUserBadge(false);
    }

    private function mutateUserBadge(bool $grant): void
    {
        $requestedUuid = trim((string)($this->request['userUuid'] ?? ''));
        if (!Uuid::isValid($requestedUuid)) {
            throw new HttpException('Некорректный UUID пользователя.', 400);
        }
        $userUuid = $this->resolveStoredUserUuid($requestedUuid);
        $reason = preg_replace('/\s+/u', ' ', trim((string)($this->request['reason'] ?? '')));
        $reason = is_string($reason) ? $reason : '';
        $reasonLength = function_exists('mb_strlen') ? mb_strlen($reason, 'UTF-8') : strlen($reason);
        if ($reasonLength < 3 || $reasonLength > 500) {
            throw new HttpException('Укажите причину административной выдачи или отзыва: от 3 до 500 символов.', 400);
        }

        $badgeId = max(0, (int)($this->request['badgeId'] ?? 0));
        $badgeName = trim((string)($this->request['badgeName'] ?? ''));
        $badge = null;
        if ($badgeId > 0) {
            $statement = $this->db->prepare(
                'SELECT `id`, `badgeName`, `description`, `img` FROM `badgesList` WHERE `id` = :id LIMIT 1'
            );
            $statement->execute([':id' => $badgeId]);
            $row = $statement->fetch(PDO::FETCH_ASSOC);
            if (is_array($row)) {
                $badge = [
                    'id' => (int)($row['id'] ?? 0),
                    'badgeName' => trim((string)($row['badgeName'] ?? '')),
                    'title' => trim((string)($row['badgeName'] ?? '')),
                    'description' => trim((string)($row['description'] ?? '')),
                    'image' => trim((string)($row['img'] ?? '')) ?: null,
                ];
                $badgeName = (string)$badge['badgeName'];
            }
        }
        if (!$grant && $badge === null && $badgeName !== '') {
            $statement = $this->db->prepare(
                'SELECT `id`, `badgeName`, `description`, `img` FROM `badgesList` WHERE `badgeName` = :badgeName LIMIT 1'
            );
            $statement->execute([':badgeName' => $badgeName]);
            $row = $statement->fetch(PDO::FETCH_ASSOC);
            if (is_array($row)) {
                $badge = [
                    'id' => (int)($row['id'] ?? 0),
                    'badgeName' => trim((string)($row['badgeName'] ?? '')),
                    'title' => trim((string)($row['badgeName'] ?? '')),
                    'description' => trim((string)($row['description'] ?? '')),
                    'image' => trim((string)($row['img'] ?? '')) ?: null,
                ];
                $badgeName = (string)$badge['badgeName'];
            }
        }
        if ($grant && $badge === null) {
            throw new HttpException('Выбранный бейдж отсутствует в каталоге.', 404);
        }
        if ($badgeName === '') {
            throw new HttpException('Бейдж для административной операции не указан.', 400);
        }
        if ((function_exists('mb_strlen') ? mb_strlen($badgeName, 'UTF-8') : strlen($badgeName)) > 160) {
            throw new HttpException('Название бейджа превышает допустимую длину.', 400);
        }

        $actorUuid = $this->session->uuid();
        $result = $this->db->transactional(function () use ($grant, $userUuid, $badgeName, $badge): array {
            $statement = $this->db->prepare(
                'SELECT `uuid`, `login`, `badges` FROM `users` WHERE `uuid` = :uuid LIMIT 1 FOR UPDATE'
            );
            $statement->execute([':uuid' => $userUuid]);
            $user = $statement->fetch(PDO::FETCH_ASSOC);
            if (!is_array($user)) {
                throw new HttpException('Пользователь не найден.', 404);
            }

            $assignments = $this->decodeBadgeAssignmentsForMutation($user['badges'] ?? null);
            $needle = $this->normalizeBadgeAssignmentKey($badgeName);
            $exists = false;
            foreach ($assignments as $assignment) {
                if ($this->normalizeBadgeAssignmentKey($this->badgeAssignmentName($assignment)) === $needle) {
                    $exists = true;
                    break;
                }
            }

            $changed = false;
            if ($grant && !$exists) {
                $assignments[] = [
                    'badgeName' => $badgeName,
                    'acquiredAt' => time(),
                    'source' => 'admin',
                ];
                $changed = true;
            } elseif (!$grant && $exists) {
                $assignments = array_values(array_filter(
                    $assignments,
                    fn (mixed $assignment): bool => $this->normalizeBadgeAssignmentKey(
                        $this->badgeAssignmentName($assignment)
                    ) !== $needle,
                ));
                $changed = true;
            }

            $badgesJson = json_encode(
                array_values($assignments),
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
            );
            if ($changed) {
                $update = $this->db->prepare('UPDATE `users` SET `badges` = :badges WHERE `uuid` = :uuid');
                $update->execute([':badges' => $badgesJson, ':uuid' => $userUuid]);
            }

            return [
                'changed' => $changed,
                'badges' => array_values($assignments),
                'login' => trim((string)($user['login'] ?? '')),
            ];
        });

        $operation = $grant ? 'grant' : 'revoke';
        $this->logger->event(
            'admin.user_badge.' . $operation,
            $grant ? 'Administrator granted a profile badge.' : 'Administrator revoked a profile badge.',
            [
                'component' => 'admin_users',
                'operation' => $operation,
                'actorUuid' => $actorUuid,
                'targetUserUuid' => $userUuid,
                'targetLogin' => (string)$result['login'],
                'badgeId' => $badge !== null ? (int)$badge['id'] : null,
                'badgeName' => $badgeName,
                'reason' => $reason,
                'changed' => (bool)$result['changed'],
                'rewardClaimChanged' => false,
                'balanceChanged' => false,
            ],
            (bool)$result['changed'] ? 'INFO' : 'NOTICE',
            (bool)$result['changed'] ? 'success' : 'noop',
        );

        $message = $grant
            ? ((bool)$result['changed'] ? 'Бейдж выдан пользователю.' : 'У пользователя уже есть этот бейдж.')
            : ((bool)$result['changed'] ? 'Бейдж отозван у пользователя.' : 'У пользователя уже нет этого бейджа.');
        $this->respond([
            'message' => $message,
            'type' => (bool)$result['changed'] ? 'success' : 'warning',
            'changed' => (bool)$result['changed'],
            'badges' => $result['badges'],
            'badge' => $badge ?? ['id' => 0, 'badgeName' => $badgeName, 'title' => $badgeName, 'description' => '', 'image' => null],
        ]);
    }

    /** @return list<mixed> */
    private function decodeBadgeAssignmentsForMutation(mixed $value): array
    {
        $decoded = $value;
        if (!is_array($decoded)) {
            $raw = trim((string)$value);
            if ($raw === '') {
                return [];
            }
            $decoded = json_decode($raw, true);
            if (!is_array($decoded)) {
                return [['badgeName' => $raw]];
            }
        }
        if ($decoded === []) {
            return [];
        }
        if (array_is_list($decoded)) {
            return array_values($decoded);
        }
        if (array_key_exists('badgeName', $decoded) || array_key_exists('id', $decoded)
            || array_key_exists('name', $decoded) || array_key_exists('title', $decoded)) {
            return [$decoded];
        }
        $assignments = [];
        foreach ($decoded as $name => $acquiredAt) {
            $badgeName = trim((string)$name);
            if ($badgeName !== '') {
                $assignments[] = ['badgeName' => $badgeName, 'acquiredAt' => $acquiredAt];
            }
        }
        return $assignments;
    }

    private function badgeAssignmentName(mixed $assignment): string
    {
        if (is_string($assignment) || is_numeric($assignment)) {
            return trim((string)$assignment);
        }
        if (!is_array($assignment)) {
            return '';
        }
        $candidate = $assignment['badgeName'] ?? $assignment['id'] ?? $assignment['name'] ?? $assignment['title'] ?? '';
        return is_string($candidate) || is_numeric($candidate) ? trim((string)$candidate) : '';
    }

    private function normalizeBadgeAssignmentKey(string $badgeName): string
    {
        $badgeName = trim($badgeName);
        return function_exists('mb_strtolower')
            ? mb_strtolower($badgeName, 'UTF-8')
            : strtolower($badgeName);
    }


    private function servers(): void {
        $stmt = $this->db->prepare('SELECT `id`, ' . $this->quotedFields(self::SERVER_FIELDS) . ' FROM `servers` ORDER BY `serverName`');
        $stmt->execute();
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($items as &$server) {
            $server['serverGroups'] = $this->normalizeGroupList($server['serverGroups'] ?? []);
        }
        unset($server);

        try {
            $catalog = $this->runtimeJdkCatalog->scan();
            $jdkOptions = $catalog['options'];
        } catch (Throwable $error) {
            $catalog = [
                'available' => false,
                'root' => $this->runtimeJdkCatalog->runtimePath(),
                'requiredSystems' => ['windows', 'linux', 'macos'],
                'scannedArchives' => 0,
                'matchedArchives' => 0,
                'ignoredArchives' => 0,
                'ignoredCandidates' => [],
                'requiredPlatforms' => ['windows-x86_64', 'linux-x86_64', 'macos-x86_64'],
                'supportedPlatforms' => [],
                'mode' => 'exact-runtime-profiles',
                'versionSource' => 'archive-release-metadata-or-file-name',
                'systemSource' => 'catalog-branch-and-release-metadata',
                'error' => $error->getMessage(),
            ];
            $jdkOptions = [];
            $this->logger->exception(
                'admin.runtime_jdk.scan_failed',
                $error,
                'Admin JDK catalog scan failed.',
                [
                    'component' => 'runtime_jdk',
                    'operation' => 'scan',
                    'root' => $this->runtimeJdkCatalog->runtimePath(),
                ],
            );
        }

        try {
            $gameVersionCatalog = $this->gameVersionCatalog->scan();
            $gameVersionOptions = $gameVersionCatalog['options'];
        } catch (Throwable $error) {
            $gameVersionCatalog = [
                'available' => false,
                'root' => $this->gameVersionCatalog->versionsPath(),
                'directories' => 0,
                'ignoredEntries' => 0,
                'error' => $error->getMessage(),
            ];
            $gameVersionOptions = [];
            $this->logger->exception(
                'admin.game_versions.scan_failed',
                $error,
                'Admin game version catalog scan failed.',
                [
                    'component' => 'game_versions',
                    'operation' => 'scan',
                    'root' => $this->gameVersionCatalog->versionsPath(),
                ],
            );
        }

        $this->respond([
            'items' => $items,
            'groups' => $this->groupRepository->all(),
            'jdkOptions' => $jdkOptions,
            'jdkCatalog' => $catalog,
            'gameVersionOptions' => $gameVersionOptions,
            'gameVersionCatalog' => $gameVersionCatalog,
        ]);
    }

    private function saveServer(): void {
        $payload = $this->decodeObject('entry');
        $originalName = trim((string)($this->request['originalName'] ?? ''));
        $serverId = max(0, (int)($payload['id'] ?? 0));
        $serverName = trim((string)($payload['serverName'] ?? ''));
        $enabled = filter_var($payload['enabled'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $runtimeWarning = '';
        if (!preg_match('/^[\p{L}\p{N}_ -]{1,64}$/u', $serverName)) {
            $this->respond(['message' => 'Некорректное имя сервера.', 'type' => 'error'], 400);
        }

        $this->ensureServerStructuredStorage();
        $booleanStorage = $this->serverBooleanStorageModes();
        $data = [];
        foreach (self::SERVER_FIELDS as $field) {
            if (!array_key_exists($field, $payload)) continue;
            $value = $payload[$field];
            if (in_array($field, ['enabled', 'checkLib'], true)) {
                $boolean = filter_var($value, FILTER_VALIDATE_BOOLEAN);
                $value = ($booleanStorage[$field] ?? 'string') === 'numeric'
                    ? ($boolean ? 1 : 0)
                    : ($boolean ? 'true' : 'false');
            }
            if ($field === 'port') {
                $value = filter_var($value, FILTER_VALIDATE_INT);
                if ($value === false || $value < 1 || $value > 65535) {
                    $this->respond(['message' => 'Некорректный порт.', 'type' => 'error'], 400);
                }
            }
            if ($field === 'host') {
                $value = trim((string)$value);
                if ($value === '' || strlen($value) > 255
                    || preg_match('/[\x00-\x20\x7F]/', $value) === 1) {
                    $this->respond(['message' => 'Некорректный адрес сервера.', 'type' => 'error'], 400);
                }
            }
            if ($field === 'serverVersion') {
                $value = trim((string)$value);
                if ($value !== '' && (strlen($value) > 128
                    || $value === '.' || $value === '..'
                    || preg_match('/[\\\/\x00-\x1F\x7F]/', $value) === 1
                )) {
                    $this->respond([
                        'message' => 'Некорректное имя версии клиента.',
                        'type' => 'error',
                    ], 400);
                }
            }
            if ($field === 'jreVersion') {
                $value = trim((string)$value);
                if ($value !== '') {
                    $major = RuntimeJdkCatalog::normalizeMajorSelector($value);
                    if ($major === null) {
                        $this->respond([
                            'message' => 'Java runtime должен содержать корректную major-версию JDK, например 8, 17, 21 или 25.',
                            'type' => 'error',
                        ], 400);
                    }
                    // Never persist the exact cross-platform profile: servers.jreVersion is the
                    // compact launcher contract and must remain compatible with legacy schemas.
                    $value = $major;
                    try {
                        $normalizedVersion = $this->runtimeJdkCatalog->normalizeVersion($value);
                        if ($normalizedVersion !== null) {
                            $value = $normalizedVersion;
                            $runtimeProfile = $this->runtimeJdkCatalog->profile($value);
                            if (is_array($runtimeProfile) && !($runtimeProfile['complete'] ?? false)) {
                                $missingPlatforms = array_values(array_map(
                                    'strval',
                                    (array)($runtimeProfile['missingPlatforms'] ?? []),
                                ));
                                $runtimeWarning = 'JDK ' . $value
                                    . ' сохранён, но отсутствуют runtime-архивы для платформ: '
                                    . ($missingPlatforms !== [] ? implode(', ', $missingPlatforms) : 'неизвестные платформы')
                                    . '. Лаунчер сможет запускать клиент только на платформах с загруженным архивом.';
                            }
                        } else {
                            $runtimeWarning = 'JDK ' . $value
                                . ' сохранён, но архивы этого семейства не найдены для Windows, Linux и macOS. '
                                . 'Запуск сервера может быть недоступен до загрузки runtime.';
                        }
                    } catch (Throwable $error) {
                        $runtimeWarning = 'JDK ' . $value
                            . ' сохранён без проверки каталога runtime: ' . $error->getMessage();
                    }
                }
            }
            if ($field === 'serverImage') {
                $value = $this->normalizeServerImageReference((string)$value);
            }
            if ($field === 'serverGroups') {
                $value = json_encode(
                    $this->normalizeGroupList($value),
                    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
                );
            } elseif ($field === 'ignoreDirs') {
                $value = json_encode(
                    $this->normalizeServerIgnoreDirectories($value),
                    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
                );
            } elseif ($field === 'modsInfo') {
                $value = json_encode(
                    $this->normalizeServerMods($value),
                    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
                );
            }
            $data[$field] = is_string($value) ? trim($value) : $value;
        }
        $data['serverName'] = $serverName;
        $requiredJavaMajor = $this->requiredJavaMajorForServerVersion((string)($data['serverVersion'] ?? ''));
        if ($requiredJavaMajor !== null) {
            $configuredJavaMajor = trim((string)($data['jreVersion'] ?? ''));
            $data['jreVersion'] = $requiredJavaMajor;
            if ($configuredJavaMajor !== $requiredJavaMajor) {
                $compatibilityWarning = 'Для Minecraft ' . (string)($data['serverVersion'] ?? '')
                    . ' принудительно выбран JDK ' . $requiredJavaMajor
                    . ': legacy Forge LaunchWrapper несовместим с Java 9 и новее.';
                $runtimeWarning = $runtimeWarning !== ''
                    ? $runtimeWarning . ' ' . $compatibilityWarning
                    : $compatibilityWarning;
            }
            try {
                if ($this->runtimeJdkCatalog->normalizeVersion($requiredJavaMajor) === null) {
                    $missingRuntimeWarning = 'Архив JDK ' . $requiredJavaMajor
                        . ' отсутствует в runtime-каталоге; загрузите его до запуска клиента.';
                    $runtimeWarning = $runtimeWarning !== ''
                        ? $runtimeWarning . ' ' . $missingRuntimeWarning
                        : $missingRuntimeWarning;
                }
            } catch (Throwable $error) {
                $catalogWarning = 'Не удалось проверить наличие обязательного JDK '
                    . $requiredJavaMajor . ': ' . $error->getMessage();
                $runtimeWarning = $runtimeWarning !== ''
                    ? $runtimeWarning . ' ' . $catalogWarning
                    : $catalogWarning;
            }
        }
        if ($enabled && (!isset($data['jreVersion']) || trim((string)$data['jreVersion']) === '')) {
            $this->respond([
                'message' => 'Для включённого сервера Java runtime обязателен. Выберите major-версию JDK.',
                'type' => 'error',
            ], 400);
        }
        if (!$enabled && (!isset($data['jreVersion']) || trim((string)$data['jreVersion']) === '')) {
            $runtimeWarning = 'Отключённый сервер сохранён без Java runtime. Назначьте JDK перед включением.';
        }

        try {
            if ($originalName !== '' || $serverId > 0) {
                $parts = [];
                $params = [];
                foreach ($data as $field => $value) {
                    $placeholder = ':field_' . $field;
                    $parts[] = '`' . $field . '` = ' . $placeholder;
                    $params[$placeholder] = $value;
                }
                if ($serverId > 0) {
                    $params[':serverId'] = $serverId;
                    $where = '`id` = :serverId';
                } else {
                    $params[':originalName'] = $originalName;
                    $where = '`serverName` = :originalName';
                }
                $this->db->run('UPDATE `servers` SET ' . implode(', ', $parts) . ' WHERE ' . $where, $params);
            } else {
                $fields = array_keys($data);
                $placeholders = array_map(fn($field) => ':' . $field, $fields);
                $params = [];
                foreach ($data as $field => $value) $params[':' . $field] = $value;
                $this->db->run(
                    'INSERT INTO `servers` (' . $this->quotedFields($fields) . ') VALUES (' . implode(', ', $placeholders) . ')',
                    $params,
                );
            }
        } catch (DatabaseException $error) {
            $this->logger->exception(
                'admin.server.save_failed',
                $error,
                'Server configuration could not be persisted.',
                [
                    'component' => 'admin_servers',
                    'operation' => 'save',
                    'serverId' => $serverId,
                    'serverName' => $serverName,
                    'originalName' => $originalName,
                    'fields' => array_keys($data),
                ],
            );
            $message = $error->getMessage();
            if (str_contains($message, '23000') || str_contains($message, '1062')) {
                $this->respond(['message' => 'Сервер с таким именем уже существует.', 'type' => 'error'], 409);
            }
            if (str_contains($message, '22001') || str_contains($message, '1406')) {
                $field = '';
                if (preg_match("/Data too long for column ['`]([^'`]+)['`]/i", $message, $columnMatch) === 1) {
                    $field = (string)$columnMatch[1];
                }
                $labels = [
                    'serverName' => 'Имя сервера',
                    'host' => 'Адрес сервера',
                    'serverGroups' => 'Группы доступа',
                    'serverDescription' => 'Описание сервера',
                    'serverVersion' => 'Версия сервера',
                    'jreVersion' => 'Java runtime',
                    'serverImage' => 'Изображение сервера',
                    'ignoreDirs' => 'Игнорируемые каталоги',
                    'modsInfo' => 'Информация о модах',
                ];
                $label = $labels[$field] ?? ($field !== '' ? $field : 'Одно из полей сервера');
                $suffix = $field === 'serverImage'
                    ? ' Примените миграцию 018, расширяющую legacy-колонку serverImage.'
                    : '';
                $this->respond([
                    'message' => $label . ' превышает допустимую длину.' . $suffix,
                    'type' => 'error',
                    'field' => $field !== '' ? $field : null,
                ], 422);
            }
            if (str_contains($message, '22032') || str_contains($message, '3140') || str_contains($message, '3141')) {
                $this->respond(['message' => 'Структурированные данные сервера содержат некорректный JSON.', 'type' => 'error'], 422);
            }
            $this->respond([
                'message' => 'Не удалось сохранить сервер. Проверьте актуальность схемы базы данных и применённые миграции.',
                'type' => 'error',
            ], 409);
        }

        $this->respond([
            'message' => $runtimeWarning !== ''
                ? 'Сервер сохранён. ' . $runtimeWarning
                : 'Сервер сохранён.',
            'type' => $runtimeWarning !== '' ? 'warning' : 'success',
        ]);
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
        $this->respond(HardwareInventoryStatisticsService::fromDatabase($this->db)->statistics());
    }

    private function showLog(): void {
        $this->log(false);
    }

    private function clearLog(): void {
        $this->log(true);
    }

    private function log(bool $clear): void {
        $name = (string)($this->request['file'] ?? 'lastlog');
        if ($clear) {
            $this->logQuery->clear($name);
            $this->logger->event(
                'admin.log.cleared',
                'Administrative log file cleared.',
                [
                    'component' => 'admin_log',
                    'operation' => 'clear',
                    'logFile' => $name,
                ],
                'WARNING',
                'success',
            );
            $this->respond(['message' => 'Log очищен.', 'type' => 'success']);
        }

        $result = $this->logQuery->read(
            $name,
            max(1, min(500, (int)($this->request['lines'] ?? 100))),
            [
                'requestId' => $this->request['requestId'] ?? '',
                'correlationId' => $this->request['correlationId'] ?? '',
                'event' => $this->request['event'] ?? '',
                'component' => $this->request['component'] ?? '',
                'level' => $this->request['level'] ?? '',
                'deviationOnly' => $this->request['deviationOnly'] ?? false,
                'search' => $this->request['search'] ?? '',
            ],
        );
        $malformedCount = (int)($result['summary']['malformedCount'] ?? 0);
        if ($malformedCount > 0) {
            $this->logger->deviation(
                'admin.log.malformed_entries',
                'malformed_log_entries_detected',
                'Malformed or legacy log entries were detected while reading the journal.',
                'notice',
                ['malformedCount' => 0],
                ['malformedCount' => $malformedCount],
                ['component' => 'admin_log', 'logFile' => $name],
            );
        }
        $this->respond($result);
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
        $this->logger->event(
            'theme.slides.saved',
            'Theme slides saved.',
            [
                'component' => 'theme_slides',
                'operation' => 'save',
                'slidesCount' => count($settings['slides'] ?? []),
                'enabledCount' => count(array_filter(
                    $settings['slides'] ?? [],
                    static fn (array $slide): bool => ($slide['enabled'] ?? false) === true,
                )),
            ],
            'INFO',
            'success',
        );
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

    private function uploadSiteSocialImage(): void {
        try {
            $result = $this->uploads->store(
                UploadPurpose::SITE_SOCIAL_IMAGE,
                is_array($this->request['_siteSocialImageUpload'] ?? null)
                    ? $this->request['_siteSocialImageUpload']
                    : null,
            );
        } catch (UploadException $error) {
            $this->respond(['message' => $error->getMessage(), 'type' => 'error'], $error->httpStatus());
        }
        $this->respond([
            'message' => 'Изображение социальной карточки загружено.',
            'type' => 'success',
            'image' => $result->publicPath(),
            'upload' => $result,
        ], 201);
    }

    private function uploadServerImage(): void {
        try {
            $result = $this->uploads->store(
                UploadPurpose::SERVER_IMAGE,
                is_array($this->request['_serverImageUpload'] ?? null)
                    ? $this->request['_serverImageUpload']
                    : null,
            );
        } catch (UploadException $error) {
            $this->respond(['message' => $error->getMessage(), 'type' => 'error'], $error->httpStatus());
        }
        $this->respond([
            'message' => 'Изображение сервера загружено.',
            'type' => 'success',
            'image' => $result->publicPath(),
            'upload' => $result,
        ], 201);
    }

    private function content(): void {
        $this->assertBadgeCatalogSchema();
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
                $this->logger->deviation(
                    'theme.content.badge_html.invalid',
                    'badge_html_invalid',
                    'Invalid badge HTML page was skipped in administrative content.',
                    'warning',
                    ['pageValid' => true],
                    ['pageValid' => false],
                    [
                        'component' => 'theme_content',
                        'badgeName' => (string)($badge['badgeName'] ?? ''),
                        'slug' => $slug,
                        'reason' => $error->getMessage(),
                    ],
                );
            }
        }
        unset($badge);

        $this->respond([
            'projectPages' => $this->contentRepository->readProjectPages(),
            'badgePages' => ['pages' => $badgePages],
            'badges' => array_values($badges),
        ]);
    }

    private function rewards(): void
    {
        $this->assertRewardAdministrationSchema();
        $this->respond([
            'rewards' => $this->rewardClaims->listDefinitions(),
            'claimKeys' => $this->rewardClaims->listKeys(),
            'badges' => $this->badgeOptions(),
        ]);
    }

    private function saveReward(): void
    {
        $this->assertRewardAdministrationSchema();
        $payload = $this->decodeObject('entry');
        $definition = $this->rewardClaims->saveDefinition($payload, $this->session->uuid());
        $this->respond([
            'message' => 'Награда сохранена. Ключи выдачи настраиваются отдельно.',
            'type' => 'success',
            'reward' => $definition,
        ]);
    }

    private function deleteReward(): void
    {
        $this->assertRewardAdministrationSchema();
        $rewardId = max(0, (int)($this->request['rewardId'] ?? 0));
        $this->rewardClaims->deleteDefinition($rewardId, $this->session->uuid());
        $this->respond([
            'message' => 'Неиспользованная награда и её ключи удалены.',
            'type' => 'success',
        ]);
    }

    private function issueRewardClaimKey(): void
    {
        $this->assertRewardAdministrationSchema();
        $rewardId = max(0, (int)($this->request['rewardId'] ?? 0));
        $usageMode = strtolower(trim((string)($this->request['usageMode'] ?? 'single')));
        $accessMode = strtolower(trim((string)($this->request['accessMode'] ?? 'code')));
        $publicPlacement = trim((string)($this->request['publicPlacement'] ?? ''));
        $result = $this->rewardClaims->issue(
            $rewardId,
            $usageMode,
            $this->session->uuid(),
            $accessMode,
            $publicPlacement,
        );
        $public = ($result['entry']['accessMode'] ?? '') === 'public';
        $this->respond([
            'message' => $public
                ? 'Скрытый криптографический placement-ключ создан. Открытое значение уничтожено.'
                : 'Криптографический код создан. Сохраните его сейчас: повторно он показан не будет.',
            'type' => 'success',
            'token' => $result['token'],
            'entry' => $result['entry'],
        ], 201);
    }

    private function revokeRewardClaimKey(): void
    {
        $this->assertRewardAdministrationSchema();
        $keyId = max(0, (int)($this->request['keyId'] ?? 0));
        $entry = $this->rewardClaims->revoke($keyId, $this->session->uuid());
        $this->respond([
            'message' => 'Ключ награды отозван.',
            'type' => 'success',
            'entry' => $entry,
        ]);
    }

    private function saveProjectPages(): void {
        $payload = $this->decodeObject('entry');
        try {
            $document = $this->contentRepository->saveProjectPages($payload);
        } catch (InvalidArgumentException $error) {
            $this->respond(['message' => $error->getMessage(), 'type' => 'error'], 400);
        }
        $this->logger->event(
            'theme.content.project_pages.saved',
            'Theme project pages saved.',
            [
                'component' => 'theme_content',
                'operation' => 'save_project_pages',
                'pagesCount' => count($document['pages'] ?? []),
            ],
            'INFO',
            'success',
        );
        $this->respond([
            'message' => 'HTML-страницы проекта сохранены в data/pages.',
            'type' => 'success',
            'document' => $document,
        ]);
    }

    private function saveBadgePage(): void {
        $this->assertBadgeCatalogSchema();
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
            $this->logger->deviation(
                'theme.content.badge_html.rejected',
                'badge_html_validation_failed',
                'Badge HTML page validation rejected the document.',
                'notice',
                ['pageValid' => true],
                ['pageValid' => false],
                [
                    'component' => 'theme_content',
                    'badgeName' => (string)$badge['badgeName'],
                    'slug' => $slug,
                    'reason' => $error->getMessage(),
                ],
            );
            $this->respond(['message' => $error->getMessage(), 'type' => 'error'], 400);
        } catch (RuntimeException $error) {
            $this->logger->exception(
                'theme.content.badge_html.storage_failed',
                $error,
                'Badge HTML page storage failed.',
                [
                    'component' => 'theme_content',
                    'badgeName' => (string)$badge['badgeName'],
                    'slug' => $slug,
                ],
            );
            $this->respond(['message' => $error->getMessage(), 'type' => 'error'], 500);
        }
        $this->logger->event(
            'theme.content.badge_html.saved',
            'Individual theme badge HTML page saved.',
            [
                'component' => 'theme_content',
                'operation' => 'save_badge_page',
                'badgeName' => (string)$page['badgeName'],
                'slug' => (string)$page['slug'],
                'file' => 'data/badges/' . (string)$page['slug'] . '.html',
            ],
            'INFO',
            'success',
        );
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
        $this->logger->event(
            'theme.content.badge_html.deleted',
            'Individual theme badge HTML page deleted.',
            [
                'component' => 'theme_content',
                'operation' => 'delete_badge_page',
                'slug' => $slug,
                'file' => 'data/badges/' . $slug . '.html',
            ],
            'INFO',
            'success',
        );
        $this->respond([
            'message' => 'HTML-файл страницы удалён. Запись бейджа в БД сохранена.',
            'type' => 'success',
            'slug' => $slug,
        ]);
    }

    private function fileList(): void {
        $this->respond($this->fileManager->browse((string)($this->request['path'] ?? '')));
    }

    private function fileCreateDirectory(): void {
        $this->respond($this->fileManager->createDirectory(
            (string)($this->request['path'] ?? ''),
            (string)($this->request['name'] ?? ''),
        ));
    }

    private function fileUpload(): void {
        $this->respond($this->fileManager->upload(
            (string)($this->request['path'] ?? ''),
            is_array($this->request['_upload'] ?? null) ? $this->request['_upload'] : null,
        ), 201);
    }

    private function fileRename(): void {
        $this->respond($this->fileManager->rename(
            (string)($this->request['path'] ?? ''),
            (string)($this->request['name'] ?? ''),
        ));
    }

    private function fileDelete(): void {
        $this->respond($this->fileManager->delete((string)($this->request['path'] ?? '')));
    }

    private function catalog(): void {
        [$spec, $catalog] = $this->catalogSpec();
        if ($catalog === 'badges') {
            $this->assertBadgeCatalogSchema();
        }
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
        if ($catalog === 'badges') {
            $this->deleteBadgeCatalogEntry();
        }
        $key = trim((string)($this->request['key'] ?? ''));
        if ($key === '') $this->respond(['message' => 'Ключ не указан.', 'type' => 'error'], 400);
        $stmt = $this->db->prepare('DELETE FROM `' . $spec['table'] . '` WHERE `' . $spec['key'] . '` = :key');
        $stmt->execute([':key' => $key]);
        $this->respond(['message' => 'Запись удалена.', 'type' => 'success']);
    }


    private function deleteBadgeCatalogEntry(): never {
        $this->assertRewardAdministrationSchema();
        $badgeName = trim((string)($this->request['key'] ?? ''));
        if ($badgeName === '') {
            $this->respond(['message' => 'Название бейджа не указано.', 'type' => 'error'], 400);
        }

        $lookup = $this->db->prepare(
            'SELECT `id`, `badgeName` FROM `badgesList` WHERE `badgeName` = :badgeName LIMIT 1'
        );
        $lookup->execute([':badgeName' => $badgeName]);
        $badge = $lookup->fetch(PDO::FETCH_ASSOC);
        if (!is_array($badge)) {
            $this->respond(['message' => 'Бейдж не найден.', 'type' => 'error'], 404);
        }

        $badgeId = (int)($badge['id'] ?? 0);
        $references = $this->db->prepare(
            'SELECT COUNT(*) FROM `rewardDefinitions` WHERE `badgeId` = :badgeId'
        );
        $references->execute([':badgeId' => $badgeId]);
        if ((int)$references->fetchColumn() > 0) {
            $this->respond([
                'message' => 'Бейдж используется в одной или нескольких наградах. Сначала измените конфигурацию этих наград.',
                'type' => 'error',
            ], 409);
        }
        $delete = $this->db->prepare('DELETE FROM `badgesList` WHERE `id` = :badgeId');
        $delete->execute([':badgeId' => $badgeId]);

        $this->logger->event(
            'catalog.badges.deleted',
            'Unreferenced badge catalog entry deleted.',
            [
                'component' => 'badge_catalog',
                'operation' => 'delete',
                'badgeId' => $badgeId,
                'badgeName' => $badgeName,
            ],
            'INFO',
            'success',
        );
        $this->respond([
            'message' => 'Бейдж удалён из каталога.',
            'type' => 'success',
        ]);
    }

    private function saveBadgeCatalogEntry(): never {
        $this->assertBadgeCatalogSchema();
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
                    'UPDATE `badgesList` SET `badgeName` = :badgeName, `description` = :description, `img` = :image WHERE `id` = :id'
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
                    $this->logger->exception(
                        'catalog.badges.rename_rollback_failed',
                        $rollbackError,
                        'Badge HTML page rename rollback failed.',
                        [
                            'component' => 'badge_catalog',
                            'from' => $newSlug,
                            'to' => $oldSlug,
                        ],
                    );
                }
            }
            throw $error;
        }

        $this->logger->event(
            'catalog.badges.saved',
            'Badge catalog entry saved.',
            [
                'component' => 'badge_catalog',
                'operation' => 'save',
                'originalBadgeName' => $originalName,
                'badgeName' => $badgeName,
                'pageSlug' => $newSlug,
                'created' => $originalName === '',
            ],
            'INFO',
            'success',
        );
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

    private function ensureServerStructuredStorage(): void
    {
        $definitions = [
            'serverGroups' => '[]',
            'ignoreDirs' => '[]',
            'modsInfo' => '[]',
        ];
        try {
            $statement = $this->db->prepare(
                "SELECT `COLUMN_NAME`, `DATA_TYPE`, `CHARACTER_MAXIMUM_LENGTH`, `IS_NULLABLE`, `COLUMN_DEFAULT` "
                . "FROM information_schema.COLUMNS "
                . "WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'servers' "
                . "AND COLUMN_NAME IN ('serverGroups', 'ignoreDirs', 'modsInfo')"
            );
            $statement->execute();
            $columns = [];
            foreach ($statement->fetchAll(PDO::FETCH_ASSOC) ?: [] as $column) {
                $name = (string)($column['COLUMN_NAME'] ?? '');
                if (array_key_exists($name, $definitions)) {
                    $columns[$name] = $column;
                }
            }

            $changed = [];
            foreach ($definitions as $name => $fallback) {
                $column = $columns[$name] ?? null;
                $requiresNotNull = false;
                if (!is_array($column)) {
                    $this->db->exec(
                        'ALTER TABLE `servers` ADD COLUMN `' . $name . '` LONGTEXT NULL'
                    );
                    $requiresNotNull = true;
                    $changed[] = $name;
                } else {
                    $type = strtolower((string)($column['DATA_TYPE'] ?? ''));
                    $capacity = (int)($column['CHARACTER_MAXIMUM_LENGTH'] ?? 0);
                    $requiresNotNull = strtoupper((string)($column['IS_NULLABLE'] ?? 'YES')) !== 'NO'
                        || ($column['COLUMN_DEFAULT'] ?? null) === null;
                    if ($type !== 'longtext' || $capacity < 65535) {
                        $this->db->exec(
                            'ALTER TABLE `servers` MODIFY COLUMN `' . $name . '` LONGTEXT NULL'
                        );
                        $requiresNotNull = true;
                        $changed[] = $name;
                    }
                }
                if ($requiresNotNull) {
                    $update = $this->db->prepare(
                        'UPDATE `servers` SET `' . $name . '` = :fallback WHERE `' . $name . '` IS NULL'
                    );
                    $update->execute([':fallback' => $fallback]);
                    $this->db->exec(
                        "ALTER TABLE `servers` MODIFY COLUMN `" . $name . "` LONGTEXT NOT NULL DEFAULT '[]'"
                    );
                }
            }

            if ($changed !== []) {
                $this->logger->event(
                    'admin.server.structured_storage_repaired',
                    'Legacy server structured-data columns were expanded before persistence.',
                    [
                        'component' => 'admin_servers',
                        'operation' => 'repair_schema',
                        'columns' => array_values(array_unique($changed)),
                    ],
                    'WARNING',
                    'success',
                );
            }
        } catch (Throwable $error) {
            $this->logger->exception(
                'admin.server.structured_storage_repair_failed',
                $error,
                'Legacy server structured-data columns could not be expanded.',
                ['component' => 'admin_servers', 'operation' => 'repair_schema'],
            );
            $this->respond([
                'message' => 'Не удалось подготовить хранилище списков сервера. Примените миграцию 022 и повторите сохранение.',
                'type' => 'error',
                'field' => 'serverGroups',
            ], 503);
        }
    }

    /** @return array{enabled:string,checkLib:string} */
    private function serverBooleanStorageModes(): array {
        $modes = ['enabled' => 'string', 'checkLib' => 'string'];
        try {
            $statement = $this->db->prepare(
                "SELECT `COLUMN_NAME`, `DATA_TYPE` FROM information_schema.COLUMNS "
                . "WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'servers' "
                . "AND COLUMN_NAME IN ('enabled', 'checkLib')"
            );
            $statement->execute();
            foreach ($statement->fetchAll(PDO::FETCH_ASSOC) ?: [] as $column) {
                $name = (string)($column['COLUMN_NAME'] ?? '');
                $type = strtolower((string)($column['DATA_TYPE'] ?? ''));
                if (array_key_exists($name, $modes)
                    && in_array($type, ['bit', 'tinyint', 'smallint', 'mediumint', 'int', 'integer', 'bigint', 'decimal'], true)) {
                    $modes[$name] = 'numeric';
                }
            }
        } catch (Throwable $error) {
            $this->logger->exception(
                'admin.server.boolean_storage_detection_failed',
                $error,
                'Server boolean column types could not be inspected; canonical string storage will be used.',
                ['component' => 'admin_servers', 'operation' => 'inspect_schema'],
            );
        }
        return $modes;
    }

    /** @return list<string> */
    private function requiredJavaMajorForServerVersion(string $serverVersion): ?string {
        $normalized = trim($serverVersion);
        if ($normalized === '') {
            return null;
        }
        if (preg_match('/(?:^|[^0-9])1\.(?:7\.10|12\.2)(?:[^0-9]|$)/', $normalized) === 1) {
            return '8';
        }
        return null;
    }

    private function normalizeServerIgnoreDirectories(mixed $value): array {
        if (is_string($value)) {
            $raw = trim($value);
            if ($raw === '') {
                $source = [];
            } else {
                $decoded = json_decode($raw, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    if (!is_array($decoded) || !array_is_list($decoded)) {
                        $this->respond(['message' => 'Игнорируемые каталоги должны быть JSON-массивом или списком через запятую.', 'type' => 'error'], 400);
                    }
                    $source = $decoded;
                } else {
                    $source = preg_split('/[\r\n,]+/', $raw) ?: [];
                }
            }
        } elseif (is_array($value) && array_is_list($value)) {
            $source = $value;
        } else {
            $this->respond(['message' => 'Игнорируемые каталоги должны быть массивом.', 'type' => 'error'], 400);
        }

        $directories = [];
        foreach ($source as $entry) {
            if (!is_string($entry) && !is_numeric($entry)) {
                $this->respond(['message' => 'Каждый игнорируемый каталог должен быть строкой.', 'type' => 'error'], 400);
            }
            $directory = trim(str_replace('\\', '/', (string)$entry));
            if ($directory === '') continue;
            if (mb_strlen($directory, 'UTF-8') > 255
                || preg_match('/[\x00-\x1F\x7F]/u', $directory) === 1) {
                $this->respond(['message' => 'Некорректное имя игнорируемого каталога.', 'type' => 'error'], 400);
            }
            foreach (explode('/', $directory) as $segment) {
                if ($segment === '..') {
                    $this->respond(['message' => 'Сегмент .. запрещён в игнорируемых каталогах.', 'type' => 'error'], 400);
                }
            }
            $directories[] = $directory;
            if (count($directories) > 256) {
                $this->respond(['message' => 'Указано слишком много игнорируемых каталогов.', 'type' => 'error'], 400);
            }
        }
        return array_values(array_unique($directories));
    }

    /** @return list<mixed> */
    private function normalizeServerMods(mixed $value): array {
        if (is_string($value)) {
            $raw = trim($value);
            if ($raw === '') return [];
            try {
                $value = json_decode($raw, true, 64, JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                $this->respond(['message' => 'Информация о модах содержит некорректный JSON.', 'type' => 'error'], 400);
            }
        } elseif (is_object($value)) {
            $value = (array)$value;
        }
        if (!is_array($value) || !array_is_list($value)) {
            $this->respond(['message' => 'Информация о модах должна быть JSON-массивом.', 'type' => 'error'], 400);
        }
        if (count($value) > 2000) {
            $this->respond(['message' => 'Список модов слишком велик.', 'type' => 'error'], 413);
        }
        return array_values($value);
    }

    private function normalizeServerImageReference(string $value): string {
        $value = trim(str_replace('\\', '/', $value));
        if ($value === '') {
            return '';
        }
        if (strlen($value) > 1024 || str_contains($value, "\0")
            || preg_match('/[\x00-\x1F\x7F]/u', $value) === 1) {
            $this->respond(['message' => 'Некорректный путь к изображению сервера.', 'type' => 'error'], 400);
        }
        if (str_starts_with($value, 'uploads/')) {
            $value = '/' . $value;
        }
        $decodedPath = rawurldecode((string)(parse_url($value, PHP_URL_PATH) ?? $value));
        foreach (explode('/', str_replace('\\', '/', $decodedPath)) as $segment) {
            if ($segment === '..') {
                $this->respond(['message' => 'Переходы .. в пути изображения сервера запрещены.', 'type' => 'error'], 400);
            }
        }
        if (str_starts_with($value, '/uploads/')) {
            try {
                return $this->uploads->validateReference(UploadPurpose::SERVER_IMAGE, $value);
            } catch (UploadException $error) {
                $this->respond(['message' => $error->getMessage(), 'type' => 'error'], $error->httpStatus());
            }
        }
        if (str_starts_with($value, '//')) {
            $this->respond(['message' => 'Protocol-relative URL изображения сервера запрещён.', 'type' => 'error'], 400);
        }
        $scheme = parse_url($value, PHP_URL_SCHEME);
        if (is_string($scheme) && $scheme !== '') {
            if (!in_array(strtolower($scheme), ['http', 'https'], true)
                || filter_var($value, FILTER_VALIDATE_URL) === false) {
                $this->respond(['message' => 'Некорректный URL изображения сервера.', 'type' => 'error'], 400);
            }
        }
        return $value;
    }

    private function bootstrapStorageDirectory(): string {
        $configured = trim((string)(foxEnv('FOXESCRAFT_BOOTSTRAP_STORAGE_DIRECTORY') ?? ''));
        if ($configured !== '') {
            return rtrim($configured, '/\\');
        }
        $uploads = trim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, UPLOADS_DIR), DIRECTORY_SEPARATOR);
        return rtrim(ROOT_DIR, '/\\')
            . DIRECTORY_SEPARATOR . $uploads
            . DIRECTORY_SEPARATOR . 'bootstrap';
    }

    private function gameVersionsDirectory(): string {
        $configured = trim((string)(foxEnv('FOXESCRAFT_GAME_VERSIONS_DIRECTORY') ?? ''));
        if ($configured !== '') {
            return rtrim($configured, '/\\');
        }

        $uploads = trim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, UPLOADS_DIR), DIRECTORY_SEPARATOR);
        return rtrim(ROOT_DIR, '/\\')
            . DIRECTORY_SEPARATOR . $uploads
            . DIRECTORY_SEPARATOR . 'game'
            . DIRECTORY_SEPARATOR . 'versions';
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

    /** @return list<array{groupTag:string,groupName:string,groupColor:string}> */
    private function adminUserGroups(): array {
        $statement = $this->db->query(
            'SELECT `groupTag`, `groupName`, `groupColor` FROM `groupAssociation` ORDER BY `groupName`, `groupTag`'
        );
        if (!$statement instanceof PDOStatement) {
            throw new RuntimeException('Database query returned no group statement.');
        }
        $groups = [];
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            if (!is_array($row)) continue;
            $tag = GroupRepository::normalizeTag($row['groupTag'] ?? 'guest');
            $color = strtolower(trim((string)($row['groupColor'] ?? '#ffffff')));
            if (preg_match('/^#[0-9a-f]{6}$/D', $color) !== 1) $color = '#ffffff';
            $groups[] = [
                'groupTag' => $tag,
                'groupName' => trim((string)($row['groupName'] ?? '')) ?: $tag,
                'groupColor' => $color,
            ];
        }
        return $groups;
    }

    private function decodeAdminJsonField(mixed $value): array {
        if (is_array($value)) return $value;
        if (is_object($value)) return (array)$value;
        $raw = trim((string)$value);
        if ($raw === '') return [];
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function assertBadgeCatalogSchema(): void
    {
        $statement = $this->db->prepare(
            "SELECT COUNT(*) FROM information_schema.COLUMNS "
            . "WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'badgesList' "
            . "AND COLUMN_NAME IN ('id', 'badgeName', 'description', 'img')"
        );
        $statement->execute();
        if ((int)$statement->fetchColumn() !== 4) {
            throw new HttpException(
                'Не удалось загрузить бейджи: таблица badgesList отсутствует или повреждена.',
                503,
            );
        }
    }

    private function assertRewardAdministrationSchema(): void
    {
        $required = [
            'badgesList' => ['id', 'badgeName', 'description', 'img'],
            'rewardDefinitions' => [
                'id', 'rewardName', 'description', 'badgeId', 'currencyCode', 'currencyAmount',
                'enabled', 'createdAt', 'updatedAt', 'createdByUuid', 'updatedByUuid',
            ],
            'rewardClaimKeys' => [
                'id', 'rewardId', 'tokenHash', 'tokenHint', 'usageMode', 'accessMode', 'publicPlacement',
                'usesCount', 'enabled', 'createdAt', 'updatedAt', 'createdByUuid',
            ],
            'rewardClaims' => [
                'id', 'rewardId', 'keyId', 'userUuid', 'badgeGranted', 'badgeId', 'badgeName',
                'currencyCode', 'currencyAmount', 'claimedAt',
            ],
        ];
        $placeholders = [];
        $parameters = [];
        foreach (array_keys($required) as $index => $table) {
            $placeholder = ':table_' . $index;
            $placeholders[] = $placeholder;
            $parameters[$placeholder] = $table;
        }
        $statement = $this->db->prepare(
            'SELECT `TABLE_NAME`, `COLUMN_NAME` FROM information_schema.COLUMNS '
            . 'WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` IN (' . implode(', ', $placeholders) . ')'
        );
        $statement->execute($parameters);
        $actual = [];
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) ?: [] as $column) {
            $table = (string)($column['TABLE_NAME'] ?? '');
            $name = (string)($column['COLUMN_NAME'] ?? '');
            if ($table !== '' && $name !== '') {
                $actual[$table][$name] = true;
            }
        }
        $missing = [];
        foreach ($required as $table => $columns) {
            if (!isset($actual[$table])) {
                $missing[] = $table . '.*';
                continue;
            }
            foreach ($columns as $column) {
                if (!isset($actual[$table][$column])) {
                    $missing[] = $table . '.' . $column;
                }
            }
        }
        $indexStatement = $this->db->prepare(
            "SELECT COUNT(*) FROM information_schema.STATISTICS "
            . "WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rewardClaims' "
            . "AND INDEX_NAME = 'uq_reward_claim_reward_user'"
        );
        $indexStatement->execute();
        if ((int)$indexStatement->fetchColumn() < 1) {
            $missing[] = 'rewardClaims.uq_reward_claim_reward_user';
        }
        if ($missing !== []) {
            throw new HttpException(
                'Не удалось загрузить награды: схема базы данных не обновлена. Отсутствуют: '
                . implode(', ', $missing) . '. Выполните `php scripts/migrate.php`; необходима миграция 021.',
                503,
            );
        }
    }

    /** @return list<array{id: int, badgeName: string, title: string, description: string, image: ?string}> */
    private function badgeOptions(): array {
        $this->assertBadgeCatalogSchema();
        $stmt = $this->db->query(
            'SELECT `id`, `badgeName`, `description`, `img` FROM `badgesList` ORDER BY `badgeName`'
        );
        if (!$stmt instanceof PDOStatement) {
            throw new RuntimeException('Database query returned no badge statement.');
        }
        $options = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            if (!is_array($row)) continue;
            $badgeName = trim((string)($row['badgeName'] ?? ''));
            if ($badgeName === '') continue;
            $image = trim((string)($row['img'] ?? ''));
            $options[] = [
                'id' => (int)($row['id'] ?? 0),
                'badgeName' => $badgeName,
                'title' => $badgeName,
                'description' => trim((string)($row['description'] ?? '')),
                'image' => $image !== '' ? $image : null,
            ];
        }
        return $options;
    }

    private function scalar(string $sql, array $params = []) {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchColumn();
    }

    private function quotedFields(array $fields): string {
        return implode(', ', array_map(fn($field) => '`' . $field . '`', $fields));
    }

    private function respond(array $payload, int $status = 200): never {
        if ($status >= 400) {
            RequestTelemetry::rejectHttp(
                'admin.operation.rejected',
                $status,
                (string)($payload['message'] ?? 'Administrative operation was rejected.'),
                ['action' => (string)($this->request['admPanel'] ?? '')],
            );
        }
        JsonResponse::send($payload, $status);
    }
}
