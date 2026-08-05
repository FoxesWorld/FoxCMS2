<?php

declare(strict_types=1);

final class GameAchievementCatalogService
{
    private const MAX_ACHIEVEMENTS = 10000;
    private const MAX_ICON_BYTES = 262144;
    private const ICON_MIME_TYPES = ['image/png'];

    public function __construct(private db $db)
    {
    }

    public static function isSchemaMissing(Throwable $error): bool
    {
        return self::matchesDatabaseError($error, '42S02', 1146);
    }

    public static function isSchemaOutdated(Throwable $error): bool
    {
        return self::matchesDatabaseError($error, '42S22', 1054);
    }

    private static function matchesDatabaseError(Throwable $error, string $sqlState, int $driverCode): bool
    {
        do {
            if ($error instanceof PDOException) {
                $errorInfo = is_array($error->errorInfo ?? null) ? $error->errorInfo : [];
                $actualState = strtoupper((string)($errorInfo[0] ?? $error->getCode()));
                $actualDriverCode = (int)($errorInfo[1] ?? 0);
                if ($actualState === $sqlState || $actualDriverCode === $driverCode) {
                    return true;
                }
            }

            $message = strtoupper($error->getMessage());
            if (
                str_contains($message, 'SQLSTATE[' . $sqlState . ']')
                || str_contains($message, 'SQLSTATE ' . $sqlState)
            ) {
                return true;
            }

            $error = $error->getPrevious();
        } while ($error instanceof Throwable);

        return false;
    }

