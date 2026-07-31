<?php

declare(strict_types=1);

define('profile', true);

final class User extends Module
{
    public function __construct($db, $logger, HttpRequest $request, UserSession $session, array $config = [])
    {
        if (!$request->has('user_doaction')) {
            return;
        }

        require_once __DIR__ . '/UserActions.class.php';
        new UserActions($db, $logger, $request, $session, $config);
    }
}
