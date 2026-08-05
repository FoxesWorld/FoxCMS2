<?php

declare(strict_types=1);

final class GameServerAuthenticator
{
    /** @param array<string,string> $serverKeys */
    public function __construct(
        private array $serverKeys,
        private int $toleranceSeconds = 300,
    ) {
        $this->toleranceSeconds = max(30, min(1800, $toleranceSeconds));
    }

    public static function fromEnvironment(): self
    {
        $encoded = trim((string)(foxEnv('FOXESCRAFT_GAME_SERVER_KEYS_JSON', '') ?? ''));
        if ($encoded === '') {
            throw new GameApiException(
                'game_server_auth_unconfigured',
                'Ключи игровых серверов не настроены.',
                503,
            );
        }

        try {
            $decoded = json_decode($encoded, true, 32, JSON_THROW_ON_ERROR);
        } catch (JsonException $error) {
            throw new GameApiException(
                'game_server_auth_invalid_config',
                'Конфигурация ключей игровых серверов повреждена.',
                503,
                $error,
            );
        }
        if (!is_array($decoded) || array_is_list($decoded)) {
            throw new GameApiException(
                'game_server_auth_invalid_config',
                'Конфигурация ключей игровых серверов должна быть JSON-объектом.',
                503,
            );
        }

        $keys = [];
        foreach ($decoded as $serverId => $secret) {
            $serverId = trim((string)$serverId);
            $secret = trim((string)$secret);
            if (preg_match('/^[a-z0-9][a-z0-9._-]{2,99}$/D', $serverId) !== 1) {
                throw new GameApiException(
                    'game_server_auth_invalid_config',
                    'В конфигурации указан некорректный идентификатор игрового сервера.',
                    503,
                );
            }
            if (str_starts_with($secret, 'base64:')) {
                $decodedSecret = base64_decode(substr($secret, 7), true);
                if (!is_string($decodedSecret)) {
                    throw new GameApiException(
                        'game_server_auth_invalid_config',
                        'Один из ключей игрового сервера имеет некорректный base64-формат.',
                        503,
                    );
                }
                $secret = $decodedSecret;
            }
            if (strlen($secret) < 32) {
                throw new GameApiException(
                    'game_server_auth_invalid_config',
                    'Ключ каждого игрового сервера должен содержать не менее 32 байт.',
                    503,
                );
            }
            $keys[$serverId] = $secret;
        }
        if ($keys === []) {
            throw new GameApiException(
                'game_server_auth_unconfigured',
                'Не настроено ни одного игрового сервера.',
                503,
            );
        }

        return new self(
            $keys,
            foxEnvInt('FOXESCRAFT_GAME_HMAC_TOLERANCE_SECONDS', 300),
        );
    }

    /**
     * @param array<string,string> $headers
     */
    public function authenticate(
        string $method,
        string $path,
        string $body,
        array $headers,
        ?int $now = null,
    ): string {
        $serverId = trim((string)($headers['server'] ?? ''));
        $timestampText = trim((string)($headers['timestamp'] ?? ''));
        $signature = strtolower(trim((string)($headers['signature'] ?? '')));
        if (str_starts_with($signature, 'sha256=')) {
            $signature = substr($signature, 7);
        }

        if (
            preg_match('/^[a-z0-9][a-z0-9._-]{2,99}$/D', $serverId) !== 1
            || preg_match('/^[0-9]{10,13}$/D', $timestampText) !== 1
            || preg_match('/^[a-f0-9]{64}$/D', $signature) !== 1
        ) {
            throw new GameApiException(
                'game_server_auth_required',
                'Запрос не содержит корректную подпись игрового сервера.',
                401,
            );
        }

        $secret = $this->serverKeys[$serverId] ?? null;
        if (!is_string($secret)) {
            throw new GameApiException(
                'game_server_unknown',
                'Игровой сервер не зарегистрирован.',
                401,
            );
        }

        $timestamp = (int)$timestampText;
        if ($timestamp > 9_999_999_999) {
            $timestamp = (int)floor($timestamp / 1000);
        }
        $now ??= time();
        if (abs($now - $timestamp) > $this->toleranceSeconds) {
            throw new GameApiException(
                'game_server_timestamp_rejected',
                'Временная метка запроса вышла за допустимое окно.',
                401,
            );
        }

        $method = strtoupper(trim($method));
        $path = trim($path);
        if ($method === '' || $path === '' || $path[0] !== '/') {
            throw new GameApiException(
                'game_server_canonical_request_invalid',
                'Не удалось сформировать каноническое представление запроса.',
                400,
            );
        }
        $canonical = $timestampText . "\n"
            . $method . "\n"
            . $path . "\n"
            . hash('sha256', $body);
        $expected = hash_hmac('sha256', $canonical, $secret);
        if (!hash_equals($expected, $signature)) {
            throw new GameApiException(
                'game_server_signature_rejected',
                'Подпись игрового сервера недействительна.',
                401,
            );
        }

        return $serverId;
    }
}
