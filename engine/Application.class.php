<?php

declare(strict_types=1);

final class Application
{
    private db $db;
    private Logger $logger;
    private HttpRequest $request;
    private UserSession $session;
    private ModulesLoader $modules;

    public function __construct(
        private array $config,
        private NetworkContext $network,
        private array $levels,
    ) {
        $this->startSession();
        $this->loadCore();
        $this->connectServices();
    }

    public function run(): void
    {
        RequestTelemetry::annotate(['applicationPhase' => 'dispatch']);
        $maintenance = (new MaintenanceModeRepository($this->db))->current();

        // Authentication and remember-token restoration must run before the
        // maintenance group gate so permitted users can establish identity.
        $this->modules->loadPriority(MODULES_DIR, 'preInit', ['AuthReg']);
        $this->enforceMaintenance($maintenance);
        $this->modules->loadPriority(MODULES_DIR, 'preInit', null, ['AuthReg']);

        $systemRequests = new SystemRequests(
            $this->db,
            $this->logger,
            $this->request,
            $this->session,
            $this->config,
        );
        $systemRequests->requestListener();

        if (($this->levels['init'] ?? false) === true) {
            $this->modules->loadPriority(MODULES_DIR, 'primary');
        }
        if (($this->levels['postInit'] ?? false) === true) {
            $this->modules->loadPriority(MODULES_DIR, 'secondary');
        }
        if (($this->levels['GFX'] ?? false) === true) {
            $theme = (new ThemeResolver(TEMPLATE_DIR))->resolve(
                (string)($this->config['siteSettings']['siteTpl'] ?? '')
            );
            $frontend = new FrontendRegistry(
                $this->session,
                (string)$theme['frontend'],
                ENGINE_DIR . 'data/modules.json',
            );
            (new ThemeRenderer(
                $this->config,
                $this->session->all(),
                $theme,
                $frontend->manifest(),
            ))->render();
        }
    }


    private function enforceMaintenance(array $settings): void
    {
        if (MaintenanceModePolicy::allows($settings, $this->session)) {
            return;
        }

        RequestTelemetry::deviation(
            'maintenance.access_blocked',
            'maintenance_mode_active',
            'Request was blocked by maintenance mode.',
            'notice',
            ['maintenanceMode' => false],
            ['maintenanceMode' => true],
            [
                'component' => 'maintenance',
                'actorGroup' => $this->session->group(),
            ],
        );

        if ($this->request->isPost() || $this->request->expectsJson()) {
            JsonResponse::send([
                'type' => 'warning',
                'code' => 'maintenance_mode',
                'message' => (string)($settings['title'] ?? 'Ведутся технические работы.'),
            ], 503, ['Retry-After' => '300']);
        }

        (new MaintenanceRenderer($this->config, $settings, $this->session))->render();
    }

