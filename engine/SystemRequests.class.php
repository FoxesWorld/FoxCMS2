<?php

declare(strict_types=1);

use FoxCMS\Engine\System\SystemRequestRouterFactory;
use FoxCMS\Shared\Routing\ActionDispatcher;

/**
 * Backward-compatible sysRequest HTTP facade.
 *
 * Domain/transport handlers live under FoxCMS\Engine\System. This class owns
 * only compatibility routing, method policy, telemetry and the fatal boundary.
 */
final class SystemRequests
{
    private const REQUEST_HEADER = 'sysRequest';

    private ActionDispatcher $router;

    public function __construct(
        db $db,
        Logger $logger,
        private HttpRequest $request,
        UserSession $userSession,
        array $config = [],
    ) {
        $this->router = (new SystemRequestRouterFactory())->create(
            $db,
            $logger,
            $request,
            $userSession,
            $config,
        );
    }

    public function requestListener(): void
    {
        $action = $this->request->string(self::REQUEST_HEADER);
        if ($action === '') {
            return;
        }

        $metadata = $this->router->metadata($action);
        RequestTelemetry::identify('system_requests.' . $action, [
            'component' => 'system_requests',
            'action' => $action,
            'handler' => (string)($metadata['handler'] ?? 'unresolved'),
            'moduleName' => 'SystemRequests',
        ]);

        if (!$this->request->isPost()) {
            RequestTelemetry::rejectHttp(
                'system_request.rejected',
                405,
                'System request used an unsupported HTTP method.',
                ['action' => $action],
            );
            JsonResponse::error('Method not allowed.', 405, ['Allow' => 'POST']);
        }
        if (!$this->router->has($action)) {
            RequestTelemetry::rejectHttp(
                'system_request.rejected',
                400,
                'Unknown system request action.',
                ['action' => $action],
            );
            JsonResponse::error('Unknown system request.', 400);
        }

        try {
            $this->router->dispatch($action);
        } catch (HttpException $error) {
            RequestTelemetry::rejectHttp(
                'system_request.rejected',
                $error->status(),
                $error->getMessage(),
                ['action' => $action],
            );
            JsonResponse::error($error->getMessage(), $error->status(), $error->headers());
        } catch (DomainException | InvalidArgumentException $error) {
            RequestTelemetry::rejectHttp(
                'system_request.rejected',
                400,
                $error->getMessage(),
                ['action' => $action],
            );
            JsonResponse::error($error->getMessage(), 400);
        } catch (Throwable $error) {
            RequestTelemetry::failure(
                'system_request.failed',
                $error,
                'System request failed unexpectedly.',
                ['action' => $action],
            );
            $requestId = RequestTelemetry::requestId();
            if ($requestId === '') {
                $requestId = ExceptionContext::requestId('system-request');
            }
            JsonResponse::send(
                \FoxCMS\Shared\Error\ThrowableDiagnostic::payload(
                    $error,
                    $requestId,
                    ROOT_DIR,
                    false,
                    ['type' => 'error', 'error' => 'system_request_failed', 'action' => $action],
                ),
                500,
            );
        }
    }
}
