<?php

declare(strict_types=1);

namespace FoxCMS\Api\Launcher;

use FoxCMS\Api\Core\HttpException;
use FoxCMS\Api\Core\Request;
use FoxCMS\Shared\Environment\Environment;

final class BridgeAuthenticator
{
    private const TOKEN_HEADER = 'X-Kaylas-Bridge-Token';
    private const MINIMUM_TOKEN_BYTES = 32;

    public function __construct(private readonly Environment $environment)
    {
    }

    public function authenticate(Request $request): void
    {
        $configured = trim((string)($this->environment->string(
            'FOXESCRAFT_LAUNCHER_BRIDGE_TOKEN',
            '',
        ) ?? ''));
        if (strlen($configured) < self::MINIMUM_TOKEN_BYTES) {
            throw new HttpException(503, 'bridge_not_configured', 'Launcher bridge token is not configured.');
        }
        $provided = $request->header(self::TOKEN_HEADER);
        if ($provided === '' || !hash_equals($configured, $provided)) {
            throw new HttpException(403, 'bridge_forbidden', 'Invalid launcher bridge credentials.');
        }
    }
}
