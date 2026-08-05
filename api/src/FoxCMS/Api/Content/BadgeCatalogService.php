<?php

declare(strict_types=1);

namespace FoxCMS\Api\Content;

use PDO;
use Throwable;
use UnexpectedValueException;

final class BadgeCatalogService
{
    private const SLUG_PATTERN = '/^[a-z0-9][a-z0-9-]{0,79}$/D';
    private const SELECT_BADGES = 'SELECT `id`, `badgeName`, `description`, `img` FROM `badgesList` ORDER BY `badgeName`, `id`';

    public function __construct(
        private readonly \db $database,
        private readonly \ThemeBadgePageRepository $repository,
    ) {
    }

    /** @return list<array<string, mixed>> */
    public function catalog(): array
    {
        $items = [];
        foreach ($this->rows() as $rowIndex => $row) {
            try {
                $item = $this->baseItem($row);
                $item['html'] = '';
                $item['pageConfigured'] = $this->repository->exists($item['id']);
                $items[] = $item;
            } catch (Throwable $error) {
                error_log(sprintf(
                    '[FoxesCraft badge catalog] skipped row=%d exception=%s message=%s',
                    $rowIndex,
                    $error::class,
                    $error->getMessage(),
                ));
            }
        }
        return $items;
    }

    /** @return array<string, mixed>|null */
    public function page(string $slug): ?array
    {
        if (preg_match(self::SLUG_PATTERN, $slug) !== 1) {
            return null;
        }
        foreach ($this->rows() as $row) {
            if (!hash_equals((string)($row['pageSlug'] ?? ''), $slug)) {
                continue;
            }
            if (!$this->repository->exists($slug)) {
                return null;
            }
            $page = $this->repository->read($slug);
            if (!is_array($page)) {
                return null;
            }

            $item = $this->baseItem($row);
            $item['html'] = $this->repository->render($page, [
                'badgeName' => $item['badgeName'],
                'description' => $item['description'],
                'img' => $item['image'] ?? '',
            ]);
            $item['pageConfigured'] = true;
            return $item;
        }
        return null;
    }

    /** @return list<array<string, mixed>> */
    private function rows(): array
    {
        $statement = $this->database->prepare(self::SELECT_BADGES);
        $statement->execute();
        return \BadgeSlug::assign($statement->fetchAll(PDO::FETCH_ASSOC) ?: []);
    }

    /** @param array<string, mixed> $row */
    private function baseItem(array $row): array
    {
        $badgeName = trim(Utf8Text::normalize($row['badgeName'] ?? ''));
        if ($badgeName === '') {
            throw new UnexpectedValueException('Badge row does not contain badgeName.');
        }
        $slug = trim(Utf8Text::normalize($row['pageSlug'] ?? ''));
        if (preg_match(self::SLUG_PATTERN, $slug) !== 1) {
            throw new UnexpectedValueException('Generated badge slug is invalid.');
        }
        $description = trim(Utf8Text::normalize($row['description'] ?? ''));
        $image = trim(Utf8Text::normalize($row['img'] ?? ''));

        return [
            'id' => $slug,
            'databaseId' => max(0, (int)($row['id'] ?? 0)),
            'badgeName' => $badgeName,
            'title' => $badgeName,
            'description' => $description,
            'image' => $image !== '' ? $image : null,
        ];
    }
}