    private function startSession(): void
    {
        if (session_status() !== PHP_SESSION_NONE) {
            return;
        }

        $security = is_array($this->config['securitySetings'] ?? null)
            ? $this->config['securitySetings']
            : [];
        $absoluteLifetime = max(900, (int)($security['sessionAbsoluteSeconds'] ?? 86400));

        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.cookie_httponly', '1');
        ini_set('session.cookie_samesite', 'Lax');
        ini_set('session.gc_maxlifetime', (string)$absoluteLifetime);
        ini_set('session.sid_length', '48');
        ini_set('session.sid_bits_per_character', '6');

        session_name('FOXESCRAFTSESSID');
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => $this->network->isSecure(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_cache_limiter('nocache');
        if (!session_start()) {
            throw new RuntimeException('Unable to start the FoxCMS session.');
        }
    }

    private function loadCore(): void
    {
        foreach ([
            'antiBrute',
            'auth',
            'database.php',
            'date',
            'file',
            'filesInDir',
            'functions',
            'getPerms',
            'randTexts',
            'syslog',
        ] as $library) {
            require_once SYSLIB_DIR . $library;
        }

        foreach ([
            ENGINE_DIR . 'classes/GenericPDO/GenericSelector.class.php',
            ENGINE_DIR . 'classes/GenericPDO/GenericUpdater.class.php',
            ENGINE_DIR . 'classes/http/HttpException.class.php',
            ENGINE_DIR . 'classes/http/JsonResponse.class.php',
            ENGINE_DIR . 'classes/http/HttpRequest.class.php',
            ENGINE_DIR . 'classes/modules/AuthReg/AuthFailure.class.php',
            ENGINE_DIR . 'classes/modules/AuthReg/AuthInputValidator.class.php',
            ENGINE_DIR . 'classes/identity/UserIdentityException.class.php',
            ENGINE_DIR . 'classes/identity/Uuid.class.php',
            ENGINE_DIR . 'classes/domain/BalanceMatrix.class.php',
            ENGINE_DIR . 'classes/session/UserSession.class.php',
            ENGINE_DIR . 'classes/observability/TraceContext.class.php',
            ENGINE_DIR . 'classes/observability/RequestTelemetry.class.php',
            ENGINE_DIR . 'classes/observability/OperationTrace.class.php',
            ENGINE_DIR . 'classes/observability/LogQueryService.class.php',
            ENGINE_DIR . 'classes/repositories/GroupRepository.class.php',
            ENGINE_DIR . 'classes/repositories/MaintenanceModeRepository.class.php',
            ENGINE_DIR . 'classes/repositories/SiteSettingsRepository.class.php',
            ENGINE_DIR . 'classes/files/SafeUploadName.class.php',
            ENGINE_DIR . 'classes/files/PublicFileLocator.class.php',
            ENGINE_DIR . 'classes/files/ArtifactRepository.class.php',
            ENGINE_DIR . 'classes/services/MaintenanceModePolicy.class.php',
            ENGINE_DIR . 'classes/services/HardwareReportService.class.php',
            ENGINE_DIR . 'classes/services/HardwareInventoryStatisticsService.class.php',
            ENGINE_DIR . 'classes/services/UserTextureLocator.class.php',
            ENGINE_DIR . 'classes/services/RewardClaimService.class.php',
            ENGINE_DIR . 'classes/services/RuntimeJdkCatalog.class.php',
            ENGINE_DIR . 'classes/support/ExceptionContext.class.php',
            ENGINE_DIR . 'classes/support/UtilityLoader.class.php',
            ENGINE_DIR . 'classes/security/CsrfToken.class.php',
            ENGINE_DIR . 'classes/security/RememberToken.class.php',
            ENGINE_DIR . 'classes/uploads/UploadException.class.php',
            ENGINE_DIR . 'classes/uploads/UploadPermission.class.php',
            ENGINE_DIR . 'classes/uploads/UploadPurpose.class.php',
            ENGINE_DIR . 'classes/uploads/UploadResult.class.php',
            ENGINE_DIR . 'classes/uploads/UploadPolicy.class.php',
            ENGINE_DIR . 'classes/uploads/UploadPolicyFactory.class.php',
            ENGINE_DIR . 'classes/uploads/InspectedUpload.class.php',
            ENGINE_DIR . 'classes/uploads/UploadFilesystem.class.php',
            ENGINE_DIR . 'classes/uploads/UploadFileInspector.class.php',
            ENGINE_DIR . 'classes/uploads/UploadService.class.php',
            ENGINE_DIR . 'classes/modules/Module.class.php',
            ENGINE_DIR . 'classes/frontend/FrontendRegistry.class.php',
            ENGINE_DIR . 'classes/themes/ThemeResolver.class.php',
            ENGINE_DIR . 'classes/themes/ThemeContentRepository.class.php',
            ENGINE_DIR . 'classes/themes/BadgeSlug.class.php',
            ENGINE_DIR . 'classes/themes/ThemeBadgePageRepository.class.php',
            ENGINE_DIR . 'classes/themes/ThemeSlidesRepository.class.php',
            ENGINE_DIR . 'classes/themes/ThemeRenderer.class.php',
            ENGINE_DIR . 'classes/themes/MaintenanceRenderer.class.php',
            ENGINE_DIR . 'ModulesLoader.class.php',
            ENGINE_DIR . 'SystemRequests.class.php',
        ] as $classFile) {
            require_once $classFile;
        }
    }

    private function connectServices(): void
    {
        $database = is_array($this->config['database'] ?? null) ? $this->config['database'] : [];
        $this->db = new db(
            (string)($database['dbUser'] ?? ''),
            (string)($database['dbPass'] ?? ''),
            (string)($database['dbName'] ?? ''),
            (string)($database['dbHost'] ?? '127.0.0.1'),
            (int)($database['dbPort'] ?? 3306),
            (string)($database['dbCharset'] ?? 'utf8mb4'),
            (int)($database['connectTimeout'] ?? 5),
        );
        $siteDefaults = is_array($this->config['siteSettings'] ?? null)
            ? $this->config['siteSettings']
            : [];
        $siteState = (new SiteSettingsRepository($this->db))->current($siteDefaults);
        $siteOverrides = is_array($siteState['settings'] ?? null) ? $siteState['settings'] : [];
        $this->config['siteSettings'] = array_replace($siteDefaults, $siteOverrides);
        $this->logger = new Logger('lastlog');
        $this->request = HttpRequest::fromGlobals($this->network);
        $this->session = new UserSession($this->db, $this->config, $this->network);
        $observability = is_array($this->config['observability'] ?? null)
            ? $this->config['observability']
            : [];
        RequestTelemetry::bootstrap($this->logger, $this->request, $this->session, $observability);
        RuntimeErrorHandler::adoptRequestId(RequestTelemetry::requestId());
        RequestTelemetry::annotate([
            'environment' => (string)($this->config['environment']['name'] ?? 'production'),
            'serviceVersion' => (string)($this->config['siteSettings']['ServiceVersion'] ?? 'unknown'),
        ]);

        $sessionStartedAt = hrtime(true);
        $wasAuthenticated = $this->session->isLogged();
        $previousActorUuid = $this->session->uuid();
        $previousActorLogin = $this->session->login();
        try {
            $this->session->synchronizeWithDatabase();
            $this->touchCurrentUser();
            $authenticated = $this->session->isLogged();
            $sessionDuration = round(max(0, hrtime(true) - $sessionStartedAt) / 1_000_000, 3);
            $sessionState = !$wasAuthenticated
                ? 'guest_confirmed'
                : ($authenticated ? 'database_refreshed' : 'invalidated');
            RequestTelemetry::annotate([
                'actorUuid' => $this->session->uuid(),
                'actorLogin' => $this->session->login(),
                'actorGroup' => $this->session->group(),
                'authenticated' => $authenticated,
                'sessionState' => $sessionState,
            ]);

            if ($wasAuthenticated && !$authenticated) {
                RequestTelemetry::deviation(
                    'session.synchronize.invalidated',
                    'session_identity_not_found',
                    'Authenticated session was invalidated because its user identity could not be refreshed.',
                    'warning',
                    ['sessionState' => 'database_refreshed'],
                    ['sessionState' => 'invalidated'],
                    [
                        'component' => 'session',
                        'previousActorUuid' => $previousActorUuid,
                        'previousActorLogin' => $previousActorLogin,
                        'durationMs' => $sessionDuration,
                    ],
                );
            } elseif ($authenticated) {
                RequestTelemetry::event(
                    'session.synchronize.completed',
                    sprintf(
                        'Session refreshed from the users table for %s [%s] in %.3f ms.',
                        $this->session->login(),
                        $this->session->group(),
                        $sessionDuration,
                    ),
                    [
                        'component' => 'session',
                        'operation' => 'session.synchronize',
                        'sessionState' => $sessionState,
                        'durationMs' => $sessionDuration,
                        'authenticated' => true,
                    ],
                    'DEBUG',
                    'success',
                );
            } elseif ($sessionDuration > 250) {
                RequestTelemetry::deviation(
                    'session.synchronize.slow_guest',
                    'guest_session_synchronization_slow',
                    'Guest session initialization exceeded the expected duration.',
                    'notice',
                    ['maximumDurationMs' => 250],
                    ['durationMs' => $sessionDuration],
                    ['component' => 'session', 'sessionState' => $sessionState],
                );
            }
        } catch (Throwable $error) {
            RequestTelemetry::failure(
                'session.synchronize.failed',
                $error,
                'Session synchronization failed before request dispatch.',
                [
                    'component' => 'session',
                    'previousActorUuid' => $previousActorUuid,
                    'previousActorLogin' => $previousActorLogin,
                    'durationMs' => round(max(0, hrtime(true) - $sessionStartedAt) / 1_000_000, 3),
                ],
            );
            throw $error;
        }

        $this->modules = new ModulesLoader(
            $this->db,
            $this->logger,
            $this->request,
            $this->session,
            $this->config,
        );
    }

    private function touchCurrentUser(): void
    {
        if (!$this->session->isLogged()) {
            return;
        }

        $placeholders = [];
        $parameters = [':last_date' => CURRENT_TIME];
        foreach (Uuid::databaseCandidates($this->session->uuid()) as $index => $candidate) {
            $placeholder = ':uuid_' . $index;
            $placeholders[] = $placeholder;
            $parameters[$placeholder] = $candidate;
        }
        $statement = $this->db->prepare(
            'UPDATE `users` SET `last_date` = :last_date '
            . 'WHERE `uuid` IN (' . implode(', ', $placeholders) . ')'
        );
        $statement->execute($parameters);
    }
}
