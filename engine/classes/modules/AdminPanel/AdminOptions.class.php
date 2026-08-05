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

    private db $db;
    private array $request;
    private UserSession $session;
    private Logger $logger;
    private MaintenanceModeRepository $maintenanceRepository;
    private SiteSettingsRepository $siteSettingsRepository;
    private array $config;
    private GroupRepository $groupRepository;
    private AdminGroupListNormalizer $groupNormalizer;
    private AdminServerController $serverController;
    private AdminCatalogController $catalogController;
    private AdminUserController $userController;
    private UploadService $uploads;
    private AdminFileManager $fileManager;
    private ThemeSlidesRepository $slidesRepository;
    private AdminResponder $responder;
    private AdminRequestPayload $payload;
    private AdminBadgeCatalogSchema $badgeCatalogSchema;
    private AdminContentController $contentController;
    private LogQueryService $logQuery;
    private AdminRewardController $rewardController;

    public function __construct(
        array $request,
        db $db,
        UserSession $session,
        Logger $logger,
        ?HttpRequest $httpRequest = null,
        array $config = [],
    ) {
        $action = (string)($request['admPanel'] ?? '');
        $this->request = $request;
        $this->responder = new AdminResponder($action);
        if (!$session->isAdmin()) {
            $this->responder->send(['message' => 'Доступ запрещён.', 'type' => 'error'], 403);
        }

        $this->db = $db;
        $this->session = $session;
        $this->logger = $logger;
        $this->config = $config;
        if (!$httpRequest instanceof HttpRequest) {
            throw new RuntimeException('Admin uploads require the original HTTP request.');
        }
        $this->uploads = new UploadService($db, $session, $this->logger, $httpRequest);
        $this->fileManager = new AdminFileManager($this->uploads, $session, $this->logger);
        $this->logQuery = new LogQueryService(self::LOG_FILES);
        $this->payload = new AdminRequestPayload($request, $this->responder);
        $this->badgeCatalogSchema = new AdminBadgeCatalogSchema($db);
        $badgeOptions = new AdminBadgeOptionsProvider($db, $this->badgeCatalogSchema);
        $this->groupRepository = new GroupRepository($db);
        $this->groupNormalizer = new AdminGroupListNormalizer($this->groupRepository);
        $this->maintenanceRepository = new MaintenanceModeRepository($db);
        $this->serverController = new AdminServerController(
            $db,
            $request,
            $logger,
            $this->uploads,
            $this->payload,
            $this->responder,
            $this->groupNormalizer,
        );
        $this->rewardController = new AdminRewardController(
            $db,
            $request,
            $session,
            $logger,
            $this->payload,
            $this->responder,
            $badgeOptions,
        );
        $this->userController = new AdminUserController(
            $db,
            $request,
            $session,
            $logger,
            $this->groupRepository,
            $this->payload,
            $this->responder,
            $badgeOptions,
        );
        $site = is_array($config['siteSettings'] ?? null) ? $config['siteSettings'] : [];
        $this->slidesRepository = new ThemeSlidesRepository(
            TEMPLATE_DIR,
            (string)($site['siteTpl'] ?? ''),
        );
        $contentRepository = new ThemeContentRepository(
            TEMPLATE_DIR,
            (string)($site['siteTpl'] ?? ''),
        );
        $badgePageRepository = new ThemeBadgePageRepository(
            TEMPLATE_DIR,
            (string)($site['siteTpl'] ?? ''),
        );
        $this->contentController = new AdminContentController(
            $db,
            $request,
            $logger,
            $contentRepository,
            $badgePageRepository,
            $this->badgeCatalogSchema,
            $this->payload,
            $this->responder,
        );
        $this->catalogController = new AdminCatalogController(
            $db,
            $request,
            $logger,
            $this->groupRepository,
            $this->maintenanceRepository,
            $badgePageRepository,
            $this->badgeCatalogSchema,
            $this->payload,
            $this->responder,
            $this->groupNormalizer,
        );
        $this->siteSettingsRepository = new SiteSettingsRepository($db);
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

    private function users(): void
    {
        $this->userController->users();
    }

    private function updateUser(): void
    {
        $this->userController->updateUser();
    }

    private function grantUserBadge(): void
    {
        $this->userController->grantUserBadge();
    }

    private function revokeUserBadge(): void
    {
        $this->userController->revokeUserBadge();
    }

    private function servers(): void
    {
        $this->serverController->servers();
    }

    private function saveServer(): void
    {
        $this->serverController->saveServer();
    }

    private function deleteServer(): void
    {
        $this->serverController->deleteServer();
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

    private function content(): void
    {
        $this->contentController->content();
    }

    private function rewards(): void
    {
        $this->rewardController->rewards();
    }

    private function saveReward(): void
    {
        $this->rewardController->saveReward();
    }

    private function deleteReward(): void
    {
        $this->rewardController->deleteReward();
    }

    private function issueRewardClaimKey(): void
    {
        $this->rewardController->issueRewardClaimKey();
    }

    private function revokeRewardClaimKey(): void
    {
        $this->rewardController->revokeRewardClaimKey();
    }

    private function saveProjectPages(): void
    {
        $this->contentController->saveProjectPages();
    }

    private function saveBadgePage(): void
    {
        $this->contentController->saveBadgePage();
    }

    private function deleteBadgePage(): void
    {
        $this->contentController->deleteBadgePage();
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

    private function catalog(): void
    {
        $this->catalogController->catalog();
    }

    private function saveCatalogEntry(): void
    {
        $this->catalogController->saveCatalogEntry();
    }

    private function deleteCatalogEntry(): void
    {
        $this->catalogController->deleteCatalogEntry();
    }


    private function decodeObject(string $field): array
    {
        return $this->payload->object($field);
    }

    private function scalar(string $sql, array $params = []) {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchColumn();
    }

    private function respond(array $payload, int $status = 200): never
    {
        $this->responder->send($payload, $status);
    }
}
