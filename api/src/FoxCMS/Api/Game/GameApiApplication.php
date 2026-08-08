<?php

declare(strict_types=1);

namespace FoxCMS\Api\Game;

use FoxCMS\Api\Core\ApplicationContext;
use FoxCMS\Api\Core\DatabaseFactory;
use FoxCMS\Api\Core\HttpException;
use FoxCMS\Api\Core\JsonResponse;
use FoxCMS\Api\Core\Request;
use FoxCMS\Api\Core\RequestId;
use Throwable;

final class GameApiApplication
{
    private const CATALOG_MAX_BYTES = 16 * 1024 * 1024;
    private const EVENT_MAX_BYTES = 512 * 1024;
    private const PROTOCOL = 'fox-achievements-v1';

    public function __construct(
        private readonly ApplicationContext $context,
        private readonly Request $request,
    ) {
        $context->requireEngine(
            'classes/syslib/database.php',
            'classes/identity/Uuid.class.php',
            'classes/game/GameApiException.class.php',
            'classes/game/GameServerAuthenticator.class.php',
            'classes/game/GameAchievementCatalogService.class.php',
            'classes/services/AchievementPointExchangeService.class.php',
            'classes/game/GameAchievementEventService.class.php',
        );
    }

    public function run(): never
    {
        try {
            match ($this->request->apiRoute()) {
                '/game/achievements/catalog' => $this->catalog(),
                '/game/achievements/event' => $this->event(),
                '/game/achievements/player' => $this->player(),
                '/game/achievements/statistics' => $this->statistics(),
                default => throw new \GameApiException('route_not_found', 'API route not found.', 404),
            };
        } catch (\GameApiException $error) {
            JsonResponse::error(
                $error->errorCode(),
                $error->getMessage(),
                $error->statusCode(),
                headers: [
                    'X-Fox-Error-Code' => $error->errorCode(),
                    'X-Fox-Server-Time' => (string)time(),
                ],
            );
        } catch (HttpException $error) {
            JsonResponse::error($error->errorCode(), $error->getMessage(), $error->statusCode(), $error->details());
        } catch (Throwable $error) {
            $requestId = RequestId::create();
            \FoxCMS\Api\Core\FatalResponse::send(
                $error,
                $this->context,
                'service_unavailable',
                503,
                $requestId,
            );
        }
    }

    private function catalog(): never
    {
        $payload = $this->authenticatedJson(self::CATALOG_MAX_BYTES);
        [$serverId, $body] = $payload;
        try {
            $result = (new \GameAchievementCatalogService($this->database()))->synchronize($serverId, $body);
        } catch (Throwable $error) {
            $this->rethrowSchemaError($error);
        }

        JsonResponse::send([
            'protocol' => self::PROTOCOL,
            'operation' => 'catalog',
            'accepted' => true,
            'serverId' => $serverId,
            ...$result,
        ], headers: ['Cache-Control' => 'no-store, max-age=0', 'Pragma' => 'no-cache']);
    }

    private function event(): never
    {
        [$serverId, $payload] = $this->authenticatedJson(self::EVENT_MAX_BYTES);
        try {
            $result = (new \GameAchievementEventService($this->database()))->ingest($serverId, $payload);
        } catch (Throwable $error) {
            $this->rethrowSchemaError($error);
        }

        JsonResponse::send([
            'protocol' => self::PROTOCOL,
            'operation' => 'event',
            ...$result,
        ], headers: ['Cache-Control' => 'no-store, max-age=0', 'Pragma' => 'no-cache']);
    }

    private function player(): never
    {
        $this->request->requireMethod('GET', 'HEAD');
        $database = $this->database();
        $playerUuid = (new PlayerIdentityResolver($database))->resolve(
            $this->request->query('uuid'),
            $this->request->query('login'),
        );
        $serverId = trim((string)$this->request->query('serverId'));

        try {
            $result = (new \GameAchievementEventService($database))->playerAchievements(
                $playerUuid,
                $serverId !== '' ? $serverId : null,
            );
        } catch (Throwable $error) {
            $this->rethrowSchemaError($error);
        }

        JsonResponse::send([
            'playerUuid' => $playerUuid,
            ...$result,
        ], headers: ['Cache-Control' => 'no-store, max-age=0', 'Pragma' => 'no-cache']);
    }

    private function statistics(): never
    {
        $this->request->requireMethod('GET', 'HEAD');
        $serverId = trim((string)$this->request->query('serverId'));
        try {
            $result = (new \GameAchievementEventService($this->database()))->achievementStatistics(
                $serverId !== '' ? $serverId : null,
            );
        } catch (Throwable $error) {
            $this->rethrowSchemaError($error);
        }

        JsonResponse::send($result, headers: [
            'Cache-Control' => 'public, max-age=30, stale-while-revalidate=120',
        ]);
    }

    /** @return array{0: string, 1: array<string, mixed>} */
    private function authenticatedJson(int $maximumBytes): array
    {
        $this->request->requireMethod('POST');
        if ($this->request->contentType() !== 'application/json') {
            throw new \GameApiException('content_type_invalid', 'Требуется Content-Type: application/json.', 415);
        }
        $payload = $this->request->jsonObject($maximumBytes);
        $serverId = \GameServerAuthenticator::fromEnvironment()->authenticate(
            $this->request->method(),
            $this->request->requestPath(),
            $this->request->rawBody(),
            [
                'server' => $this->request->header('X-Fox-Server'),
                'timestamp' => $this->request->header('X-Fox-Timestamp'),
                'signature' => $this->request->header('X-Fox-Signature'),
            ],
        );
        return [$serverId, $payload];
    }

    private function database(): \db
    {
        return DatabaseFactory::create($this->context->config());
    }

    private function rethrowSchemaError(Throwable $error): never
    {
        if (\GameAchievementCatalogService::isSchemaMissing($error)) {
            $migration = \GameAchievementCatalogService::requiredMigration($error);
            JsonResponse::send([
                'error' => 'achievement_schema_missing',
                'message' => 'В подключенной к API базе отсутствует часть схемы достижений.',
                'migration' => $migration,
                'requiredMigrations' => [
                    '025_game_achievements.sql',
                    '026_game_achievement_category_labels.sql',
                    '027_game_achievement_points_economy.sql',
                    '028_game_achievement_category_label_cleanup.sql',
                ],
            ], 503, ['Cache-Control' => 'no-store, max-age=0']);
        }

        if (\GameAchievementCatalogService::isSchemaOutdated($error)) {
            $migration = \GameAchievementCatalogService::requiredMigration($error);
            JsonResponse::send([
                'error' => 'achievement_schema_outdated',
                'message' => 'Схема таблиц достижений не соответствует текущей версии FoxCMS.',
                'migration' => $migration,
                'requiredMigrations' => [
                    '025_game_achievements.sql',
                    '026_game_achievement_category_labels.sql',
                    '027_game_achievement_points_economy.sql',
                    '028_game_achievement_category_label_cleanup.sql',
                ],
            ], 503, ['Cache-Control' => 'no-store, max-age=0']);
        }

        throw $error;
    }
}
