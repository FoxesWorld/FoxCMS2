<?php

declare(strict_types=1);

namespace FoxCMS\Engine\Admin;

final class AdminMailController
{
    public function __construct(
        private array $config,
        private \db $db,
        private \SiteSettingsRepository $repository,
        private \GroupRepository $groups,
        private \UserSession $session,
        private \Logger $logger,
        private \AdminRequestPayload $payload,
        private \AdminResponder $responder,
    ) {
    }

    public function settings(): never
    {
        $this->responder->send($this->publicState($this->currentState()));
    }

    public function save(): never
    {
        $entry = $this->payload->object('entry');
        $state = $this->currentState();
        $current = is_array($state['settings'] ?? null) ? $state['settings'] : [];
        $password = array_key_exists('smtpPassword', $entry)
            ? trim((string)$entry['smtpPassword'])
            : '';

        $patch = [
            'mailMethod' => (string)($entry['mailMethod'] ?? $current['mailMethod'] ?? 'smtp'),
            'mailFromAddress' => (string)($entry['mailFromAddress'] ?? $current['mailFromAddress'] ?? ''),
            'mailFromName' => (string)($entry['mailFromName'] ?? $current['mailFromName'] ?? 'FoxesCraft'),
            'smtpHost' => (string)($entry['smtpHost'] ?? $current['smtpHost'] ?? 'smtp.mail.ru'),
            'smtpPort' => $entry['smtpPort'] ?? $current['smtpPort'] ?? 465,
            'smtpSecurity' => (string)($entry['smtpSecurity'] ?? $current['smtpSecurity'] ?? 'ssl'),
            'smtpUsername' => (string)($entry['smtpUsername'] ?? $current['smtpUsername'] ?? ''),
            'smtpPassword' => $password !== ''
                ? $password
                : (string)($current['smtpPassword'] ?? ''),
        ];

        $saved = $this->repository->save(
            array_replace($current, $patch),
            $this->fallback(),
            $this->session->uuid(),
        );

        $this->logger->event(
            'admin.mail_settings.updated',
            'Mail settings updated.',
            [
                'component' => 'mail',
                'operation' => 'save',
                'fields' => array_values(array_diff(array_keys($entry), ['smtpPassword'])),
            ],
            'INFO',
            'success',
        );

        $this->responder->send(array_merge($this->publicState($saved), [
            'message' => 'Mail settings saved to the site configuration.',
            'type' => 'success',
        ]));
    }

    public function test(): never
    {
        $state = $this->currentState();
        $settings = is_array($state['settings'] ?? null) ? $state['settings'] : [];

        if (($settings['mailMethod'] ?? 'smtp') === 'smtp' && !defined('OPENSSL_ALGO_SHA1')) {
            $this->responder->send([
                'success' => false,
                'message' => 'PHP OpenSSL extension is not enabled.',
                'type' => 'error',
                'checkedAt' => gmdate('c'),
                'code' => 'tls_extension_missing',
                'hint' => $this->diagnosticHint('tls_extension_missing'),
            ], 500);
        }

        \UtilityLoader::load('FoxMail', '1.0.0');
        $mailer = new \FoxMail(true, $settings, true);
        $diagnostic = $mailer->testConnection();

        if (!($diagnostic['success'] ?? false)) {
            $this->logger->event(
                'admin.mail.smtp_test_failed',
                'SMTP diagnostic failed.',
                [
                    'component' => 'mail',
                    'operation' => 'smtp_test',
                    'host' => (string)($settings['smtpHost'] ?? ''),
                ],
                'WARNING',
                'failure',
            );
            $this->responder->send([
                'success' => false,
                'message' => (string)($diagnostic['message'] ?? 'SMTP diagnostic failed.'),
                'type' => 'error',
                'checkedAt' => gmdate('c'),
                'code' => (string)($diagnostic['code'] ?? 'smtp_failed'),
                'hint' => $this->diagnosticHint((string)($diagnostic['code'] ?? 'smtp_failed')),
                'detail' => (string)($diagnostic['detail'] ?? ''),
                'smtpCode' => (string)($diagnostic['smtpCode'] ?? ''),
                'smtpReply' => (string)($diagnostic['smtpReply'] ?? ''),
                'library' => (string)($diagnostic['library'] ?? ''),
            ], 400);
        }

        $entry = $this->payload->object('entry');
        $recipient = trim((string)($entry['recipient'] ?? ''));
        $sent = null;
        if ($recipient !== '') {
            if (filter_var($recipient, FILTER_VALIDATE_EMAIL) === false) {
                $this->responder->send([
                    'message' => 'Invalid test recipient.',
                    'type' => 'error',
                ], 400);
            }

            $sent = $mailer->send(
                $recipient,
                'FoxesCraft SMTP test',
                'smtp-test.html',
                [
                    'checkedAt' => gmdate('c'),
                    'host' => (string)($settings['smtpHost'] ?? ''),
                ],
            );

            if (!$sent) {
                $this->responder->send([
                    'success' => false,
                    'message' => $mailer->smtp_msg !== ''
                        ? $mailer->smtp_msg
                        : 'SMTP authenticated, but the test message could not be sent.',
                    'type' => 'error',
                    'checkedAt' => gmdate('c'),
                ], 400);
            }
        }

        $this->logger->event(
            'admin.mail.smtp_test_succeeded',
            'SMTP diagnostic succeeded.',
            [
                'component' => 'mail',
                'operation' => 'smtp_test',
                'host' => (string)($settings['smtpHost'] ?? ''),
                'testMessageSent' => $sent === true,
            ],
            'INFO',
            'success',
        );

        $this->responder->send([
            'success' => true,
            'message' => $sent === true
                ? 'SMTP authentication succeeded and the test message was sent.'
                : 'SMTP connection and authentication succeeded.',
            'type' => 'success',
            'checkedAt' => gmdate('c'),
            'testMessageSent' => $sent === true,
            'code' => 'ok',
            'library' => (string)($diagnostic['library'] ?? ''),
        ]);
    }