    /**
     * @param array<string,mixed> $payload
     * @return array{catalogRevision:string,received:int,activeCount:int,disabledCount:int}
     */
    public function synchronize(string $serverId, array $payload): array
    {
        $serverId = $this->serverId($serverId);
        $locale = $this->locale($payload['locale'] ?? 'ru_RU');
        $rawAchievements = $payload['achievements'] ?? null;
        if (!is_array($rawAchievements) || !array_is_list($rawAchievements)) {
            throw new GameApiException(
                'achievement_catalog_invalid',
                'Поле achievements должно быть массивом.',
                422,
            );
        }
        if (count($rawAchievements) > self::MAX_ACHIEVEMENTS) {
            throw new GameApiException(
                'achievement_catalog_too_large',
                'Каталог достижений превышает допустимый размер.',
                413,
            );
        }

        $definitions = [];
        foreach ($rawAchievements as $index => $rawAchievement) {
            if (!is_array($rawAchievement) || array_is_list($rawAchievement)) {
                throw new GameApiException(
                    'achievement_catalog_invalid',
                    'Элемент achievements[' . $index . '] должен быть объектом.',
                    422,
                );
            }
            $definition = $this->normalizeDefinition($rawAchievement, $index);
            $key = $definition['achievementKey'];
            if (isset($definitions[$key])) {
                throw new GameApiException(
                    'achievement_catalog_duplicate_key',
                    'Каталог содержит повторяющийся ключ достижения: ' . $key,
                    422,
                );
            }
            $definitions[$key] = $definition;
        }
        ksort($definitions, SORT_STRING);
        $catalogRevision = hash('sha256', json_encode(
            array_values(array_map(
                static fn (array $definition): array => [
                    'achievementKey' => $definition['achievementKey'],
                    'definitionHash' => $definition['definitionHash'],
                ],
                $definitions,
            )),
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
        ));
        $now = time();

        return $this->db->transactional(function () use (
            $serverId,
            $definitions,
            $catalogRevision,
            $locale,
            $now,
        ): array {
            $statement = $this->db->prepare(
                'INSERT INTO `gameAchievements` '
                . '(`serverId`, `gameCode`, `achievementKey`, `achievementType`, `parentKey`, `title`, '
                . '`description`, `frameType`, `category`, `iconBase64`, `iconMime`, `iconItem`, '
                . '`iconComponents`, `criteria`, `requirements`, `points`, `hidden`, `announceToChat`, '
                . '`showToast`, `enabled`, `definitionHash`, `catalogRevision`, `createdAt`, `updatedAt`, `lastSeenAt`) '
                . 'VALUES (:serverId, :gameCode, :achievementKey, :achievementType, :parentKey, :title, '
                . ':description, :frameType, :category, :iconBase64, :iconMime, :iconItem, '
                . ':iconComponents, :criteria, :requirements, :points, :hidden, :announceToChat, '
                . ':showToast, 1, :definitionHash, :catalogRevision, :createdAt, :updatedAt, :lastSeenAt) '
                . 'ON DUPLICATE KEY UPDATE '
                . '`achievementType` = VALUES(`achievementType`), `parentKey` = VALUES(`parentKey`), '
                . '`title` = VALUES(`title`), `description` = VALUES(`description`), '
                . '`frameType` = VALUES(`frameType`), `category` = VALUES(`category`), '
                . '`iconBase64` = VALUES(`iconBase64`), `iconMime` = VALUES(`iconMime`), '
                . '`iconItem` = VALUES(`iconItem`), `iconComponents` = VALUES(`iconComponents`), '
                . '`criteria` = VALUES(`criteria`), `requirements` = VALUES(`requirements`), '
                . '`points` = VALUES(`points`), `hidden` = VALUES(`hidden`), '
                . '`announceToChat` = VALUES(`announceToChat`), `showToast` = VALUES(`showToast`), '
                . '`enabled` = 1, `definitionHash` = VALUES(`definitionHash`), '
                . '`catalogRevision` = VALUES(`catalogRevision`), `updatedAt` = VALUES(`updatedAt`), '
                . '`lastSeenAt` = VALUES(`lastSeenAt`)'
            );

            foreach ($definitions as $definition) {
                $statement->execute([
                    ':serverId' => $serverId,
                    ':gameCode' => 'minecraft',
                    ':achievementKey' => $definition['achievementKey'],
                    ':achievementType' => $definition['achievementType'],
                    ':parentKey' => $definition['parentKey'],
                    ':title' => $definition['title'],
                    ':description' => $definition['description'],
                    ':frameType' => $definition['frameType'],
                    ':category' => $definition['category'],
                    ':iconBase64' => $definition['iconBase64'],
                    ':iconMime' => $definition['iconMime'],
                    ':iconItem' => $definition['iconItem'],
                    ':iconComponents' => $definition['iconComponentsJson'],
                    ':criteria' => $definition['criteriaJson'],
                    ':requirements' => $definition['requirementsJson'],
                    ':points' => $definition['points'],
                    ':hidden' => $definition['hidden'] ? 1 : 0,
                    ':announceToChat' => $definition['announceToChat'] ? 1 : 0,
                    ':showToast' => $definition['showToast'] ? 1 : 0,
                    ':definitionHash' => $definition['definitionHash'],
                    ':catalogRevision' => $catalogRevision,
                    ':createdAt' => $now,
                    ':updatedAt' => $now,
                    ':lastSeenAt' => $now,
                ]);
            }

            $disable = $this->db->prepare(
                'UPDATE `gameAchievements` SET `enabled` = 0, `updatedAt` = :updatedAt '
                . 'WHERE `serverId` = :serverId AND `gameCode` = \'minecraft\' '
                . 'AND `catalogRevision` <> :catalogRevision AND `enabled` = 1'
            );
            $disable->execute([
                ':updatedAt' => $now,
                ':serverId' => $serverId,
                ':catalogRevision' => $catalogRevision,
            ]);

            $counts = $this->db->prepare(
                'SELECT COUNT(*) AS `persistedCount`, '
                . 'SUM(CASE WHEN `enabled` = 1 THEN 1 ELSE 0 END) AS `activeCount` '
                . 'FROM `gameAchievements` WHERE `serverId` = :serverId AND `gameCode` = \'minecraft\''
            );
            $counts->execute([':serverId' => $serverId]);
            $stored = $counts->fetch(PDO::FETCH_ASSOC);
            $databaseName = (string)($this->db->getValue('SELECT DATABASE()') ?? '');

            return [
                'catalogRevision' => $catalogRevision,
                'locale' => $locale,
                'received' => count($definitions),
                'persistedCount' => max(0, (int)($stored['persistedCount'] ?? 0)),
                'activeCount' => max(0, (int)($stored['activeCount'] ?? 0)),
                'disabledCount' => max(0, $disable->rowCount()),
                'databaseName' => $databaseName,
            ];
        });
    }

