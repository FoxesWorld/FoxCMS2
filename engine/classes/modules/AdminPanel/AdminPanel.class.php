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
        if (!in_array($action, ['fileUpload', 'uploadSlideImage'], true)) {
            CsrfToken::requireValid($request->csrfToken());
        }
        if (!$session->isAdmin()) {
            http_response_code(403);
            header('Content-Type: application/json; charset=UTF-8');
            die(json_encode(['message' => 'Недостаточно прав.', 'type' => 'error'], JSON_UNESCAPED_UNICODE));
        }

        require_once __DIR__ . '/AdminOptions.class.php';
        $payload = $request->all();
        $payload['_upload'] = $request->file('file');
        $payload['_slideUpload'] = $request->file('image');
        new AdminOptions(
            $payload,
            $db,
            $session,
            $logger instanceof Logger ? $logger : null,
            $request,
            $config,
        );
    }
}
