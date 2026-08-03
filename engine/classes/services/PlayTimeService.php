<?php

declare(strict_types=1);

final class PlayTimeService
{
    private const MAX_HEARTBEAT_SECONDS = 300;
    private const MAX_SERVERS_PER_USER = 128;

    public function __construct(
        private db $database,
        private Logger $logger,
    ) {
    }

    public function start(string $userUuid, string $serverName, string $uuid): never
    {
        RequestTelemetry::identify('playtime.start', ['component' => 'playtime']);
        [$userUuid, $serverName, $uuid] = $this->validateIdentity($userUuid, $serverName, $uuid);
        $result = $this->mutate($userUuid, function (array $state, int $now) use ($serverName, $uuid): array {
            $server = $this->normalizeServerState($state['servers'][$serverName] ?? []);

            if (($server['active'] ?? false) && ($server['uuid'] ?? '') !== $uuid) {
                $this->logger->deviation(
                    'playtime.session.replaced',
                    'active_session_uuid_changed',
                    'An active playtime session was replaced by a different session identifier.',
                    'warning',
                    ['activeSessionUuidChanged' => false],
                    ['activeSessionUuidChanged' => true],
                    ['component' => 'playtime', 'serverName' => $serverName],
                );
                $server = $this->accrue($server, $now);
            }

            $server['uuid'] = $uuid;
            $server['active'] = true;
            $server['lastStarted'] = $now;
            $server['lastHeartbeat'] = $now;
            $server['lastPlayed'] = $now;
            $state['servers'][$serverName] = $server;
            $this->assertServerLimit($state);

            return [$state, [
                'status' => 'active',
                'serverName' => $serverName,
                'totalTime' => $server['totalTime'],
                'lastPlayed' => $now,
            ]];
        });
        $this->respond($result + ['type' => 'success', 'message' => 'Игровая сессия начата.']);
    }

    public function heartbeat(string $userUuid, string $uuid): never
    {
        RequestTelemetry::identify('playtime.heartbeat', ['component' => 'playtime']);
        $userUuid = Uuid::normalize($userUuid);
        $uuid = $this->validateUuid($uuid);
        $result = $this->mutate($userUuid, function (array $state, int $now) use ($uuid): array {
            foreach ($state['servers'] as $serverName => $server) {
                $server = $this->normalizeServerState(is_array($server) ? $server : []);
                if (($server['uuid'] ?? '') !== $uuid || !($server['active'] ?? false)) {
                    continue;
                }

                $server = $this->accrue($server, $now);
                $server['lastHeartbeat'] = $now;
                $server['lastPlayed'] = $now;
                $state['servers'][$serverName] = $server;

                return [$state, [
                    'status' => 'active',
                    'serverName' => (string)$serverName,
                    'totalTime' => $server['totalTime'],
                    'lastPlayed' => $now,
                ]];
            }
            throw new DomainException('Активная игровая сессия не найдена.');
        });
        $this->respond($result + ['type' => 'success', 'message' => 'Игровая сессия обновлена.']);
    }

    public function status(string $userUuid, string $serverName, string $uuid): never
    {
        RequestTelemetry::identify('playtime.status', ['component' => 'playtime']);
        [$userUuid, $serverName, $uuid] = $this->validateIdentity($userUuid, $serverName, $uuid);
        try {
            $state = $this->loadState($userUuid, false);
            $server = $this->normalizeServerState($state['servers'][$serverName] ?? []);
            $active = ($server['active'] ?? false)
                && hash_equals((string)($server['uuid'] ?? ''), $uuid)
                && CURRENT_TIME - (int)($server['lastHeartbeat'] ?? 0) <= self::MAX_HEARTBEAT_SECONDS;

            $this->respond([
                'type' => 'success',
                'status' => $active ? 'active' : 'inactive',
                'serverName' => $serverName,
                'totalTime' => (int)$server['totalTime'],
                'lastPlayed' => (int)$server['lastPlayed'],
            ]);
        } catch (DomainException $exception) {
            $this->respond(['type' => 'error', 'message' => $exception->getMessage()], 404);
        } catch (Throwable $exception) {
            $this->logger->exception(
                'playtime.status.failed',
                $exception,
                'Playtime status lookup failed.',
                ['component' => 'playtime'],
            );
            $this->respond(['type' => 'error', 'message' => 'Не удалось получить игровую статистику.'], 500);
        }
    }