    public function audience(): never
    {
        $filter = $this->normalizeAudienceFilter($this->payload->object('entry'));
        $recipients = $this->resolveAudience($filter);
        $sendLimit = 250;

        $this->responder->send([
            'count' => count($recipients),
            'sendLimit' => $sendLimit,
            'tooLarge' => count($recipients) > $sendLimit,
            'sample' => array_slice($recipients, 0, 20),
            'groups' => $this->groups->all(),
            'statuses' => $this->audienceStatuses(),
        ]);
    }

    public function sendCampaign(): never
    {
        $entry = $this->payload->object('entry');
        $filter = $this->normalizeAudienceFilter(is_array($entry['filter'] ?? null) ? $entry['filter'] : []);
        $subject = trim((string)($entry['subject'] ?? ''));
        $body = trim((string)($entry['body'] ?? ''));
        $format = strtolower(trim((string)($entry['format'] ?? 'html')));
        $expectedCount = max(0, (int)($entry['expectedCount'] ?? 0));
        $confirmed = filter_var($entry['confirmed'] ?? false, FILTER_VALIDATE_BOOLEAN);

        if (!$confirmed) {
            $this->responder->send(['message' => 'Подтвердите аудиторию перед отправкой.', 'type' => 'error'], 422);
        }
        if ($subject === '' || strlen($subject) > 240 || preg_match('/[\r\n]/', $subject) === 1) {
            $this->responder->send(['message' => 'Укажите тему письма без переносов строк (до 240 символов).', 'type' => 'error'], 422);
        }
        if ($body === '' || strlen($body) > 100_000) {
            $this->responder->send(['message' => 'Укажите текст письма размером до 100 КБ.', 'type' => 'error'], 422);
        }
        if (!in_array($format, ['html', 'text'], true)) {
            $this->responder->send(['message' => 'Неизвестный формат письма.', 'type' => 'error'], 422);
        }

        $recipients = $this->resolveAudience($filter);
        $count = count($recipients);
        if ($count === 0) {
            $this->responder->send(['message' => 'По выбранному фильтру нет получателей с корректным email.', 'type' => 'error'], 422);
        }
        if ($count > 250) {
            $this->responder->send([
                'message' => 'Аудитория превышает лимит 250 получателей за одну отправку. Сузьте фильтр.',
                'type' => 'error',
                'count' => $count,
                'sendLimit' => 250,
            ], 422);
        }
        if ($expectedCount !== $count) {
            $this->responder->send([
                'message' => 'Состав аудитории изменился после предпросмотра. Обновите предпросмотр и подтвердите отправку снова.',
                'type' => 'error',
                'count' => $count,
            ], 409);
        }

        $state = $this->currentState();
        $settings = is_array($state['settings'] ?? null) ? $state['settings'] : [];
        \UtilityLoader::load('FoxMail', '1.0.0');
        $mailer = new \FoxMail($format === 'html', $settings);
        $mailer->keepalive = true;

        $sent = 0;
        $failed = 0;
        $failures = [];
        foreach ($recipients as $recipient) {
            $personalSubject = $this->mergeTags($subject, $recipient, false, $settings);
            $personalBody = $this->mergeTags($body, $recipient, $format === 'html', $settings);
            $ok = $mailer->sendContent(
                (string)$recipient['email'],
                $personalSubject,
                $personalBody,
                $format === 'html',
            );
            if ($ok) {
                $sent++;
                continue;
            }
            $failed++;
            if (count($failures) < 12) {
                $failures[] = [
                    'uuid' => (string)$recipient['uuid'],
                    'login' => (string)$recipient['login'],
                ];
            }
        }

        $this->logger->event(
            $failed === 0 ? 'admin.mail_campaign.sent' : 'admin.mail_campaign.partial_failure',
            $failed === 0 ? 'Administrative mail campaign sent.' : 'Administrative mail campaign completed with delivery failures.',
            [
                'component' => 'mail',
                'operation' => 'campaign',
                'actorUuid' => $this->session->uuid(),
                'recipientCount' => $count,
                'sentCount' => $sent,
                'failedCount' => $failed,
                'groupFilter' => $filter['groupTags'],
                'statusFilterCount' => count($filter['statuses']),
                'hasSearchFilter' => $filter['search'] !== '',
                'format' => $format,
            ],
            $failed === 0 ? 'INFO' : 'WARNING',
            $failed === 0 ? 'success' : 'partial_failure',
        );

        if ($sent === 0) {
            $this->responder->send([
                'success' => false,
                'message' => $mailer->smtp_msg !== '' ? $mailer->smtp_msg : 'Не удалось отправить письма выбранной аудитории.',
                'type' => 'error',
                'sent' => 0,
                'failed' => $failed,
                'total' => $count,
                'failures' => $failures,
            ], 502);
        }

        $this->responder->send([
            'success' => $failed === 0,
            'message' => $failed === 0
                ? 'Рассылка успешно отправлена.'
                : 'Рассылка завершена частично: некоторые письма не были доставлены SMTP-сервером.',
            'type' => $failed === 0 ? 'success' : 'warning',
            'sent' => $sent,
            'failed' => $failed,
            'total' => $count,
            'failures' => $failures,
        ]);
    }

