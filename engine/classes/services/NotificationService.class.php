<?php

declare(strict_types=1);

final class NotificationService
{
    private const SEVERITIES = ['info', 'success', 'warning', 'security'];

    public function __construct(private db $db)
    {
    }

    public static function isSchemaMissing(Throwable $error): bool
    {
        do {
            $message = strtolower($error->getMessage());
            if (
                str_contains($message, 'sqlstate 42s02')
                || str_contains($message, "table 'usernotifications' doesn't exist")
                || str_contains($message, "table `usernotifications` doesn't exist")
                || (str_contains($message, '1146') && str_contains($message, 'usernotifications'))
            ) {
                return true;
            }
            $error = $error->getPrevious();
        } while ($error instanceof Throwable);

        return false;
    }

    public function countUnread(string $userUuid): int
    {
        $userUuid = $this->normalizeUserUuid($userUuid);
        $statement = $this->db->prepare(
            'SELECT COUNT(*) FROM `userNotifications` WHERE `userUuid` = :userUuid AND `readAt` IS NULL'
        );
        $statement->execute([':userUuid' => $userUuid]);
        return max(0, (int)$statement->fetchColumn());
    }

    /** @return array{notifications:list<array<string,mixed>>,unreadCount:int,hasMore:bool,nextBeforeId:int} */
    public function pageForUser(string $userUuid, int $limit = 20, int $beforeId = 0): array
    {
        $userUuid = $this->normalizeUserUuid($userUuid);
        $limit = max(1, min(50, $limit));
        $beforeId = max(0, $beforeId);
        $parameters = [':userUuid' => $userUuid];
        $where = '`userUuid` = :userUuid';
        if ($beforeId > 0) {
            $where .= ' AND `id` < :beforeId';
            $parameters[':beforeId'] = $beforeId;
        }

        $statement = $this->db->prepare(
            'SELECT `id`, `notificationType`, `severity`, `title`, `message`, `actionUrl`, '
            . '`payload`, `createdAt`, `readAt` FROM `userNotifications` '
            . 'WHERE ' . $where . ' ORDER BY `id` DESC LIMIT ' . ($limit + 1)
        );
        $statement->execute($parameters);
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $hasMore = count($rows) > $limit;
        if ($hasMore) {
            array_pop($rows);
        }
        $notifications = array_values(array_map(
            fn (array $row): array => $this->normalizeRow($row),
            $rows,
        ));
        $last = $notifications !== [] ? $notifications[array_key_last($notifications)] : null;

        return [
            'notifications' => $notifications,
            'unreadCount' => $this->countUnread($userUuid),
            'hasMore' => $hasMore,
            'nextBeforeId' => is_array($last) ? (int)($last['id'] ?? 0) : 0,
        ];
    }

    public function markRead(string $userUuid, int $notificationId): bool
    {
        $userUuid = $this->normalizeUserUuid($userUuid);
        if ($notificationId <= 0) {
            throw new InvalidArgumentException('Некорректный идентификатор уведомления.');
        }
        $statement = $this->db->prepare(
            'UPDATE `userNotifications` SET `readAt` = :readAt '
            . 'WHERE `id` = :id AND `userUuid` = :userUuid AND `readAt` IS NULL'
        );
        $statement->execute([
            ':readAt' => time(),
            ':id' => $notificationId,
            ':userUuid' => $userUuid,
        ]);
        return $statement->rowCount() === 1;
    }

    public function markAllRead(string $userUuid): int
    {
        $userUuid = $this->normalizeUserUuid($userUuid);
        $statement = $this->db->prepare(
            'UPDATE `userNotifications` SET `readAt` = :readAt '
            . 'WHERE `userUuid` = :userUuid AND `readAt` IS NULL'
        );
        $statement->execute([':readAt' => time(), ':userUuid' => $userUuid]);
        return max(0, $statement->rowCount());
    }

    /** @param array<string,mixed> $context */
    public function notifyLogin(string $userUuid, array $context, int $loginAt): int
    {
        $location = trim((string)($context['locationLabel'] ?? 'регион не определён'));
        $device = trim((string)($context['deviceLabel'] ?? 'неизвестное устройство'));
        $ip = trim((string)($context['ip'] ?? ''));
        $details = [$location, $device];
        if ($ip !== '') {
            $details[] = 'IP ' . $ip;
        }
        return $this->create(
            $userUuid,
            'security.login',
            'security',
            'Новый вход в аккаунт',
            'Зафиксирован вход: ' . implode(' · ', $details) . '.',
            $context,
            null,
            'login:' . hash('sha256', $userUuid . '|' . $loginAt . '|' . $ip . '|' . (string)($context['userAgent'] ?? '')),
            $loginAt,
        );
    }

