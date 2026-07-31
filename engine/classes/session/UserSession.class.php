<?php

declare(strict_types=1);

final class UserSession
{
    private const USER_FIELDS = [
        'uuid',
        'user_id',
        'email',
        'login',
        'user_group',
        'realname',
        'reg_date',
        'last_date',
        'logged_ip',
        'profilePhoto',
        'userStatus',
        'land',
        'colorScheme',
        'badges',
        'balance',
        'serversOnline',
        'userPerms',
    ];

    private const DERIVED_FIELDS = [
        'isLogged',
        'groupName',
        'groupTag',
        'groupColor',
    ];

    private array $user;
    private int $idleSeconds;
    private int $absoluteSeconds;

    public function __construct(
        private db $db,
        private array $config,
        private NetworkContext $network,
    ) {
        $security = is_array($config['securitySetings'] ?? null)
            ? $config['securitySetings']
            : [];
        $this->idleSeconds = max(300, (int)($security['sessionIdleSeconds'] ?? 7200));
        $this->absoluteSeconds = max(900, (int)($security['sessionAbsoluteSeconds'] ?? 86400));
        $this->user = $this->guestDefaults();
        $this->hydrateFromNativeSession();
        $this->enrichGroup();
    }

    public function all(): array
    {
        return $this->user;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->user[$key] ?? $default;
    }

    public function isLogged(): bool
    {
        return ($this->user['isLogged'] ?? false) === true
            && $this->uuid() !== ''
            && $this->login() !== 'anonymous';
    }

    public function uuid(): string
    {
        $uuid = (string)($this->user['uuid'] ?? '');
        return Uuid::isValid($uuid) ? Uuid::normalize($uuid) : '';
    }

    public function compactUuid(): string
    {
        $uuid = $this->uuid();
        return $uuid === '' ? '' : Uuid::compact($uuid);
    }

    public function login(): string
    {
        return (string)($this->user['login'] ?? 'anonymous');
    }

    public function group(): int
    {
        return (int)($this->user['user_group'] ?? 5);
    }

    public function isAdmin(): bool
    {
        return $this->group() === 1 || $this->get('groupTag') === 'admin';
    }

    public function set(string $key, mixed $value, bool $persist = false): void
    {
        if (!$this->isAllowedField($key)) {
            throw new InvalidArgumentException('Unsupported session field: ' . $key);
        }
        if ($key === 'uuid') {
            $value = Uuid::normalize((string)$value);
        }
        $this->user[$key] = $value;
        if ($persist) {
            $_SESSION[$key] = $value;
            $this->touchNativeSession();
        }
    }

    public function merge(array $values, bool $persist = false): void
    {
        foreach ($values as $key => $value) {
            if (!is_string($key) || !$this->isAllowedField($key)) {
                continue;
            }
            if ($key === 'uuid') {
                $value = Uuid::normalize((string)$value);
            }
            $this->user[$key] = $value;
            if ($persist) {
                $_SESSION[$key] = $value;
            }
        }
        if ($persist) {
            $this->touchNativeSession();
        }
        $this->enrichGroup();
    }

    public function authenticate(array $userData): void
    {
        $safeUser = $this->sanitizeUserData($userData);
        if (($safeUser['login'] ?? 'anonymous') === 'anonymous' || empty($safeUser['uuid'])) {
            throw new InvalidArgumentException('Authenticated user data must contain UUID and login.');
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }

        $this->persistAuthenticatedState($safeUser, true);
    }

    public function refreshFromDatabase(): void
    {
        $this->synchronizeWithDatabase();
    }

    /**
     * Rehydrates the server-side session from the authoritative database row.
     * UUID is always attempted first. user_id is used only as a migration bridge
     * when an already-issued session still contains the pre-migration MD5 UUID.
     */
    public function synchronizeWithDatabase(): void
    {
        if (!$this->isLogged()) {
            return;
        }

        $data = $this->loadDatabaseUserByUuid($this->uuid());
        if ($data === null) {
            $userId = max(0, (int)$this->get('user_id', 0));
            $data = $userId > 0 ? $this->loadDatabaseUserById($userId) : null;
        }
        if ($data === null) {
            $this->clear();
            return;
        }

        $safeUser = $this->sanitizeUserData($data);
        if (($safeUser['login'] ?? 'anonymous') === 'anonymous' || empty($safeUser['uuid'])) {
            $this->clear();
            return;
        }

        $this->persistAuthenticatedState($safeUser, false);
    }