    /** @param array<string,mixed> $input @return array{groupTags:list<string>,statuses:list<string>,search:string} */
    private function normalizeAudienceFilter(array $input): array
    {
        $knownGroups = [];
        foreach ($this->groups->all() as $group) {
            $knownGroups[(string)$group['groupTag']] = true;
        }
        $groupTags = [];
        foreach (is_array($input['groupTags'] ?? null) ? $input['groupTags'] : [] as $value) {
            $tag = \GroupRepository::normalizeTag($value, '');
            if ($tag === '' || !isset($knownGroups[$tag])) {
                $this->responder->send(['message' => 'Фильтр содержит неизвестную группу пользователей.', 'type' => 'error'], 422);
            }
            $groupTags[$tag] = $tag;
        }

        $statuses = [];
        foreach (is_array($input['statuses'] ?? null) ? $input['statuses'] : [] as $value) {
            $status = trim(preg_replace('/\s+/u', ' ', (string)$value) ?? '');
            if (strlen($status) > 120) {
                $this->responder->send(['message' => 'Некорректный статус в фильтре аудитории.', 'type' => 'error'], 422);
            }
            $statuses[$status] = $status;
        }

        $search = trim(preg_replace('/\s+/u', ' ', (string)($input['search'] ?? '')) ?? '');
        if (strlen($search) > 160) {
            $this->responder->send(['message' => 'Поисковый фильтр слишком длинный.', 'type' => 'error'], 422);
        }

        return [
            'groupTags' => array_values($groupTags),
            'statuses' => array_values($statuses),
            'search' => $search,
        ];
    }