    public function notifyWelcomeBack(string $userUuid, string $login, int $absenceSeconds, int $loginAt): int
    {
        $days = max(1, (int)floor($absenceSeconds / 86400));
        return $this->create(
            $userUuid,
            'account.welcome_back',
            'success',
            'С возвращением, ' . trim($login),
            'Вы снова с нами после ' . $days . ' дн. отсутствия. Рады видеть вас в FoxesCraft.',
            ['absenceDays' => $days],
            '/',
            'welcome-back:' . $loginAt,
            $loginAt,
        );
    }

    /** @param array<string,mixed> $context */
    public function notifyPasswordChanged(string $userUuid, string $source, array $context = []): int
    {
        $sourceLabel = match ($source) {
            'recovery' => 'через восстановление доступа',
            'administrator' => 'администратором',
            default => 'в настройках профиля',
        };
        $ip = trim((string)($context['ip'] ?? ''));
        $message = 'Пароль аккаунта изменён ' . $sourceLabel . '.';
        if ($ip !== '') {
            $message .= ' IP: ' . $ip . '.';
        }
        return $this->create(
            $userUuid,
            'security.password_changed',
            'security',
            'Пароль изменён',
            $message,
            array_merge($context, ['source' => $source]),
            '/settings/profile',
        );
    }

    /** @param array<string,mixed> $badge */
    public function notifyBadgeAwarded(string $userUuid, array $badge, string $source): int
    {
        $name = trim((string)($badge['badgeName'] ?? $badge['title'] ?? 'Новый бейдж'));
        $id = max(0, (int)($badge['id'] ?? 0));
        return $this->create(
            $userUuid,
            'achievement.badge_awarded',
            'success',
            'Получен новый бейдж',
            'Вам выдан бейдж «' . $name . '».',
            [
                'badgeId' => $id,
                'badgeName' => $name,
                'description' => trim((string)($badge['description'] ?? '')),
                'image' => trim((string)($badge['image'] ?? $badge['img'] ?? '')),
                'source' => $source,
            ],
            '/badges',
        );
    }

    /** @param array<string,mixed> $badge */
    public function notifyBadgeRevoked(
        string $userUuid,
        array $badge,
        string $reason,
        string $source,
    ): int {
        $name = trim((string)($badge['badgeName'] ?? $badge['title'] ?? 'Бейдж'));
        if ($name === '') {
            $name = 'Бейдж';
        }
        $id = max(0, (int)($badge['id'] ?? 0));
        $reason = preg_replace('/\s+/u', ' ', trim($reason));
        $reason = is_string($reason) ? $this->truncate($reason, 500) : '';
        if ($reason === '') {
            throw new InvalidArgumentException('Причина отзыва бейджа обязательна.');
        }

        return $this->create(
            $userUuid,
            'achievement.badge_revoked',
            'warning',
            'Бейдж отозван',
            'У вас отозван бейдж «' . $name . '». Причина: ' . $reason,
            [
                'badgeId' => $id,
                'badgeName' => $name,
                'description' => trim((string)($badge['description'] ?? '')),
                'image' => trim((string)($badge['image'] ?? $badge['img'] ?? '')),
                'source' => $source,
                'reason' => $reason,
            ],
            '/badges',
        );
    }

    /** @param array<string,mixed> $reward @param array<string,mixed>|null $badge @param array<string,mixed>|null $currency */
    public function notifyRewardClaimed(
        string $userUuid,
        array $reward,
        ?array $badge,
        ?array $currency,
    ): int {
        $name = trim((string)($reward['rewardName'] ?? $reward['title'] ?? 'Награда'));
        $parts = ['Награда «' . $name . '» получена'];
        if ($badge !== null && trim((string)($badge['badgeName'] ?? '')) !== '') {
            $parts[] = 'бейдж «' . trim((string)$badge['badgeName']) . '»';
        }
        if ($currency !== null && (int)($currency['amount'] ?? 0) > 0) {
            $currencyName = trim((string)($currency['currencyName'] ?? $currency['currencyCode'] ?? 'ед.'));
            $parts[] = (int)$currency['amount'] . ' ' . $currencyName;
        }
        return $this->create(
            $userUuid,
            'achievement.reward_claimed',
            'success',
            'Получена награда',
            implode(' · ', $parts) . '.',
            ['reward' => $reward, 'badge' => $badge, 'currency' => $currency],
            '/badges',
        );
    }

