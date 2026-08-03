<?php

if (!defined('FOXXEY')) {
    exit("Not a FOXXEY");
} else {
    define('foxesMon', true);
}



UtilityLoader::load('McQuery', "1.0.0");
use PHPMinecraft\MinecraftQuery\MinecraftQueryResolver;
class FoxesMon
{
    private $logger;
    private $servers;
    private $time;
    private $results = [];
    private $record = [];

    public function __construct($logger, $serversArray, $time)
    {
        global $config;

        $this->logger = $logger;
        $this->servers = $this->parseServers($serversArray);
        $this->time = $time;

        $this->initializeRecords($config['monitor']);
        $this->fetchServersData();
    }

    public function outputMonitoringData()
    {
        $response = [
            'servers' => $this->results,
            'totalPlayersOnline' => array_sum(array_column($this->results, 'playersOnline')),
            'totalPlayersMax' => array_sum(array_column($this->results, 'playersMax')),
            'absoluteRecord' => $this->record['all'] ?? 0,
            'todaysRecord' => $this->record['day'] ?? 0,
        ];

        header('Content-Type: application/json');
        echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        exit;
    }

private function fetchServersData()
{
	global $config;
    foreach ($this->servers as $server) {
        try {
            $resolver = new MinecraftQueryResolver($server['host'], $server['port']);
            $result = $resolver->getResult($tryOldQueryProtocolPre17 = true);

            $playersOnline = $result->getOnlinePlayers() ?? 0;
            $this->results[] = [
                'serverName' => $server['name'],
                'status' => $result->isOnline() ? 'online' : 'offline',
                'version' => $result->getVersion() ?? null,
                'playersOnline' => $playersOnline,
                'playersMax' => $result->getMaxPlayers() ?? 0,
                'playersOnServer' => $result->getPlayersSample(),
                'favicon' => $result->getFavicon() ?? null,
            ];

            // Обновление абсолютного рекорда
            if ($playersOnline > $this->record['all']) {
                $this->record['all'] = $playersOnline;
                file_put_contents($config['monitor']['absoluteRecordPath'], $playersOnline);
            }

            // Обновление дневного рекорда
            if ($playersOnline > $this->record['day']) {
                $this->record['day'] = $playersOnline;
                file_put_contents($config['monitor']['dayRecordPath'], $playersOnline);
            }
        } catch (Exception $e) {
            $this->results[] = [
                'serverName' => $server['name'],
                'status' => 'offline',
                'version' => null,
                'playersOnline' => 0,
                'playersMax' => 0,
                'favicon' => null,
            ];
        }
    }
}



    private function initializeRecords($config)
    {
		global $config;
        $this->record['all'] = @file_get_contents($config['monitor']['absoluteRecordPath']) ?: 0;
        $this->record['day'] = @file_get_contents($config['monitor']['dayRecordPath']) ?: 0;
    }

    private function parseServers($serversArray)
    {
        $decoded = json_decode((string)$serversArray, true);
        if (!is_array($decoded) || !array_is_list($decoded)) {
            return [];
        }
        $servers = [];
        foreach ($decoded as $server) {
            if (!is_array($server)) continue;
            $name = trim((string)($server['serverName'] ?? ''));
            $host = trim((string)($server['host'] ?? ''));
            $port = filter_var($server['port'] ?? null, FILTER_VALIDATE_INT);
            if ($name === '' || $host === '' || $port === false || $port < 1 || $port > 65535) {
                continue;
            }
            $servers[] = [
                'name' => $name,
                'host' => $host,
                'port' => $port,
            ];
        }
        return $servers;
    }
}
