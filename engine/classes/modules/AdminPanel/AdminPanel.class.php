<?php

declare(strict_types=1);

define('ADMIN', true);

final class AdminPanel extends Module
{
    public function __construct(
        db $db,
        Logger $logger,
        HttpRequest $request,
        UserSession $session,
        array $config = [],
    ) {
        if (!$request->has('admPanel')) {
            return;
        }

        $action = $request->string('admPanel');
        RequestTelemetry::identify('admin.' . $action, [
            'component' => 'admin_panel',
            'action' => $action,
        ]);

        try {
            if (!in_array($action, ['fileUpload', 'uploadSlideImage', 'uploadServerImage', 'uploadSiteSocialImage'], true)) {
                CsrfToken::requireValid($request->csrfToken());
            }
            if (!$session->isAdmin()) {
                RequestTelemetry::rejectHttp(
                    'admin.operation.rejected',
                    403,
                    'Non-administrator attempted an administrative operation.',
                    ['action' => $action],
                );
                JsonResponse::error(
                    'Недостаточно прав для административной операции «' . $action . '».',
                    403,
                );
            }

            require_once __DIR__ . '/AdminFailurePresenter.class.php';
            require_once __DIR__ . '/AdminFileManager.class.php';
            require_once __DIR__ . '/AdminOptions.class.php';
            $payload = $request->all();
            $payload['_upload'] = $request->file('file');
            $payload['_slideUpload'] = $request->file('image');
            $payload['_serverImageUpload'] = $request->file('image');
            $payload['_siteSocialImageUpload'] = $request->file('image');
            new AdminOptions(
                $payload,
                $db,
                $session,
                $logger,
                $request,
                $config,
            );
        } catch (HttpException $error) {
            RequestTelemetry::rejectHttp(
                'admin.operation.rejected',
                $error->status(),
                $error->getMessage(),
                ['action' => $action],
            );
            require_once __DIR__ . '/AdminFailurePresenter.class.php';
            $requestId = RequestTelemetry::requestId();
            if ($requestId === '') {
                $requestId = ExceptionContext::requestId('admin-rejected');
            }
            JsonResponse::send(
                AdminFailurePresenter::payload($error, $action, $requestId),
                $error->status(),
                $error->headers(),
            );
        } catch (Throwable $error) {
            RequestTelemetry::failure(
                'admin.bootstrap.failed',
                $error,
                'Administrative request bootstrap failed.',
                ['action' => $action],
            );
            $requestId = RequestTelemetry::requestId();
            if ($requestId === '') {
                $requestId = ExceptionContext::requestId('admin-bootstrap');
            }
            require_once __DIR__ . '/AdminFailurePresenter.class.php';
            JsonResponse::send(
                AdminFailurePresenter::payload($error, $action, $requestId),
                AdminFailurePresenter::status($error),
            );
        }
    }
}