    /** @return array<string,mixed> */
    private function normalizeDefinition(array $value, int $index): array
    {
        $key = $this->identifier($value['achievementKey'] ?? null, 'achievementKey', $index);
        $type = $this->shortToken($value['achievementType'] ?? 'advancement', 'achievementType', 32);
        $parent = $value['parentKey'] ?? null;
        $parentKey = $parent === null || trim((string)$parent) === ''
            ? null
            : $this->identifier($parent, 'parentKey', $index);
        $title = $this->text($value['title'] ?? '', 'title', 190, false);
        $description = $this->text($value['description'] ?? '', 'description', 4000, true);
        $frameType = $this->shortToken($value['frameType'] ?? 'task', 'frameType', 24);
        $category = $this->text($value['category'] ?? $this->categoryFromKey($key), 'category', 100, false);
        $iconMime = strtolower(trim((string)($value['iconMime'] ?? 'image/png')));
        if (!in_array($iconMime, self::ICON_MIME_TYPES, true)) {
            throw new GameApiException(
                'achievement_icon_invalid',
                'Достижение ' . $key . ' использует неподдерживаемый MIME-тип иконки.',
                422,
            );
        }
        $iconBase64 = preg_replace('/\s+/', '', trim((string)($value['iconBase64'] ?? ''))) ?? '';
        if (str_starts_with($iconBase64, 'data:')) {
            $separator = strpos($iconBase64, ',');
            $iconBase64 = $separator === false ? '' : substr($iconBase64, $separator + 1);
        }
        $iconBytes = base64_decode($iconBase64, true);
        if (!is_string($iconBytes) || $iconBytes === '' || strlen($iconBytes) > self::MAX_ICON_BYTES) {
            throw new GameApiException(
                'achievement_icon_invalid',
                'Иконка достижения ' . $key . ' должна быть корректным Base64-изображением размером до 256 KiB.',
                422,
            );
        }
        if (!str_starts_with($iconBytes, "\x89PNG\r\n\x1a\n") || strlen($iconBytes) < 24) {
            throw new GameApiException(
                'achievement_icon_invalid',
                'Иконка достижения ' . $key . ' объявлена как PNG, но сигнатура файла не совпадает.',
                422,
            );
        }
        $dimensions = unpack('Nwidth/Nheight', substr($iconBytes, 16, 8));
        $width = is_array($dimensions) ? (int)($dimensions['width'] ?? 0) : 0;
        $height = is_array($dimensions) ? (int)($dimensions['height'] ?? 0) : 0;
        if ($width < 1 || $height < 1 || $width > 512 || $height > 512) {
            throw new GameApiException(
                'achievement_icon_invalid',
                'Размер PNG-иконки достижения ' . $key . ' должен быть от 1×1 до 512×512 пикселей.',
                422,
            );
        }

        $iconItem = trim((string)($value['iconItem'] ?? ''));
        if ($iconItem !== '' && preg_match('~^[a-z0-9_.-]+:[a-z0-9_./-]+$~D', $iconItem) !== 1) {
            throw new GameApiException('achievement_icon_item_invalid', 'Некорректный iconItem для ' . $key . '.', 422);
        }
        $iconComponents = $this->jsonObject($value['iconComponents'] ?? [], 'iconComponents', $key);
        $criteria = $this->jsonObject($value['criteria'] ?? [], 'criteria', $key);
        $requirements = $value['requirements'] ?? [];
        if (!is_array($requirements) || !array_is_list($requirements)) {
            throw new GameApiException('achievement_requirements_invalid', 'Некорректные requirements для ' . $key . '.', 422);
        }
        foreach ($requirements as $group) {
            if (!is_array($group) || !array_is_list($group)) {
                throw new GameApiException('achievement_requirements_invalid', 'Некорректная группа requirements для ' . $key . '.', 422);
            }
            foreach ($group as $criterion) {
                if (!is_string($criterion) || $criterion === '' || strlen($criterion) > 190) {
                    throw new GameApiException('achievement_requirements_invalid', 'Некорректный criterion в requirements для ' . $key . '.', 422);
                }
            }
        }
        $points = max(0, min(1000000, (int)($value['points'] ?? 0)));

        $canonical = [
            'achievementKey' => $key,
            'achievementType' => $type,
            'parentKey' => $parentKey,
            'title' => $title,
            'description' => $description,
            'frameType' => $frameType,
            'category' => $category,
            'iconMime' => $iconMime,
            'iconBase64' => $iconBase64,
            'iconItem' => $iconItem,
            'iconComponents' => $iconComponents,
            'criteria' => $criteria,
            'requirements' => $requirements,
            'points' => $points,
            'hidden' => ($value['hidden'] ?? false) === true,
            'announceToChat' => ($value['announceToChat'] ?? false) === true,
            'showToast' => ($value['showToast'] ?? true) !== false,
        ];
        $definitionHash = hash('sha256', json_encode(
            $canonical,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
        ));

        return $canonical + [
            'iconComponentsJson' => json_encode($iconComponents, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
            'criteriaJson' => json_encode($criteria, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
            'requirementsJson' => json_encode($requirements, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
            'definitionHash' => $definitionHash,
        ];
    }

    private function locale(mixed $value): string
    {
        $locale = trim((string)$value);
        if (preg_match('/^[a-z]{2}_[A-Z]{2}$/D', $locale) !== 1) {
            throw new GameApiException('achievement_locale_invalid', 'Некорректная локаль каталога достижений.', 422);
        }
        return $locale;
    }

    private function serverId(string $serverId): string
    {
        $serverId = trim($serverId);
        if (preg_match('/^[a-z0-9][a-z0-9._-]{2,99}$/D', $serverId) !== 1) {
            throw new GameApiException('game_server_invalid', 'Некорректный идентификатор игрового сервера.', 400);
        }
        return $serverId;
    }

    private function identifier(mixed $value, string $field, int $index): string
    {
        $identifier = trim((string)$value);
        if (preg_match('~^[a-z0-9_.-]+:[a-z0-9_./-]+$~D', $identifier) !== 1 || strlen($identifier) > 190) {
            throw new GameApiException(
                'achievement_identifier_invalid',
                'Некорректное поле ' . $field . ' в achievements[' . $index . '].',
                422,
            );
        }
        return $identifier;
    }

    private function shortToken(mixed $value, string $field, int $maximum): string
    {
        $token = strtolower(trim((string)$value));
        if (preg_match('/^[a-z][a-z0-9._-]*$/D', $token) !== 1 || strlen($token) > $maximum) {
            throw new GameApiException('achievement_field_invalid', 'Некорректное поле ' . $field . '.', 422);
        }
        return $token;
    }

    private function text(mixed $value, string $field, int $maximum, bool $allowEmpty): string
    {
        $text = preg_replace('/\s+/u', ' ', trim((string)$value));
        $text = is_string($text) ? $text : '';
        $length = function_exists('mb_strlen')
            ? mb_strlen($text, 'UTF-8')
            : strlen($text);
        if ((!$allowEmpty && $text === '') || $length > $maximum) {
            throw new GameApiException('achievement_field_invalid', 'Некорректное поле ' . $field . '.', 422);
        }
        return $text;
    }

    /** @return array<string,mixed> */
    private function jsonObject(mixed $value, string $field, string $key): array
    {
        if (!is_array($value) || array_is_list($value)) {
            throw new GameApiException('achievement_field_invalid', 'Поле ' . $field . ' для ' . $key . ' должно быть объектом.', 422);
        }
        $encoded = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        if (strlen($encoded) > 65535) {
            throw new GameApiException('achievement_field_invalid', 'Поле ' . $field . ' для ' . $key . ' слишком велико.', 422);
        }
        return $value;
    }

    private function categoryFromKey(string $key): string
    {
        $path = explode(':', $key, 2)[1] ?? 'general';
        $segment = explode('/', $path, 2)[0] ?? 'general';
        return $segment !== '' ? $segment : 'general';
    }
}
