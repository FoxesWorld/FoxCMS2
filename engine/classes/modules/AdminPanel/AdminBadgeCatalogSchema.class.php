<?php

declare(strict_types=1);

/**
 * Verifies the database boundary required by badge administration.
 */
final class AdminBadgeCatalogSchema
{
    public function __construct(private db $db)
    {
    }

    public function assertAvailable(): void
    {
        $statement = $this->db->prepare(
            "SELECT COUNT(*) FROM information_schema.COLUMNS "
            . "WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'badgesList' "
            . "AND COLUMN_NAME IN ('id', 'badgeName', 'description', 'img')"
        );
        $statement->execute();
        if ((int)$statement->fetchColumn() !== 4) {
            throw new HttpException(
                'Не удалось загрузить бейджи: таблица badgesList отсутствует или повреждена.',
                503,
            );
        }
    }
}
