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

        CsrfToken::requireValid($request->csrfToken());
        if (!$session->isAdmin()) {
            http_response_code(403);
            header('Content-Type: application/json; charset=UTF-8');
            die(json_encode(['message' => 'Недостаточно прав.', 'type' => 'error'], JSON_UNESCAPED_UNICODE));
        }

        require_once __DIR__ . '/AdminOptions.class.php';
        new AdminOptions(
            $request->all(),
            $db,
            $session,
            $logger instanceof Logger ? $logger : null,
        );
    }
}
