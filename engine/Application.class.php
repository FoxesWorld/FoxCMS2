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

        if ($this->request->isPost() || $this->request->expectsJson()) {
            http_response_code(503);
            header('Content-Type: application/json; charset=UTF-8');
            header('Cache-Control: no-store');
            header('Retry-After: 300');
            exit(json_encode([
                'type' => 'warning',
                'code' => 'maintenance_mode',
                'message' => (string)($settings['title'] ?? 'Ведутся технические работы.'),
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
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
            ENGINE_DIR . 'classes/http/HttpRequest.class.php',
            ENGINE_DIR . 'classes/identity/UserIdentityException.class.php',
            ENGINE_DIR . 'classes/identity/Uuid.class.php',
            ENGINE_DIR . 'classes/session/UserSession.class.php',
            ENGINE_DIR . 'classes/repositories/GroupRepository.class.php',
            ENGINE_DIR . 'classes/repositories/MaintenanceModeRepository.class.php',
            ENGINE_DIR . 'classes/services/MaintenanceModePolicy.class.php',
            ENGINE_DIR . 'classes/support/UtilityLoader.class.php',
            ENGINE_DIR . 'classes/security/CsrfToken.class.php',
            ENGINE_DIR . 'classes/security/RememberToken.class.php',
            ENGINE_DIR . 'classes/modules/Module.class.php',
            ENGINE_DIR . 'classes/frontend/FrontendRegistry.class.php',
            ENGINE_DIR . 'classes/themes/ThemeResolver.class.php',
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
        $this->logger = new Logger('lastlog');
        $this->request = HttpRequest::fromGlobals($this->network);
        $this->session = new UserSession($this->db, $this->config, $this->network);
        $this->session->synchronizeWithDatabase();
        $this->touchCurrentUser();
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