    public function finish(string $userUuid, string $serverName, string $uuid): never
    {
        RequestTelemetry::identify('playtime.finish', ['component' => 'playtime']);
        [$userUuid, $serverName, $uuid] = $this->validateIdentity($userUuid, $serverName, $uuid);
        $result = $this->mutate($userUuid, function (array $state, int $now) use ($serverName, $uuid): array {
            $server = $this->normalizeServerState($state['servers'][$serverName] ?? []);
            if (!($server['active'] ?? false) || !hash_equals((string)($server['uuid'] ?? ''), $uuid)) {
                throw new DomainException('Активная игровая сессия не найдена.');
            }

            $server = $this->accrue($server, $now);
            $server['active'] = false;
            $server['lastPlayed'] = $now;
            unset($server['uuid'], $server['lastHeartbeat']);
            $state['servers'][$serverName] = $server;

            return [$state, [
                'status' => 'finished',
                'serverName' => $serverName,
                'totalTime' => $server['totalTime'],
                'lastPlayed' => $now,
            ]];
        });
        $this->respond($result + ['type' => 'success', 'message' => 'Игровая сессия завершена.']);
    }

    private function mutate(string $userUuid, callable $callback): array
    {
        try {
            return $this->database->transactional(function () use ($userUuid, $callback): array {
                $state = $this->loadState($userUuid, true);
                [$state, $response] = $callback($state, CURRENT_TIME);
                $encoded = json_encode(
                    $state,
                    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
                );
                if (strlen($encoded) > 1_048_576) {
                    throw new DomainException('Игровая статистика превысила допустимый размер.');
                }

                $placeholders = [];
                $parameters = [':state' => $encoded];
                foreach (Uuid::databaseCandidates($userUuid) as $index => $candidate) {
                    $placeholder = ':userUuid_' . $index;
                    $placeholders[] = $placeholder;
                    $parameters[$placeholder] = $candidate;
                }
                $statement = $this->database->prepare(
                    'UPDATE `users` SET `serversOnline` = :state '
                    . 'WHERE `uuid` IN (' . implode(', ', $placeholders) . ')'
                );
                $statement->execute($parameters);
                if ($statement->rowCount() > 1) {
                    throw new RuntimeException('Unexpected playtime update cardinality.');
                }
                return $response;
            });
        } catch (DomainException $exception) {
            $this->respond(['type' => 'error', 'message' => $exception->getMessage()], 400);
        } catch (Throwable $exception) {
            $this->logger->exception(
                'playtime.mutation.failed',
                $exception,
                'Playtime mutation failed.',
                ['component' => 'playtime', 'targetUserUuid' => $userUuid],
            );
            $this->respond(['type' => 'error', 'message' => 'Не удалось сохранить игровую статистику.'], 500);
        }
    }

