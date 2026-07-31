<?php

declare(strict_types=1);

final class MaintenanceModeRepository
{
    private const DEFAULT_TITLE = 'Ведутся технические работы';
    private const DEFAULT_MESSAGE = 'Мы обновляем систему. Доступ будет восстановлен после завершения работ.';

    public function __construct(private db $db)
    {
    }

    /** @return array{enabled:bool,allowedGroups:list<int>,title:string,message:string,updatedAt:string,updatedByUuid:string,storageReady:bool} */
    public function current(bool $ensureSchema = false): array
    {
        if ($ensureSchema) {
            $this->ensureSchema();
        }

        try {
            $statement = $this->db->prepare(
                'SELECT `enabled`, `allowedGroups`, `title`, `message`, `updatedAt`, `updatedByUuid` '
                . 'FROM `site_maintenance` WHERE `id` = 1 LIMIT 1'
            );
            $statement->execute();
            $row = $statement->fetch(PDO::FETCH_ASSOC);
        } catch (Throwable) {
            return $this->defaults(false);
        }

        if (!is_array($row)) {
            return $this->defaults(true);
        }

        $groups = json_decode((string)($row['allowedGroups'] ?? '[]'), true);
        return [
            'enabled' => (bool)($row['enabled'] ?? false),
            'allowedGroups' => $this->normalizeGroups(is_array($groups) ? $groups : []),
            'title' => $this->cleanText((string)($row['title'] ?? ''), self::DEFAULT_TITLE, 160),
            'message' => $this->cleanText((string)($row['message'] ?? ''), self::DEFAULT_MESSAGE, 1200),
            'updatedAt' => (string)($row['updatedAt'] ?? ''),
            'updatedByUuid' => (string)($row['updatedByUuid'] ?? ''),
            'storageReady' => true,
        ];
    }

    /** @param list<int> $allowedGroups */
    public function save(
        bool $enabled,
        array $allowedGroups,
        string $title,
        string $message,
        string $updatedByUuid,
    ): array {
        $this->ensureSchema();
        $allowedGroups = $this->normalizeGroups($allowedGroups);
        $title = $this->cleanText($title, self::DEFAULT_TITLE, 160);
        $message = $this->cleanText($message, self::DEFAULT_MESSAGE, 1200);
        $updatedByUuid = Uuid::isValid($updatedByUuid) ? Uuid::canonical($updatedByUuid) : '';

        $statement = $this->db->prepare(
            'UPDATE `site_maintenance` SET '
            . '`enabled` = :enabled, `allowedGroups` = :allowedGroups, `title` = :title, '
            . '`message` = :message, `updatedByUuid` = :updatedByUuid, `updatedAt` = CURRENT_TIMESTAMP(4) '
            . 'WHERE `id` = 1'
        );
        $statement->execute([
            ':enabled' => $enabled ? 1 : 0,
            ':allowedGroups' => json_encode($allowedGroups, JSON_THROW_ON_ERROR),
            ':title' => $title,
            ':message' => $message,
            ':updatedByUuid' => $updatedByUuid === '' ? null : $updatedByUuid,
        ]);

        return $this->current();
    }

    public function ensureSchema(): void
    {
        $this->db->exec(
            'CREATE TABLE IF NOT EXISTS `site_maintenance` ('
            . '`id` TINYINT UNSIGNED NOT NULL, '
            . '`enabled` TINYINT(1) NOT NULL DEFAULT 0, '
            . '`allowedGroups` LONGTEXT NOT NULL, '
            . '`title` VARCHAR(160) NOT NULL, '
            . '`message` VARCHAR(1200) NOT NULL, '
            . '`updatedAt` DATETIME(4) NOT NULL DEFAULT CURRENT_TIMESTAMP(4) ON UPDATE CURRENT_TIMESTAMP(4), '
            . '`updatedByUuid` CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NULL, '
            . 'PRIMARY KEY (`id`)'
            . ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
        $statement = $this->db->prepare(
            'INSERT IGNORE INTO `site_maintenance` '
            . '(`id`, `enabled`, `allowedGroups`, `title`, `message`) '
            . 'VALUES (1, 0, :allowedGroups, :title, :message)'
        );
        $statement->execute([
            ':allowedGroups' => '[1]',
            ':title' => self::DEFAULT_TITLE,
            ':message' => self::DEFAULT_MESSAGE,
        ]);
    }

    private function defaults(bool $storageReady): array
    {
        return [
            'enabled' => false,
            'allowedGroups' => [1],
            'title' => self::DEFAULT_TITLE,
            'message' => self::DEFAULT_MESSAGE,
            'updatedAt' => '',
            'updatedByUuid' => '',
            'storageReady' => $storageReady,
        ];
    }

    /** @return list<int> */
    private function normalizeGroups(array $groups): array
    {
        $normalized = [1];
        foreach ($groups as $group) {
            $value = filter_var($group, FILTER_VALIDATE_INT);
            if ($value !== false && $value > 0) {
                $normalized[] = (int)$value;
            }
        }
        $normalized = array_values(array_unique($normalized));
        sort($normalized, SORT_NUMERIC);
        return $normalized;
    }

    private function cleanText(string $value, string $fallback, int $limit): string
    {
        $value = trim(preg_replace('/\s+/u', ' ', $value) ?? '');
        if ($value === '') {
            return $fallback;
        }
        return mb_substr($value, 0, $limit);
    }
}
