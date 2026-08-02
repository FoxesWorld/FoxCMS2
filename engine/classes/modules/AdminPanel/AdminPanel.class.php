<?php

declare(strict_types=1);

define('ADMIN', true);

final class AdminPanel extends Module
{
    public function __construct($db, $logger, HttpRequest $request, UserSession $session, array $config = [])
    {
        if (!$request->has('admPanel')) {
            return;
        }

        $action = $request->string('admPanel');
        try {
            if (!in_array($action, ['fileUpload', 'uploadSlideImage', 'uploadServerImage'], true)) {
                CsrfToken::requireValid($request->csrfToken());
            }
            if (!$session->isAdmin()) {
                $this->respond([
                    'message' => 'Недостаточно прав для административной операции «' . $action . '».',
                    'type' => 'error',
                    'error' => [
                        'action' => $action,
                        'exception' => 'AuthorizationException',
                        'detail' => 'Текущая сессия не имеет административных прав.',
                    ],
                ], 403);
            }

            require_once __DIR__ . '/AdminOptions.class.php';
            $payload = $request->all();
            $payload['_upload'] = $request->file('file');
            $payload['_slideUpload'] = $request->file('image');
            $payload['_serverImageUpload'] = $request->file('image');
            new AdminOptions(
                $payload,
                $db,
                $session,
                $logger instanceof Logger ? $logger : null,
                $request,
                $config,
            );
        } catch (Throwable $error) {
            $requestId = $this->errorRequestId();
            $exception = $error::class;
            $detail = $this->exceptionDetail($error);
            if ($logger instanceof Logger) {
                try {
                    $logger->logError('Admin request bootstrap failed.', [
                        'requestId' => $requestId,
                        'action' => $action,
                        'exception' => $exception,
                        'message' => $detail,
                        'file' => $error->getFile(),
                        'line' => $error->getLine(),
                        'trace' => $error->getTraceAsString(),
                    ]);
                } catch (Throwable $loggingError) {
                    error_log('[FoxesCraft admin bootstrap][' . $requestId . '] Logger failed: '
                        . $loggingError::class . ': ' . $loggingError->getMessage());
                }
            }
            $this->respond([
                'message' => 'Ошибка операции «' . ($action !== '' ? $action : 'unknown') . '»: '
                    . $exception . ' — ' . $detail . ' Код события: ' . $requestId . '.',
                'type' => 'error',
                'requestId' => $requestId,
                'error' => [
                    'action' => $action !== '' ? $action : 'unknown',
                    'exception' => $exception,
                    'detail' => $detail,
                    'requestId' => $requestId,
                ],
            ], 500);
        }
    }

    private function errorRequestId(): string
    {
        try {
            return bin2hex(random_bytes(8));
        } catch (Throwable) {
            return substr(hash('sha256', uniqid('admin-bootstrap-error-', true)), 0, 16);
        }
    }

    private function exceptionDetail(Throwable $error): string
    {
        $detail = trim(str_replace(["\r", "\n", "\t"], ' ', $error->getMessage()));
        $detail = preg_replace('/\s+/u', ' ', $detail) ?? $detail;
        if ($detail === '') {
            $detail = 'Исключение не содержит текстового описания.';
        }
        return mb_substr($detail, 0, 3000, 'UTF-8');
    }

    private function respond(array $payload, int $status): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=UTF-8');
        header('Cache-Control: no-store');
        header('X-Content-Type-Options: nosniff');
        if (isset($payload['requestId']) && is_string($payload['requestId'])) {
            header('X-Request-ID: ' . $payload['requestId']);
        }
        die(json_encode(
            $payload,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE,
        ));
    }
}