    /** @param array{groupTags:list<string>,statuses:list<string>,search:string} $filter @return list<array<string,string>> */
    private function resolveAudience(array $filter): array
    {
        $where = ["TRIM(COALESCE(`user`.`email`, '')) <> ''"];
        $parameters = [];

        if ($filter['groupTags'] !== []) {
            $placeholders = [];
            foreach ($filter['groupTags'] as $index => $tag) {
                $key = ':group_' . $index;
                $placeholders[] = $key;
                $parameters[$key] = $tag;
            }
            $where[] = '`user`.`groupTag` IN (' . implode(', ', $placeholders) . ')';
        }
        if ($filter['statuses'] !== []) {
            $placeholders = [];
            foreach ($filter['statuses'] as $index => $status) {
                $key = ':status_' . $index;
                $placeholders[] = $key;
                $parameters[$key] = $status;
            }
            $where[] = "COALESCE(`user`.`userStatus`, '') IN (" . implode(', ', $placeholders) . ')';
        }
        if ($filter['search'] !== '') {
            $where[] = "CONCAT_WS(' ', COALESCE(`user`.`login`, ''), COALESCE(`user`.`email`, ''), COALESCE(`user`.`realname`, ''), COALESCE(`user`.`uuid`, '')) LIKE :search";
            $parameters[':search'] = '%' . $filter['search'] . '%';
        }

        $statement = $this->db->prepare(
            'SELECT `user`.`uuid`, `user`.`login`, `user`.`email`, `user`.`realname`, '
            . '`user`.`groupTag`, COALESCE(`user`.`userStatus`, \'\') AS `userStatus` '
            . 'FROM `users` AS `user` WHERE ' . implode(' AND ', $where)
            . ' ORDER BY `user`.`login`, `user`.`uuid`'
        );
        $statement->execute($parameters);

        $result = [];
        foreach ($statement->fetchAll(\PDO::FETCH_ASSOC) ?: [] as $row) {
            if (!is_array($row)) continue;
            $email = mb_strtolower(trim((string)($row['email'] ?? '')));
            if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) continue;
            $uuid = (string)($row['uuid'] ?? '');
            $result[] = [
                'uuid' => \Uuid::isValid($uuid) ? \Uuid::canonical($uuid) : $uuid,
                'login' => trim((string)($row['login'] ?? '')),
                'email' => $email,
                'realname' => trim((string)($row['realname'] ?? '')),
                'groupTag' => \GroupRepository::normalizeTag($row['groupTag'] ?? 'guest'),
                'userStatus' => trim((string)($row['userStatus'] ?? '')),
            ];
        }
        return $result;
    }

    /** @return list<string> */
    private function audienceStatuses(): array
    {
        $statement = $this->db->query(
            "SELECT DISTINCT COALESCE(`userStatus`, '') AS `userStatus` FROM `users` ORDER BY `userStatus`"
        );
        if (!$statement instanceof \PDOStatement) return [];
        $statuses = [];
        foreach ($statement->fetchAll(\PDO::FETCH_COLUMN) ?: [] as $status) {
            $statuses[] = trim((string)$status);
        }
        return array_values(array_unique($statuses));
    }

    /** @param array<string,string> $recipient @param array<string,mixed> $settings */
    private function mergeTags(string $template, array $recipient, bool $html, array $settings): string
    {
        $values = [
            'login' => (string)($recipient['login'] ?? ''),
            'username' => (string)($recipient['login'] ?? ''),
            'realname' => trim((string)($recipient['realname'] ?? '')) !== '' ? (string)$recipient['realname'] : (string)($recipient['login'] ?? ''),
            'email' => (string)($recipient['email'] ?? ''),
            'siteTitle' => (string)($settings['siteTitle'] ?? 'FoxesCraft'),
        ];
        foreach ($values as $key => $value) {
            $value = str_replace(["\r", "\n"], ' ', $value);
            if ($html) {
                $value = htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            }
            $template = str_replace('{{' . $key . '}}', $value, $template);
        }
        return $template;
    }

    private function diagnosticHint(string $code): string
    {
        return match ($code) {
            'authentication_failed' => 'For VK WorkSpace use the full mailbox address as the login and an application password, not the regular web-mail password.',
            'tls_extension_missing' => 'Enable the PHP OpenSSL extension for the web-server PHP runtime before using SMTP over SSL/TLS.',
            'tls_failed' => 'Check the server CA bundle/OpenSSL configuration and use smtp.mail.ru:465 with SSL/TLS.',
            'connection_failed' => 'Check outbound TCP access to smtp.mail.ru:465, DNS resolution and firewall rules.',
            default => 'Check the SMTP host, port, encryption mode, full mailbox login and application password.',
        };
    }

    /** @return array<string,mixed> */
    private function currentState(): array
    {
        return $this->repository->current($this->fallback());
    }

    /** @return array<string,mixed> */
    private function fallback(): array
    {
        return is_array($this->config['siteSettings'] ?? null)
            ? $this->config['siteSettings']
            : [];
    }

    /** @param array<string,mixed> $state @return array<string,mixed> */
    private function publicState(array $state): array
    {
        $settings = is_array($state['settings'] ?? null) ? $state['settings'] : [];

        return [
            'settings' => [
                'mailMethod' => (string)($settings['mailMethod'] ?? 'smtp'),
                'mailFromAddress' => (string)($settings['mailFromAddress'] ?? ''),
                'mailFromName' => (string)($settings['mailFromName'] ?? 'FoxesCraft'),
                'smtpHost' => (string)($settings['smtpHost'] ?? 'smtp.mail.ru'),
                'smtpPort' => (int)($settings['smtpPort'] ?? 465),
                'smtpSecurity' => (string)($settings['smtpSecurity'] ?? 'ssl'),
                'smtpUsername' => (string)($settings['smtpUsername'] ?? ''),
                'smtpPassword' => '',
                'passwordConfigured' => trim((string)($settings['smtpPassword'] ?? '')) !== '',
            ],
            'updatedAt' => (string)($state['updatedAt'] ?? ''),
            'storageReady' => (bool)($state['storageReady'] ?? false),
        ];
    }
}
