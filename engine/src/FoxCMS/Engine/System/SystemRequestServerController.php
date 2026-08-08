<?php

declare(strict_types=1);

namespace FoxCMS\Engine\System;

final class SystemRequestServerController
{
    public function __construct(
        private \db $db,
        private \Logger $logger,
        private \HttpRequest $request,
        private \UserSession $session,
        private array $config,
    ) {
    }

    public function parseServers(): never
    {
        $serverName = $this->request->string('serverName');
        if ($serverName !== '' && preg_match('/^[\p{L}\p{N}_ .-]{1,64}$/uD', $serverName) !== 1) {
            throw new \InvalidArgumentException('Invalid server name.');
        }

        \UtilityLoader::load('ServerParser', '1.0.0');
        $parser = new \ServerParser($this->db, $this->session->uuid());
        \JsonResponse::rawJson($parser->parseServers($serverName !== '' ? $serverName : null));
    }

    public function monitor(): never
    {
        \UtilityLoader::load('ServerParser', '1.0.0');
        $parser = new \ServerParser($this->db, $this->session->uuid());
        $serversJson = $parser->parseServers();
        $servers = json_decode($serversJson, true);
        if (is_array($servers) && ($servers['error'] ?? null) === 'ServerNotFound') {
            \JsonResponse::send([
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
            throw new \RuntimeException('Server parser returned an invalid monitoring payload.');
        }

        $monitorConfig = is_array($this->config['monitor'] ?? null) ? $this->config['monitor'] : [];
        $monitor = new \FoxesMon($this->logger, $serversJson, $monitorConfig);
        \JsonResponse::send($monitor->outputMonitoringData());
    }

    public function topPlayers(): void
    {
        (new \UserTop($this->db, $this->logger))->getTopPlayers();
    }

    public function infoBox(): never
    {
        $login = $this->request->string('user', 'anonymous');
        if (preg_match('/^[A-Za-z0-9_.-]{1,64}$/D', $login) !== 1) {
            throw new \InvalidArgumentException('Invalid login.');
        }

        \UtilityLoader::load('LoadUserInfo', '1.0.0');
        $userInfo = \LoadUserInfo::byLogin($login, $this->db)->userInfoArray();
        $tag = \GroupRepository::normalizeTag($userInfo['groupTag'] ?? 'guest');
        (new \InfoBox($this->db, $this->logger, $tag))->getInfoBox();
        throw new \LogicException('InfoBox did not terminate the request.');
    }
}
