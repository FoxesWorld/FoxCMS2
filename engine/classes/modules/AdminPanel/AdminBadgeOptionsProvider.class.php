<?php

declare(strict_types=1);

/**
 * Provides the canonical badge selector used by administrative use-cases.
 */
final class AdminBadgeOptionsProvider
{
    public function __construct(
        private db $db,
        private AdminBadgeCatalogSchema $schema,
    ) {
    }

    /** @return list<array{id: int, badgeName: string, title: string, description: string, image: ?string}> */
    public function all(): array
    {
        $this->schema->assertAvailable();
        $stmt = $this->db->query(
            'SELECT `id`, `badgeName`, `description`, `img` FROM `badgesList` ORDER BY `badgeName`'
        );
        if (!$stmt instanceof PDOStatement) {
            throw new RuntimeException('Database query returned no badge statement.');
        }
        $options = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            if (!is_array($row)) continue;
            $badgeName = trim((string)($row['badgeName'] ?? ''));
            if ($badgeName === '') continue;
            $image = trim((string)($row['img'] ?? ''));
            $options[] = [
                'id' => (int)($row['id'] ?? 0),
                'badgeName' => $badgeName,
                'title' => $badgeName,
                'description' => trim((string)($row['description'] ?? '')),
                'image' => $image !== '' ? $image : null,
            ];
        }
        return $options;
    }
}
