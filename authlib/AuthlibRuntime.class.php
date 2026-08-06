<?php

declare(strict_types=1);

use FoxCMS\Shared\Environment\Environment;
use FoxCMS\Shared\Http\ResponseHeaders;

final class AuthlibRuntime
{
    private function __construct(
        private string $rootDirectory,
        private array $config,
        private NetworkContext $network,
        private HttpRequest $request,
        private db $database,
        private Logger $logger,
    ) {
    }

    public static function boot(): self
    {
        $rootDirectory = dirname(__DIR__);

        require_once $rootDirectory . '/autoload.php';
        require_once $rootDirectory . '/engine/autoload.php';
        $environment = Environment::boot($rootDirectory);
        require_once $rootDirectory . '/engine/data/environment.php';
        RuntimeErrorHandler::register($rootDirectory, false);
        RuntimeErrorHandler::setDebug($environment->boolean('FOXESCRAFT_DEBUG', false));

        if (!defined('FOXXEY')) {
            define('FOXXEY', true);
        }

        $trustedProxies = $environment->csv('FOXESCRAFT_TRUSTED_PROXIES');
        $network = NetworkContext::fromGlobals($trustedProxies);
        $GLOBALS['foxNetworkContext'] = $network;

        require_once $rootDirectory . '/engine/data/const.php';
        $config = require $rootDirectory . '/engine/data/config.php';
        if (!is_array($config)) {
            throw new RuntimeException('Authlib configuration did not return an array.');
        }
        date_default_timezone_set((string)($config['other']['timezone'] ?? 'Europe/Amsterdam'));

        SecurityHeaders::apply($network, false);
        require_once $rootDirectory . '/engine/classes/syslib/database.php';
        require_once $rootDirectory . '/engine/classes/syslib/syslog';

        $databaseConfig = is_array($config['database'] ?? null) ? $config['database'] : [];
        $database = new db(
            (string)($databaseConfig['dbUser'] ?? ''),
            (string)($databaseConfig['dbPass'] ?? ''),
            (string)($databaseConfig['dbName'] ?? ''),
            (string)($databaseConfig['dbHost'] ?? '127.0.0.1'),
            (int)($databaseConfig['dbPort'] ?? 3306),
            (string)($databaseConfig['dbCharset'] ?? 'utf8mb4'),
            (int)($databaseConfig['connectTimeout'] ?? 5),
        );

        return new self(
            $rootDirectory,
            $config,
            $network,
            HttpRequest::fromGlobals($network),
            $database,
            new Logger('authlib'),
        );
    }

    public function rootDirectory(): string
    {
        return $this->rootDirectory;
    }

    public function config(): array
    {
        return $this->config;
    }

    public function request(): HttpRequest
    {
        return $this->request;
    }

    public function database(): db
    {
        return $this->database;
    }

    public function logger(): Logger
    {
        return $this->logger;
    }

    public function publicBaseUrl(): string
    {
        $configured = rtrim((string)($this->config['environment']['publicBaseUrl'] ?? ''), '/');
        return $configured !== '' ? $configured : $this->network->origin();
    }

    /** @return array<string, mixed> */
    public function jsonBody(int $maximumBytes = 32768): array
    {
        $decoded = $this->jsonDocument($maximumBytes);
        if (!is_array($decoded) || array_is_list($decoded)) {
            $this->problem('JSON object expected.', 400);
        }
        return $decoded;
    }

    /** @return list<mixed> */
    public function jsonList(int $maximumBytes = 32768, int $maximumItems = 100): array
    {
        $decoded = $this->jsonDocument($maximumBytes);
        if (!is_array($decoded) || !array_is_list($decoded)) {
            $this->problem('JSON array expected.', 400);
        }
        if (count($decoded) > $maximumItems) {
            $this->problem('Too many items in the request.', 413);
        }
        return $decoded;
    }

    private function jsonDocument(int $maximumBytes): mixed
    {
        if ($this->request->method() !== 'POST') {
            $this->problem('Method not allowed.', 405, ['Allow' => 'POST']);
        }

        $contentType = strtolower($this->request->header('Content-Type'));
        if (!str_starts_with($contentType, 'application/json')) {
            $this->problem('Content-Type must be application/json.', 415);
        }

        $contentLength = filter_var(
            $this->request->header('Content-Length'),
            FILTER_VALIDATE_INT,
        );
        if ($contentLength !== false && $contentLength > $maximumBytes) {
            $this->problem('Request body is too large.', 413);
        }

        $stream = fopen('php://input', 'rb');
        if ($stream === false) {
            throw new RuntimeException('Unable to open the request body stream.');
        }
        $raw = stream_get_contents($stream, $maximumBytes + 1);
        fclose($stream);
        if (!is_string($raw)) {
            throw new RuntimeException('Unable to read the request body.');
        }
        if (strlen($raw) > $maximumBytes) {
            $this->problem('Request body is too large.', 413);
        }

        try {
            return json_decode($raw, true, 16, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            $this->problem('Malformed JSON request.', 400);
        }
    }

    public function logEvent(string $event, array $context = []): void
    {
        $safe = [];
        foreach ($context as $key => $value) {
            if (!is_string($key) || preg_match('/token|secret|password|server.?id/i', $key) === 1) {
                continue;
            }
            if (is_scalar($value) || $value === null) {
                $safe[$key] = $value;
            }
        }
        $encoded = $safe === []
            ? ''
            : ' ' . json_encode($safe, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $this->logger->logInfo($event . $encoded);
    }

    public function json(array $payload, int $status = 200, array $headers = []): never
    {
        try {
            $encoded = json_encode(
                $payload,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR,
            );
        } catch (JsonException $error) {
            throw new RuntimeException('Unable to encode authlib response.', 0, $error);
        }

        ResponseHeaders::begin(
            $status,
            'application/json; charset=UTF-8',
            array_merge(['Cache-Control' => 'no-store, max-age=0'], $headers),
        );
        echo $encoded;
        exit;
    }

    public function noContent(int $status = 204): never
    {
        ResponseHeaders::begin(
            $status,
            'application/json; charset=UTF-8',
            ['Cache-Control' => 'no-store, max-age=0'],
        );
        exit;
    }

    public function problem(string $message, int $status, array $headers = []): never
    {
        $this->json([
            'error' => $this->errorName($status),
            'errorMessage' => $message,
        ], $status, $headers);
    }

    private function errorName(int $status): string
    {
        return match ($status) {
            400 => 'BadRequest',
            401 => 'Unauthorized',
            403 => 'Forbidden',
            404 => 'NotFound',
            405 => 'MethodNotAllowed',
            413 => 'PayloadTooLarge',
            415 => 'UnsupportedMediaType',
            429 => 'TooManyRequests',
            default => 'InternalServerError',
        };
    }
}
