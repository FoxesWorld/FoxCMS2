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
        'login', 'realname', 'email', 'userStatus', 'user_group', 'balance', 'badges', 'serversOnline'
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
            'key' => 'groupName',
            'fields' => ['groupName', 'groupColor', 'groupNum', 'groupType'],
        ],
    ];

    private $db;
    private array $request;
    private UserSession $session;
    private ?Logger $logger;
    private MaintenanceModeRepository $maintenanceRepository;

    public function __construct(array $request, $db, UserSession $session, ?Logger $logger = null) {
        if (!$session->isAdmin()) {
            $this->respond(['message' => 'Недостаточно прав.', 'type' => 'error'], 403);
        }

        $this->db = $db;
        $this->session = $session;
        $this->logger = $logger;
        $this->request = $request;
        $this->maintenanceRepository = new MaintenanceModeRepository($db);
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
            $this->logger?->logError('Admin operation failed.', [
                'action' => $action,
                'exception' => $error::class,
                'message' => $error->getMessage(),
                'file' => $error->getFile(),
                'line' => $error->getLine(),
            ]);
            $this->respond(['message' => 'Административная операция завершилась ошибкой.', 'type' => 'error'], 500);
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
            $where = ' WHERE `login` LIKE :search OR `email` LIKE :search OR `realname` LIKE :search';
            $params[':search'] = '%' . $search . '%';
        }

        $sql = 'SELECT `uuid`, `user_id`, `login`, `email`, `realname`, `user_group`, `last_date`, `reg_date`, `profilePhoto`, `userStatus`, `balance`, `badges`, `serversOnline` FROM `users`' . $where . ' ORDER BY `last_date` DESC LIMIT ' . $limit . ' OFFSET ' . $offset;
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $this->respond(['items' => $rows ?: [], 'limit' => $limit, 'offset' => $offset]);
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
            if ($field === 'user_group') {
                $value = filter_var($value, FILTER_VALIDATE_INT);
                if ($value === false || $value < 1) {
                    $this->respond(['message' => 'Некорректная группа.', 'type' => 'error'], 400);
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
        $this->respond(['items' => $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []]);
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
            if (in_array($field, ['enabled', 'checkLib'], true)) $value = filter_var($value, FILTER_VALIDATE_BOOLEAN) ? 'true' : 'false';
            if ($field === 'port') {
                $value = filter_var($value, FILTER_VALIDATE_INT);
                if ($value === false || $value < 1 || $value > 65535) $this->respond(['message' => 'Некорректный порт.', 'type' => 'error'], 400);
            }
            if (in_array($field, ['ignoreDirs', 'serverGroups', 'modsInfo'], true) && (is_array($value) || is_object($value))) {
                $value = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
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
        $settings = $this->maintenanceRepository->current(true);
        $statement = $this->db->prepare(
            'SELECT `groupNum`, `groupName`, `groupColor`, `groupType` '
            . 'FROM `groupAssociation` ORDER BY `groupNum`'
        );
        $statement->execute();
        $groups = $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($groups as &$group) {
            $group['groupNum'] = (int)($group['groupNum'] ?? 0);
        }
        unset($group);
        $this->respond(['settings' => $settings, 'groups' => $groups]);
    }

    private function saveMaintenance(): void {
        $payload = $this->decodeObject('entry');
        $enabled = filter_var($payload['enabled'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $requestedGroups = is_array($payload['allowedGroups'] ?? null)
            ? $payload['allowedGroups']
            : [];

        $statement = $this->db->prepare('SELECT `groupNum` FROM `groupAssociation`');
        $statement->execute();
        $existingGroups = array_map('intval', $statement->fetchAll(PDO::FETCH_COLUMN) ?: []);
        $allowedGroups = [1];
        foreach ($requestedGroups as $group) {
            $value = filter_var($group, FILTER_VALIDATE_INT);
            if ($value !== false && in_array((int)$value, $existingGroups, true)) {
                $allowedGroups[] = (int)$value;
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
        $this->respond(['file' => $name, 'lines' => $lines]);
    }

    private function catalog(): void {
        [$spec] = $this->catalogSpec();
        $stmt = $this->db->prepare('SELECT ' . $this->quotedFields($spec['fields']) . ' FROM `' . $spec['table'] . '` ORDER BY `' . $spec['key'] . '`');
        $stmt->execute();
        $this->respond(['items' => $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []]);
    }

    private function saveCatalogEntry(): void {
        [$spec] = $this->catalogSpec();
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
        [$spec] = $this->catalogSpec();
        $key = trim((string)($this->request['key'] ?? ''));
        if ($key === '') $this->respond(['message' => 'Ключ не указан.', 'type' => 'error'], 400);
        $stmt = $this->db->prepare('DELETE FROM `' . $spec['table'] . '` WHERE `' . $spec['key'] . '` = :key');
        $stmt->execute([':key' => $key]);
        $this->respond(['message' => 'Запись удалена.', 'type' => 'success']);
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

    private function respond(array $payload, int $status = 200): never {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        die(json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
}