    /** @param array<string,mixed> $payload */
    public function create(
        string $userUuid,
        string $notificationType,
        string $severity,
        string $title,
        string $message,
        array $payload = [],
        ?string $actionUrl = null,
        ?string $dedupeKey = null,
        ?int $createdAt = null,
    ): int {
        $userUuid = $this->normalizeUserUuid($userUuid);
        $notificationType = trim($notificationType);
        if (preg_match('/^[a-z][a-z0-9._-]{2,63}$/D', $notificationType) !== 1) {
            throw new InvalidArgumentException('Некорректный тип уведомления.');
        }
        if (!in_array($severity, self::SEVERITIES, true)) {
            throw new InvalidArgumentException('Некорректная важность уведомления.');
        }
        $title = $this->truncate(trim($title), 160);
        $message = $this->truncate(trim($message), 1000);
        if ($title === '' || $message === '') {
            throw new InvalidArgumentException('Уведомление должно содержать заголовок и текст.');
        }
        if ($actionUrl !== null) {
            $actionUrl = trim($actionUrl);
            if ($actionUrl === '' || !str_starts_with($actionUrl, '/')) {
                $actionUrl = null;
            } else {
                $actionUrl = $this->truncate($actionUrl, 512);
            }
        }
        if ($dedupeKey !== null) {
            $dedupeKey = $this->truncate(trim($dedupeKey), 191);
            if ($dedupeKey === '') {
                $dedupeKey = null;
            }
        }
        $payloadJson = json_encode(
            $payload,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
        );
        $statement = $this->db->prepare(
            'INSERT INTO `userNotifications` '
            . '(`userUuid`, `notificationType`, `severity`, `title`, `message`, `actionUrl`, `payload`, `dedupeKey`, `createdAt`) '
            . 'VALUES (:userUuid, :notificationType, :severity, :title, :message, :actionUrl, :payload, :dedupeKey, :createdAt) '
            . 'ON DUPLICATE KEY UPDATE `id` = LAST_INSERT_ID(`id`)'
        );
        $statement->execute([
            ':userUuid' => $userUuid,
            ':notificationType' => $notificationType,
            ':severity' => $severity,
            ':title' => $title,
            ':message' => $message,
            ':actionUrl' => $actionUrl,
            ':payload' => $payloadJson,
            ':dedupeKey' => $dedupeKey,
            ':createdAt' => max(0, $createdAt ?? time()),
        ]);
        $id = (int)$this->db->lastInsertId();
        if ($id <= 0) {
            throw new RuntimeException('Database did not return the notification identifier.');
        }
        return $id;
    }

    /** @return array<string,mixed> */
    private function normalizeRow(array $row): array
    {
        $payload = [];
        $rawPayload = $row['payload'] ?? null;
        if (is_string($rawPayload) && trim($rawPayload) !== '') {
            $decoded = json_decode($rawPayload, true);
            if (is_array($decoded)) {
                $payload = $decoded;
            }
        }
        $readAt = isset($row['readAt']) && $row['readAt'] !== null ? max(0, (int)$row['readAt']) : null;
        return [
            'id' => max(0, (int)($row['id'] ?? 0)),
            'type' => trim((string)($row['notificationType'] ?? 'system.notice')),
            'severity' => trim((string)($row['severity'] ?? 'info')),
            'title' => trim((string)($row['title'] ?? '')),
            'message' => trim((string)($row['message'] ?? '')),
            'actionUrl' => isset($row['actionUrl']) ? trim((string)$row['actionUrl']) : null,
            'payload' => $payload,
            'createdAt' => max(0, (int)($row['createdAt'] ?? 0)),
            'readAt' => $readAt,
            'unread' => $readAt === null,
        ];
    }

    private function normalizeUserUuid(string $userUuid): string
    {
        try {
            return Uuid::normalize($userUuid);
        } catch (InvalidArgumentException $error) {
            throw new InvalidArgumentException('Некорректный UUID получателя уведомления.', 0, $error);
        }
    }

    private function truncate(string $value, int $length): string
    {
        return function_exists('mb_substr') ? mb_substr($value, 0, $length, 'UTF-8') : substr($value, 0, $length);
    }
}
