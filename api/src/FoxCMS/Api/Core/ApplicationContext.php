<?php

declare(strict_types=1);

namespace FoxCMS\Api\Core;

use FoxCMS\Shared\Environment\Environment;
use RuntimeException;

final class ApplicationContext
{
    /** @param array<string, mixed> $config */
    private function __construct(
        private readonly string $rootDirectory,
        private readonly array $config,
        private readonly \NetworkContext $network,
        private readonly Environment $environment,
    ) {
    }

    public static function boot(
        string $rootDirectory,
        bool $registerErrorHandler = true,
        bool $applySecurityHeaders = true,
    ): self {
        $rootDirectory = rtrim($rootDirectory, '/\\');
        require_once $rootDirectory . '/autoload.php';
        $environment = Environment::boot($rootDirectory);
        require_once $rootDirectory . '/engine/data/environment.php';
        if ($registerErrorHandler) {
            require_once $rootDirectory . '/engine/classes/support/RuntimeErrorHandler.class.php';
            \RuntimeErrorHandler::register($rootDirectory, false);
        }
        if ($registerErrorHandler) {
            \RuntimeErrorHandler::setDebug($environment->boolean('FOXESCRAFT_DEBUG', false));
        }

        if (!defined('FOXXEY')) {
            define('FOXXEY', true);
        }

        require_once $rootDirectory . '/engine/classes/http/NetworkContext.class.php';
        $trustedProxies = $environment->csv('FOXESCRAFT_TRUSTED_PROXIES');
        $network = \NetworkContext::fromGlobals($trustedProxies);
        $GLOBALS['foxNetworkContext'] = $network;

        require_once $rootDirectory . '/engine/data/const.php';
        $config = require $rootDirectory . '/engine/data/config.php';
        if (!is_array($config)) {
            throw new RuntimeException('FoxCMS configuration did not return an array.');
        }

        if ($applySecurityHeaders) {
            require_once $rootDirectory . '/engine/classes/http/SecurityHeaders.class.php';
            \SecurityHeaders::apply($network, false);
        }

        return new self($rootDirectory, $config, $network, $environment);
    }

    public function rootDirectory(): string
    {
        return $this->rootDirectory;
    }

    /** @return array<string, mixed> */
    public function config(): array
    {
        return $this->config;
    }

    public function network(): \NetworkContext
    {
        return $this->network;
    }

    public function environment(): Environment
    {
        return $this->environment;
    }


    public function requireEngine(string ...$relativeFiles): void
    {
        foreach ($relativeFiles as $relativeFile) {
            require_once $this->rootDirectory . '/engine/' . ltrim($relativeFile, '/\\');
        }
    }
}
