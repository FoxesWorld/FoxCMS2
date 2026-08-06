<?php

declare(strict_types=1);

use FoxCMS\Engine\User\AuthenticatedUserGuard;
use FoxCMS\Engine\User\UserActionResponder;
use FoxCMS\Engine\User\UserBrowserSessionController;
use FoxCMS\Engine\User\UserNotificationController;
use FoxCMS\Engine\User\UserProfileQueryController;
use FoxCMS\Engine\User\UserRewardController;

if (!defined('profile')) {
    http_response_code(403);
    exit('{"message":"Profile module is unavailable","type":"error"}');
}

/**
 * Legacy transport facade for user_doaction. Business operations are owned by
 * focused namespaced controllers under FoxCMS\Engine\User.
 */
final class UserActions
{
    private const ACTION_HANDLERS = [
        'EditUser' => 'account.edit',
        'updateProfilePhoto' => 'account.photo',
        'getUserData' => 'profile.public',
        'getUserSettings' => 'profile.private',
        'getRewardOffer' => 'rewards.offer',
        'getBadgeOffer' => 'rewards.offer',
        'claimReward' => 'rewards.claim',
        'claimBadge' => 'rewards.claim',
        'getNotifications' => 'notifications.list',
        'markNotificationRead' => 'notifications.mark_read',
        'markAllNotificationsRead' => 'notifications.mark_all_read',
        'getActiveSessions' => 'sessions.list',
        'revokeActiveSession' => 'sessions.revoke',
        'lostpassword' => 'password.request_reset',
        'resetpassword' => 'password.reset',
    ];

    private UserActionResponder $responder;
    private UserRewardController $rewards;
    private UserBrowserSessionController $browserSessions;
    private UserNotificationController $notifications;
    private UserProfileQueryController $profiles;

    public function __construct(
        private db $db,
        private Logger $logger,
        private HttpRequest $request,
        private UserSession $session,
        private array $config = [],
    ) {
        $action = $request->string('user_doaction');
        $this->responder = new UserActionResponder($action);
        $guard = new AuthenticatedUserGuard($session, $this->responder);
        $this->rewards = new UserRewardController(
            $db,
            $logger,
            $request,
            $session,
            $guard,
            $this->responder,
        );
        $this->browserSessions = new UserBrowserSessionController(
            $db,
            $logger,
            $request,
            $session,
            $config,
            $guard,
            $this->responder,
        );
        $this->notifications = new UserNotificationController(
            $db,
            $logger,
            $request,
            $guard,
            $this->responder,
        );
        $this->profiles = new UserProfileQueryController(
            $db,
            $request,
            $session,
            $this->responder,
        );
        $this->dispatch($action);
    }

    private function dispatch(string $action): void
    {
        RequestTelemetry::identify('user_settings.' . $action, [
            'component' => 'user_settings',
            'action' => $action,
            'handler' => self::ACTION_HANDLERS[$action] ?? 'unresolved',
            'moduleName' => 'UserSettings',
        ]);

        match ($action) {
            'EditUser' => $this->editUser(),
            'updateProfilePhoto' => $this->updateProfilePhoto(),
            'getUserData' => $this->profiles->getUserData(),
            'getUserSettings' => $this->profiles->getUserSettings(),
            'getRewardOffer' => $this->rewards->getRewardOffer(),
            'getBadgeOffer' => $this->rewards->getRewardOffer(),
            'claimReward' => $this->rewards->claimReward(),
            'claimBadge' => $this->rewards->claimReward(),
            'getNotifications' => $this->notifications->getNotifications(),
            'markNotificationRead' => $this->notifications->markNotificationRead(),
            'markAllNotificationsRead' => $this->notifications->markAllNotificationsRead(),
            'getActiveSessions' => $this->browserSessions->getActiveSessions(),
            'revokeActiveSession' => $this->browserSessions->revokeActiveSession(),
            'lostpassword' => $this->lostPassword(),
            'resetpassword' => $this->resetPassword(),
            default => $this->responder->send([
                'message' => 'Unknown user request.',
                'type' => 'error',
            ], 400),
        };
    }

    private function editUser(): never
    {
        require_once __DIR__ . '/actions/EditUser.class.php';
        (new EditUser(
            $this->request,
            $this->db,
            $this->logger,
            $this->session,
        ))->update();
    }

    private function updateProfilePhoto(): never
    {
        require_once __DIR__ . '/actions/updateProfilePhoto.class.php';
        (new UpdateProfilePhoto($this->db, $this->request, $this->session, $this->logger))->upload();
    }

    private function lostPassword(): never
    {
        require_once __DIR__ . '/actions/lostpassword.class.php';
        (new LostPassword($this->db, $this->logger, $this->config))->resetPass(
            $this->request->string('email'),
        );
    }

    private function resetPassword(): never
    {
        require_once __DIR__ . '/actions/resetpassword.class.php';
        (new ResetPassword($this->db, $this->logger, $this->request))->reset(
            $this->request->string('token'),
            $this->request->string('new_password'),
            $this->request->string('confirm_password'),
        );
    }
}
