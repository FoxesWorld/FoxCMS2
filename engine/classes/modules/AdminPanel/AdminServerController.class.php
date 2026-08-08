<?php

declare(strict_types=1);

/**
 * Owns game-server persistence, runtime catalogs and structured server fields.
 */
final class AdminServerController
{
    private const SERVER_FIELDS = [
        'serverName', 'host', 'port', 'ignoreDirs', 'enabled', 'checkLib',
        'serverGroups', 'serverDescription', 'serverVersion', 'jreVersion',
        'serverImage', 'modsInfo',
    ];

    private RuntimeJdkCatalog $runtimeJdkCatalog;
    private GameVersionCatalog $gameVersionCatalog;

    public function __construct(
        private db $db,
        private array $request,
        private Logger $logger,
        private UploadService $uploads,
        private AdminRequestPayload $payload,
        private AdminResponder $responder,
        private GroupRepository $groupRepository,
        private AdminGroupListNormalizer $groupNormalizer,
    ) {
        $this->runtimeJdkCatalog = new RuntimeJdkCatalog($this->bootstrapStorageDirectory());
        $this->gameVersionCatalog = new GameVersionCatalog($this->gameVersionsDirectory());
    }

    public function servers(): void {
        $stmt = $this->db->prepare('SELECT `id`, ' . $this->quotedFields(self::SERVER_FIELDS) . ' FROM `servers` ORDER BY `serverName`');
        $stmt->execute();
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($items as &$server) {
            $server['serverGroups'] = $this->groupNormalizer->normalize($server['serverGroups'] ?? []);
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

        $this->responder->send([
            'items' => $items,
            'groups' => $this->groupRepository->all(),
            'jdkOptions' => $jdkOptions,
            'jdkCatalog' => $catalog,
            'gameVersionOptions' => $gameVersionOptions,
            'gameVersionCatalog' => $gameVersionCatalog,
        ]);
    }

    public function saveServer(): void {
        $payload = $this->payload->object('entry');
        $originalName = trim((string)($this->request['originalName'] ?? ''));
        $serverId = max(0, (int)($payload['id'] ?? 0));
        $serverName = trim((string)($payload['serverName'] ?? ''));
        $enabled = filter_var($payload['enabled'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $runtimeWarning = '';
        if (!preg_match('/^[\p{L}\p{N}_ -]{1,64}$/u', $serverName)) {
            $this->responder->send(['message' => 'Некорректное имя сервера.', 'type' => 'error'], 400);
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
                    $this->responder->send(['message' => 'Некорректный порт.', 'type' => 'error'], 400);
                }
            }
            if ($field === 'host') {
                $value = trim((string)$value);
                if ($value === '' || strlen($value) > 255
                    || preg_match('/[\x00-\x20\x7F]/', $value) === 1) {
                    $this->responder->send(['message' => 'Некорректный адрес сервера.', 'type' => 'error'], 400);
                }
            }
            if ($field === 'serverVersion') {
                $value = trim((string)$value);
                if ($value !== '' && (strlen($value) > 128
                    || $value === '.' || $value === '..'
                    || preg_match('/[\\\/\x00-\x1F\x7F]/', $value) === 1
                )) {
                    $this->responder->send([
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
                        $this->responder->send([
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
                    $this->groupNormalizer->normalize($value),
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
            $this->responder->send([
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
                $this->responder->send(['message' => 'Сервер с таким именем уже существует.', 'type' => 'error'], 409);
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
                $this->responder->send([
                    'message' => $label . ' превышает допустимую длину.' . $suffix,
                    'type' => 'error',
                    'field' => $field !== '' ? $field : null,
                ], 422);
            }
            if (str_contains($message, '22032') || str_contains($message, '3140') || str_contains($message, '3141')) {
                $this->responder->send(['message' => 'Структурированные данные сервера содержат некорректный JSON.', 'type' => 'error'], 422);
            }
            $this->responder->send([
                'message' => 'Не удалось сохранить сервер. Проверьте актуальность схемы базы данных и применённые миграции.',
                'type' => 'error',
            ], 409);
        }

        $this->responder->send([
            'message' => $runtimeWarning !== ''
                ? 'Сервер сохранён. ' . $runtimeWarning
                : 'Сервер сохранён.',
            'type' => $runtimeWarning !== '' ? 'warning' : 'success',
        ]);
    }

    public function deleteServer(): void {
        $serverName = trim((string)($this->request['serverName'] ?? ''));
        if ($serverName === '') $this->responder->send(['message' => 'Имя сервера не указано.', 'type' => 'error'], 400);
        $stmt = $this->db->prepare('DELETE FROM `servers` WHERE `serverName` = :serverName');
        $stmt->execute([':serverName' => $serverName]);
        $this->responder->send(['message' => 'Сервер удалён.', 'type' => 'success']);
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
            $this->responder->send([
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
                        $this->responder->send(['message' => 'Игнорируемые каталоги должны быть JSON-массивом или списком через запятую.', 'type' => 'error'], 400);
                    }
                    $source = $decoded;
                } else {
                    $source = preg_split('/[\r\n,]+/', $raw) ?: [];
                }
            }
        } elseif (is_array($value) && array_is_list($value)) {
            $source = $value;
        } else {
            $this->responder->send(['message' => 'Игнорируемые каталоги должны быть массивом.', 'type' => 'error'], 400);
        }

        $directories = [];
        foreach ($source as $entry) {
            if (!is_string($entry) && !is_numeric($entry)) {
                $this->responder->send(['message' => 'Каждый игнорируемый каталог должен быть строкой.', 'type' => 'error'], 400);
            }
            $directory = trim(str_replace('\\', '/', (string)$entry));
            if ($directory === '') continue;
            if (mb_strlen($directory, 'UTF-8') > 255
                || preg_match('/[\x00-\x1F\x7F]/u', $directory) === 1) {
                $this->responder->send(['message' => 'Некорректное имя игнорируемого каталога.', 'type' => 'error'], 400);
            }
            foreach (explode('/', $directory) as $segment) {
                if ($segment === '..') {
                    $this->responder->send(['message' => 'Сегмент .. запрещён в игнорируемых каталогах.', 'type' => 'error'], 400);
                }
            }
            $directories[] = $directory;
            if (count($directories) > 256) {
                $this->responder->send(['message' => 'Указано слишком много игнорируемых каталогов.', 'type' => 'error'], 400);
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
                $this->responder->send(['message' => 'Информация о модах содержит некорректный JSON.', 'type' => 'error'], 400);
            }
        } elseif (is_object($value)) {
            $value = (array)$value;
        }
        if (!is_array($value) || !array_is_list($value)) {
            $this->responder->send(['message' => 'Информация о модах должна быть JSON-массивом.', 'type' => 'error'], 400);
        }
        if (count($value) > 2000) {
            $this->responder->send(['message' => 'Список модов слишком велик.', 'type' => 'error'], 413);
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
            $this->responder->send(['message' => 'Некорректный путь к изображению сервера.', 'type' => 'error'], 400);
        }
        if (str_starts_with($value, 'uploads/')) {
            $value = '/' . $value;
        }
        $decodedPath = rawurldecode((string)(parse_url($value, PHP_URL_PATH) ?? $value));
        foreach (explode('/', str_replace('\\', '/', $decodedPath)) as $segment) {
            if ($segment === '..') {
                $this->responder->send(['message' => 'Переходы .. в пути изображения сервера запрещены.', 'type' => 'error'], 400);
            }
        }
        if (str_starts_with($value, '/uploads/')) {
            try {
                return $this->uploads->validateReference(UploadPurpose::SERVER_IMAGE, $value);
            } catch (UploadException $error) {
                $this->responder->send(['message' => $error->getMessage(), 'type' => 'error'], $error->httpStatus());
            }
        }
        if (str_starts_with($value, '//')) {
            $this->responder->send(['message' => 'Protocol-relative URL изображения сервера запрещён.', 'type' => 'error'], 400);
        }
        $scheme = parse_url($value, PHP_URL_SCHEME);
        if (is_string($scheme) && $scheme !== '') {
            if (!in_array(strtolower($scheme), ['http', 'https'], true)
                || filter_var($value, FILTER_VALIDATE_URL) === false) {
                $this->responder->send(['message' => 'Некорректный URL изображения сервера.', 'type' => 'error'], 400);
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

    private function quotedFields(array $fields): string {
        return implode(', ', array_map(fn($field) => '`' . $field . '`', $fields));
    }
}