    private function loadState(string $userUuid, bool $forUpdate): array
    {
        $placeholders = [];
        $parameters = [];
        foreach (Uuid::databaseCandidates($userUuid) as $index => $candidate) {
            $placeholder = ':userUuid_' . $index;
            $placeholders[] = $placeholder;
            $parameters[$placeholder] = $candidate;
        }
        $sql = 'SELECT `serversOnline` FROM `users` '
            . 'WHERE `uuid` IN (' . implode(', ', $placeholders) . ') LIMIT 1'
            . ($forUpdate ? ' FOR UPDATE' : '');
        $statement = $this->database->prepare($sql);
        $statement->execute($parameters);
        $raw = $statement->fetchColumn();
        if ($raw === false) {
            throw new DomainException('Пользователь не найден.');
        }

        try {
            $decoded = json_decode((string)$raw, true, 16, JSON_THROW_ON_ERROR);
        } catch (JsonException $error) {
            $this->logger->deviation(
                'playtime.state.invalid_json',
                'stored_playtime_json_invalid',
                'Stored playtime state is not valid JSON and was reset to an empty state.',
                'warning',
                ['stateFormat' => 'valid_json'],
                ['stateFormat' => 'invalid_json'],
                ['component' => 'playtime', 'targetUserUuid' => $userUuid],
            );
            $decoded = [];
        }
        if (!is_array($decoded)) {
            $this->logger->deviation(
                'playtime.state.invalid_shape',
                'stored_playtime_shape_invalid',
                'Stored playtime state has an invalid root type and was reset.',
                'warning',
                ['rootType' => 'array'],
                ['rootType' => get_debug_type($decoded)],
                ['component' => 'playtime', 'targetUserUuid' => $userUuid],
            );
            $decoded = [];
        }

        if (isset($decoded['servers']) && is_array($decoded['servers'])) {
            $servers = $decoded['servers'];
        } else {
            $servers = [];
            foreach ($decoded as $entry) {
                if (!is_array($entry) || empty($entry['serverName'])) {
                    continue;
                }
                $name = (string)$entry['serverName'];
                unset($entry['serverName']);
                $servers[$name] = $entry;
            }
        }
        $decoded['servers'] = $servers;
        $this->assertServerLimit($decoded);
        return $decoded;
    }

    private function normalizeServerState(array $server): array
    {
        return array_merge($server, [
            'totalTime' => max(0, (int)($server['totalTime'] ?? 0)),
            'lastPlayed' => max(0, (int)($server['lastPlayed'] ?? 0)),
            'active' => (bool)($server['active'] ?? false),
        ]);
    }

    private function accrue(array $server, int $now): array
    {
        $lastHeartbeat = max(0, (int)($server['lastHeartbeat'] ?? $now));
        $rawElapsed = max(0, $now - $lastHeartbeat);
        if ($rawElapsed > self::MAX_HEARTBEAT_SECONDS) {
            $this->logger->deviation(
                'playtime.heartbeat.late',
                'heartbeat_interval_exceeded',
                'Playtime heartbeat interval exceeded the normal maximum and was capped.',
                'notice',
                ['maximumHeartbeatSeconds' => self::MAX_HEARTBEAT_SECONDS],
                ['heartbeatIntervalSeconds' => $rawElapsed],
                ['component' => 'playtime'],
            );
        }
        $elapsed = min(self::MAX_HEARTBEAT_SECONDS, $rawElapsed);
        $server['totalTime'] = max(0, (int)($server['totalTime'] ?? 0)) + $elapsed;
        return $server;
    }

    private function validateIdentity(string $userUuid, string $serverName, string $uuid): array
    {
        return [
            Uuid::normalize($userUuid),
            $this->validateServerName($serverName),
            $this->validateUuid($uuid),
        ];
    }

    private function validateServerName(string $serverName): string
    {
        $serverName = trim($serverName);
        if (preg_match('/^[\p{L}\p{N}_ .-]{1,64}$/uD', $serverName) !== 1) {
            throw new DomainException('Некорректное имя сервера.');
        }
        return $serverName;
    }

    private function validateUuid(string $uuid): string
    {
        $uuid = trim($uuid);
        if (preg_match('/^[A-Za-z0-9_-]{8,128}$/D', $uuid) !== 1) {
            throw new DomainException('Некорректный идентификатор игровой сессии.');
        }
        return $uuid;
    }

    private function assertServerLimit(array $state): void
    {
        $servers = is_array($state['servers'] ?? null) ? $state['servers'] : [];
        if (count($servers) > self::MAX_SERVERS_PER_USER) {
            throw new DomainException('Превышено допустимое число серверов в статистике.');
        }
    }

    private function respond(array $payload, int $status = 200): never
    {
        if ($status >= 400) {
            RequestTelemetry::rejectHttp(
                'playtime.operation.rejected',
                $status,
                (string)($payload['message'] ?? 'Playtime operation was rejected.'),
            );
        }
        JsonResponse::send($payload, $status);
    }
}
