<?php

declare(strict_types=1);

namespace FoxCMS\Engine\Application;

final class ApplicationContextFactory
{
    public function create(array $config, \NetworkContext $network): ApplicationContext
    {
        $database = is_array($config['database'] ?? null) ? $config['database'] : [];
        $db = new \db(
            (string)($database['dbUser'] ?? ''),
            (string)($database['dbPass'] ?? ''),
            (string)($database['dbName'] ?? ''),
            (string)($database['dbHost'] ?? '127.0.0.1'),
            (int)($database['dbPort'] ?? 3306),
            (string)($database['dbCharset'] ?? 'utf8mb4'),
            (int)($database['connectTimeout'] ?? 5),
        );

        $siteDefaults = is_array($config['siteSettings'] ?? null)
            ? $config['siteSettings']
            : [];
        $siteSettings = new \SiteSettingsRepository(ENGINE_DIR . 'data' . DIRECTORY_SEPARATOR . 'site-settings.json');
        (new \LegacySiteSettingsMigrator($db, $siteSettings))->migrateIfNeeded($siteDefaults);
        $siteState = $siteSettings->current($siteDefaults);
        $siteOverrides = is_array($siteState['settings'] ?? null) ? $siteState['settings'] : [];
        $config['siteSettings'] = array_replace($siteDefaults, $siteOverrides);

        $logger = new \Logger('lastlog');
        $request = \HttpRequest::fromGlobals($network);
        $session = new \UserSession($db, $config, $network);
        $observability = is_array($config['observability'] ?? null)
            ? $config['observability']
            : [];

        \RequestTelemetry::bootstrap($logger, $request, $session, $observability);
        \RuntimeErrorHandler::adoptRequestId(\RequestTelemetry::requestId());
        \RequestTelemetry::annotate([
            'environment' => (string)($config['environment']['name'] ?? 'production'),
            'serviceVersion' => (string)($config['siteSettings']['ServiceVersion'] ?? 'unknown'),
        ]);

        (new UserSessionSynchronizer($db, $logger, $request, $session, $config))->synchronize();

        $modules = new \ModulesLoader($db, $logger, $request, $session, $config);
        return new ApplicationContext($db, $logger, $request, $session, $modules, $config);
    }
}
