<?php

declare(strict_types=1);

final class LegacySiteSettingsMigrator
{
    public function __construct(
        private db $db,
        private SiteSettingsRepository $repository,
    ) {
    }

    /** @param array<string, mixed> $fallback */
    public function migrateIfNeeded(array $fallback): bool
    {
        if ($this->repository->exists()) {
            return false;
        }

        try {
            $statement = $this->db->prepare(
                'SELECT `settings`, `updatedByUuid` FROM `site_settings` WHERE `id` = 1 LIMIT 1'
            );
            $statement->execute();
            $row = $statement->fetch(PDO::FETCH_ASSOC);
        } catch (Throwable) {
            return false;
        }

        if (!is_array($row)) {
            return false;
        }

        $decoded = json_decode((string)($row['settings'] ?? '{}'), true);
        $stored = is_array($decoded) && !array_is_list($decoded) ? $decoded : [];
        $updatedByUuid = (string)($row['updatedByUuid'] ?? '');
        $this->repository->save($stored, $fallback, $updatedByUuid);
        return true;
    }
}
