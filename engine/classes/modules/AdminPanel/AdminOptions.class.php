<?php

declare(strict_types=1);

if (!defined('ADMIN')) {
    die();
}

use FoxCMS\Engine\Admin\AdminActionRouterFactory;
use FoxCMS\Shared\Routing\ActionDispatcher;

/**
 * Backward-compatible admPanel facade.
 *
 * Authorization and the HTTP failure boundary live in AdminPanel. This class
 * only composes and dispatches registered administrative use-cases.
 */
final class AdminOptions
{
    private ActionDispatcher $router;

    public function __construct(
        array $request,
        db $db,
        UserSession $session,
        Logger $logger,
        ?HttpRequest $httpRequest = null,
        array $config = [],
    ) {
        if (!$httpRequest instanceof HttpRequest) {
            throw new RuntimeException('Admin uploads require the original HTTP request.');
        }
        $action = (string)($request['admPanel'] ?? '');
        $responder = new AdminResponder($action);
        $this->router = (new AdminActionRouterFactory())->create(
            $request,
            $db,
            $session,
            $logger,
            $httpRequest,
            $config,
            $responder,
        );
    }

    public function dispatch(string $action): void
    {
        $metadata = $this->router->metadata($action);
        RequestTelemetry::identify('admin.' . $action, [
            'component' => 'admin_panel',
            'action' => $action,
            'handler' => (string)($metadata['handler'] ?? 'unresolved'),
            'moduleName' => 'AdminPanel',
        ]);

        if (!$this->router->has($action)) {
            throw new HttpException('Неизвестная административная операция.', 400);
        }
        $this->router->dispatch($action);
    }
}
