<?php

declare(strict_types=1);

use FoxCMS\Engine\Monitoring\MonitoringRecordStore;
use PHPMinecraft\MinecraftQuery\MinecraftQueryResolver;

if (!defined('FOXXEY')) {
    http_response_code(403);
    exit('Forbidden');
}
if (!defined('foxesMon')) {
    define('foxesMon', true);
}

UtilityLoader::load('McQuery', '1.0.0');

/** Minecraft server monitoring domain service. */
final class FoxesMon
{
    /** @var list<array{name:string,host:string,port:int}> */
    private array $servers;

    /** @var list<array<string, mixed>> */
    private array $results = [];

    /** @var array{all:int,day:int} */
    private array $record;

    private MonitoringRecordStore $records;

    /** @param array<string, mixed> $monitorConfig */
    public function __construct(
        private Logger $logger,
        string $serversJson,
        array $monitorConfig,
    ) {
        $this->servers = $this->parseServers($serversJson);
        $this->records = new MonitoringRecordStore($monitorConfig);
        $this->record = $this->records->load();
        $this->fetchServersData();
    }

    /** @return array<string, mixed> */
    public function outputMonitoringData(): array
    {
        return [
            'servers' => $this->results,
            'totalPlayersOnline' => array_sum(array_column($this->results, 'playersOnline')),
            'totalPlayersMax' => array_sum(array_column($this->results, 'playersMax')),
            'absoluteRecord' => $this->record['all'],
            'todaysRecord' => $this->record['day'],
        ];
    }

    private function fetchServersData(): void
    {
        foreach ($this->servers as $server) {
            try {
                $resolver = new MinecraftQueryResolver($server['host'], $server['port']);
                $result = $resolver->getResult(true);
                $playersOnline = max(0, (int)($result->getOnlinePlayers() ?? 0));
                $this->results[] = [
                    'serverName' => $server['name'],
                    'status' => $result->isOnline() ? 'online' : 'offline',
                    'version' => $result->getVersion() ?? null,
                    'playersOnline' => $playersOnline,
                    'playersMax' => max(0, (int)($result->getMaxPlayers() ?? 0)),
                    'playersOnServer' => $result->getPlayersSample(),
                    'favicon' => $result->getFavicon() ?? null,
                ];
                $this->record['all'] = $this->records->updateAbsolute($playersOnline);
                $this->record['day'] = $this->records->updateDay($playersOnline);
            } catch (Throwable $error) {
                $this->results[] = [
                    'serverName' => $server['name'],
                    'status' => 'offline',
                    'version' => null,
                    'playersOnline' => 0,
                    'playersMax' => 0,
                    'playersOnServer' => [],
                    'favicon' => null,
                ];
                $this->logger->exception(
                    'monitoring.server.query_failed',
                    $error,
                    'Minecraft server monitoring query failed.',
                    [
                        'component' => 'monitoring',
                        'operation' => 'query_server',
                        'serverName' => $server['name'],
                    ],
                );
            }
        }
    }

    /** @return list<array{name:string,host:string,port:int}> */
    private function parseServers(string $serversJson): array
    {
        $decoded = json_decode($serversJson, true);
        if (!is_array($decoded) || !array_is_list($decoded)) {
            return [];
        }
        $servers = [];
        foreach ($decoded as $server) {
            if (!is_array($server)) {
                continue;
            }
            $name = trim((string)($server['serverName'] ?? ''));
            $host = trim((string)($server['host'] ?? ''));
            $port = filter_var($server['port'] ?? null, FILTER_VALIDATE_INT);
            if ($name === '' || strlen($name) > 128
                || $host === '' || strlen($host) > 253
                || $port === false || $port < 1 || $port > 65535) {
                continue;
            }
            $servers[] = [
                'name' => $name,
                'host' => $host,
                'port' => (int)$port,
            ];
        }
        return $servers;
    }
}
