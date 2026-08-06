<?php

declare(strict_types=1);

namespace FoxCMS\Engine\User;

use \CsrfToken;
use \InvalidArgumentException;
use \NotificationService;
use \Throwable;

final class UserNotificationController
{
    public function __construct(
        private readonly \db $db,
        private readonly \Logger $logger,
        private readonly \HttpRequest $request,
        private readonly AuthenticatedUserGuard $guard,
        private readonly UserActionResponder $responder,
    ) {
    }

    public function getNotifications(): never
    {
        $userUuid = $this->guard->uuid();
        try {
            $page = (new NotificationService($this->db))->pageForUser(
                $userUuid,
                $this->request->integer('limit', 20),
                $this->request->integer('beforeId'),
            );
            $this->responder->send($page);
        } catch (InvalidArgumentException $error) {
            $this->responder->send(['message' => $error->getMessage(), 'type' => 'error'], 400);
        } catch (Throwable $error) {
            if (NotificationService::isSchemaMissing($error)) {
                $this->responder->send([
                    'message' => 'Цент уведомлений не инициализирован. Примените миграцию 023_user_notifications.sql.',
                    'type' => 'error',
                    'migration' => '023_user_notifications.sql',
                ], 503);
            }
            $this->logger->exception(
                'notifications.list.failed',
                $error,
                'User notification inbox could not be loaded.',
                ['component' => 'notifications', 'operation' => 'list', 'targetUserUuid' => $userUuid],
            );
            $this->responder->fatal($error, 500, ['operation' => 'list_notifications']);
        }
    }

    public function markNotificationRead(): never
    {
        $userUuid = $this->guard->uuid();
        CsrfToken::requireValid($this->request->csrfToken());
        $notificationId = $this->request->integer('notificationId');
        try {
            $service = new NotificationService($this->db);
            $updated = $service->markRead($userUuid, $notificationId);
            $this->responder->send([
                'updated' => $updated,
                'notificationId' => $notificationId,
                'unreadCount' => $service->countUnread($userUuid),
            ]);
        } catch (InvalidArgumentException $error) {
            $this->responder->send(['message' => $error->getMessage(), 'type' => 'error'], 400);
        } catch (Throwable $error) {
            if (NotificationService::isSchemaMissing($error)) {
                $this->responder->send([
                    'message' => 'Цент уведомлений не инициализирован. Примените миграцию 023_user_notifications.sql.',
                    'type' => 'error',
                    'migration' => '023_user_notifications.sql',
                ], 503);
            }
            $this->logger->exception(
                'notifications.mark_read.failed',
                $error,
                'Notification could not be marked as read.',
                [
                    'component' => 'notifications',
                    'operation' => 'mark_read',
                    'targetUserUuid' => $userUuid,
                    'notificationId' => $notificationId,
                ],
            );
            $this->responder->fatal($error, 500, ['operation' => 'mark_notification_read']);
        }
    }

    public function markAllNotificationsRead(): never
    {
        $userUuid = $this->guard->uuid();
        CsrfToken::requireValid($this->request->csrfToken());
        try {
            $service = new NotificationService($this->db);
            $updatedCount = $service->markAllRead($userUuid);
            $this->responder->send(['updatedCount' => $updatedCount, 'unreadCount' => 0]);
        } catch (Throwable $error) {
            if (NotificationService::isSchemaMissing($error)) {
                $this->responder->send([
                    'message' => 'Цент уведомлений не инициализирован. Примените миграцию 023_user_notifications.sql.',
                    'type' => 'error',
                    'migration' => '023_user_notifications.sql',
                ], 503);
            }
            $this->logger->exception(
                'notifications.mark_all_read.failed',
                $error,
                'Notifications could not be marked as read.',
                ['component' => 'notifications', 'operation' => 'mark_all_read', 'targetUserUuid' => $userUuid],
            );
            $this->responder->fatal($error, 500, ['operation' => 'mark_all_notifications_read']);
        }
    }
}
