<?php

declare(strict_types=1);

namespace FoxCMS\Engine\Admin;

final class AdminSystemController
{
    public function __construct(
        private \db $db,
        private \Logger $logger,
        private \UserSession $session,
        private array $request,
        private array $config,
        private \SiteSettingsRepository $siteSettings,
        private \MaintenanceModeRepository $maintenance,
        private \GroupRepository $groups,
        private \LogQueryService $logs,
        private \AdminRequestPayload $payload,
        private \AdminResponder $responder,
    ) {
    }

    public function overview(): never
    {
        $users = (int)$this->scalar('SELECT COUNT(*) FROM `users`');
        $recent = (int)$this->scalar(
            'SELECT COUNT(*) FROM `users` WHERE `last_date` >= :threshold',
            [':threshold' => time() - 86400],
        );
        $servers = (int)$this->scalar('SELECT COUNT(*) FROM `servers`');
        $enabledServers = (int)$this->scalar(
            "SELECT COUNT(*) FROM `servers` WHERE LOWER(CAST(`enabled` AS CHAR)) IN ('true', '1')"
        );
        $hardware = (int)$this->scalar('SELECT COUNT(*) FROM `system_hardware_inventory`');

        $this->responder->send([
            'users' => $users,
            'recentUsers' => $recent,
            'servers' => $servers,
            'enabledServers' => $enabledServers,
            'hardwareReports' => $hardware,
        ]);
    }

    public function siteSettings(): never
    {
        $fallback = is_array($this->config['siteSettings'] ?? null)
            ? $this->config['siteSettings']
            : [];
        $this->responder->send($this->siteSettings->current($fallback));
    }

    public function saveSiteSettings(): never
    {
        $entry = $this->payload->object('entry');
        $fallback = is_array($this->config['siteSettings'] ?? null)
            ? $this->config['siteSettings']
            : [];
        $state = $this->siteSettings->save($entry, $fallback, $this->session->uuid());
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
        $this->responder->send(array_merge($state, [
            'message' => 'Настройки сайта и SEO сохранены. Публичные метатеги обновятся при следующей загрузке страницы.',
            'type' => 'success',
        ]));
    }

    public function maintenance(): never
    {
        $this->responder->send([
            'settings' => $this->maintenance->current(true),
            'groups' => $this->groups->all(),
        ]);
    }

    public function saveMaintenance(): never
    {
        $payload = $this->payload->object('entry');
        $enabled = filter_var($payload['enabled'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $requestedGroups = is_array($payload['allowedGroups'] ?? null)
            ? $payload['allowedGroups']
            : [];
        $allowedGroups = ['admin'];
        foreach ($requestedGroups as $group) {
            $tag = \GroupRepository::normalizeTag($group, '');
            if ($tag !== '' && $this->groups->exists($tag)) {
                $allowedGroups[] = $tag;
            }
        }

        $title = trim((string)($payload['title'] ?? ''));
        $message = trim((string)($payload['message'] ?? ''));
        if (mb_strlen($title) > 160 || mb_strlen($message) > 1200) {
            $this->responder->send([
                'message' => 'Текст режима техработ превышает допустимую длину.',
                'type' => 'error',
            ], 400);
        }

        $settings = $this->maintenance->save(
            $enabled,
            array_values(array_unique($allowedGroups)),
            $title,
            $message,
            $this->session->uuid(),
        );
        $this->responder->send([
            'message' => $enabled ? 'Режим технических работ включён.' : 'Режим технических работ отключён.',
            'type' => 'success',
            'settings' => $settings,
        ]);
    }

    public function hardware(): never
    {
        $this->responder->send(\HardwareInventoryStatisticsService::fromDatabase($this->db)->statistics());
    }

    public function showLog(): never
    {
        $this->log(false);
    }

    public function clearLog(): never
    {
        $this->log(true);
    }

    private function log(bool $clear): never
    {
        $name = (string)($this->request['file'] ?? 'lastlog');
        if ($clear) {
            $this->logs->clear($name);
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
            $this->responder->send(['message' => 'Log очищен.', 'type' => 'success']);
        }

        $result = $this->logs->read(
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
        $this->responder->send($result);
    }

    private function scalar(string $sql, array $params = []): mixed
    {
        $statement = $this->db->prepare($sql);
        $statement->execute($params);
        return $statement->fetchColumn();
    }
}
