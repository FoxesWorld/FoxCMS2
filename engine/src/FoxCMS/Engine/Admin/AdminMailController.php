<?php

declare(strict_types=1);

namespace FoxCMS\Engine\Admin;

final class AdminMailController
{
    public function __construct(
        private array $config,
        private \SiteSettingsRepository $repository,
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

        \UtilityLoader::load('FoxMail', '1.0.0');
        $mailer = new \FoxMail(true, $settings);
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
        ]);
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