    public function clear(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires' => time() - 42000,
                'path' => (string)($params['path'] ?? '/'),
                'domain' => (string)($params['domain'] ?? ''),
                'secure' => (bool)($params['secure'] ?? $this->network->isSecure()),
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        }
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        $this->user = $this->guestDefaults();
        $this->enrichGroup();
    }

    public function userFolder(): string
    {
        $uuid = $this->uuid();
        if ($uuid === '') {
            throw new RuntimeException('A guest has no private user directory.');
        }
        return ROOT_DIR . UPLOADS_DIR . USR_SUBFOLDER . $uuid . DIRECTORY_SEPARATOR;
    }

    public function publicUserFolder(): string
    {
        $uuid = $this->uuid();
        if ($uuid === '') {
            throw new RuntimeException('A guest has no public user directory.');
        }
        return rtrim(UPLOADS_DIR, '/') . '/' . trim(USR_SUBFOLDER, '/') . '/' . rawurlencode($uuid) . '/';
    }

    public function gameFiles(): array
    {
        $compact = $this->compactUuid();
        if ($compact === '') {
            throw new RuntimeException('A guest has no game files.');
        }
        $folder = $this->userFolder();
        return [
            'skin' => $folder . $compact . '-skin.png',
            'cape' => $folder . $compact . '-cape.png',
        ];
    }

    /**
     * Replaces only account-owned session fields. Security metadata such as the
     * CSRF token must survive database refreshes and session ID regeneration.
     */
    private function persistAuthenticatedState(array $safeUser, bool $resetLifetime): void
    {
        foreach (array_merge(self::USER_FIELDS, self::DERIVED_FIELDS) as $field) {
            unset($_SESSION[$field]);
        }
        foreach ($safeUser as $field => $value) {
            $_SESSION[$field] = $value;
        }

        $_SESSION['isLogged'] = true;
        if ($resetLifetime || empty($_SESSION['_fox_issued_at'])) {
            $_SESSION['_fox_issued_at'] = CURRENT_TIME;
        }
        $_SESSION['_fox_last_seen'] = CURRENT_TIME;

        $this->user = array_merge($this->guestDefaults(), $safeUser, ['isLogged' => true]);
        $this->enrichGroup();
    }

    private function loadDatabaseUserByUuid(string $uuid): ?array
    {
        if (!Uuid::isValid($uuid)) {
            return null;
        }

        $placeholders = [];
        $parameters = [];
        foreach (Uuid::databaseCandidates($uuid) as $index => $candidate) {
            $placeholder = ':uuid_' . $index;
            $placeholders[] = $placeholder;
            $parameters[$placeholder] = $candidate;
        }
        $statement = $this->db->prepare(
            'SELECT * FROM `users` WHERE `uuid` IN (' . implode(', ', $placeholders) . ') LIMIT 1'
        );
        $statement->execute($parameters);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    private function loadDatabaseUserById(int $userId): ?array
    {
        $statement = $this->db->prepare(
            'SELECT * FROM `users` WHERE `user_id` = :userId LIMIT 1'
        );
        $statement->execute([':userId' => $userId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    private function hydrateFromNativeSession(): void
    {
        if (empty($_SESSION['isLogged']) || $_SESSION['isLogged'] !== true) {
            return;
        }

        $issuedAt = (int)($_SESSION['_fox_issued_at'] ?? 0);
        $lastSeen = (int)($_SESSION['_fox_last_seen'] ?? 0);
        $expired = $issuedAt <= 0
            || $lastSeen <= 0
            || CURRENT_TIME - $lastSeen > $this->idleSeconds
            || CURRENT_TIME - $issuedAt > $this->absoluteSeconds;

        if ($expired) {
            $this->clear();
            return;
        }

        try {
            $safeUser = $this->sanitizeUserData($_SESSION);
        } catch (InvalidArgumentException) {
            $this->clear();
            return;
        }
        if (($safeUser['login'] ?? 'anonymous') === 'anonymous' || empty($safeUser['uuid'])) {
            $this->clear();
            return;
        }

        $this->user = array_merge($this->guestDefaults(), $safeUser, ['isLogged' => true]);
        $this->touchNativeSession();
    }

    private function touchNativeSession(): void
    {
        if (!empty($_SESSION['isLogged'])) {
            $_SESSION['_fox_last_seen'] = CURRENT_TIME;
        }
    }

    private function sanitizeUserData(array $data): array
    {
        $safe = [];
        foreach (self::USER_FIELDS as $field) {
            if (array_key_exists($field, $data) && (is_scalar($data[$field]) || $data[$field] === null)) {
                $safe[$field] = $data[$field];
            }
        }

        $uuid = trim((string)($safe['uuid'] ?? ''));
        $safe['uuid'] = $uuid === '' ? '' : Uuid::normalize($uuid);
        $safe['user_id'] = max(0, (int)($safe['user_id'] ?? 0));
        $safe['user_group'] = max(1, (int)($safe['user_group'] ?? 5));
        $safe['login'] = (string)($safe['login'] ?? 'anonymous');
        $safe['logged_ip'] = $this->network->clientIp();
        $safe['last_date'] = (int)($safe['last_date'] ?? CURRENT_TIME);
        return $safe;
    }

    private function enrichGroup(): void
    {
        $group = max(1, (int)($this->user['user_group'] ?? 5));
        try {
            $statement = $this->db->prepare(
                'SELECT `groupNum`, `groupName`, `groupType`, `groupColor` '
                . 'FROM `groupAssociation` WHERE `groupNum` = :group LIMIT 1'
            );
            $statement->execute([':group' => $group]);
            $row = $statement->fetch(PDO::FETCH_ASSOC);
        } catch (Throwable) {
            $row = false;
        }

        $this->user['user_group'] = $row === false ? $group : (int)($row['groupNum'] ?? $group);
        $this->user['groupName'] = $row === false ? 'Гость' : (string)($row['groupName'] ?? 'Гость');
        $this->user['groupTag'] = $row === false ? 'guest' : (string)($row['groupType'] ?? 'guest');
        $this->user['groupColor'] = $row === false ? '#ffffff' : (string)($row['groupColor'] ?? '#ffffff');

        if ($this->isLogged()) {
            $_SESSION['user_group'] = $this->user['user_group'];
            $_SESSION['_fox_last_seen'] = CURRENT_TIME;
        }
    }

    private function guestDefaults(): array
    {
        return [
            'isLogged' => false,
            'uuid' => '',
            'user_id' => 0,
            'email' => '',
            'login' => 'anonymous',
            'realname' => '',
            'reg_date' => 0,
            'last_date' => CURRENT_TIME,
            'logged_ip' => $this->network->clientIp(),
            'user_group' => 5,
            'balance' => '[]',
            'profilePhoto' => UPLOADS_DIR . USR_SUBFOLDER . 'anonymous/avatar.jpg',
        ];
    }

    private function isAllowedField(string $key): bool
    {
        return in_array($key, self::USER_FIELDS, true) || in_array($key, self::DERIVED_FIELDS, true);
    }
}
