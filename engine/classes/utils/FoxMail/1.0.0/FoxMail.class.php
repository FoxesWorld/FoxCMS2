<?php

declare(strict_types=1);

if (!defined('FOXXEY')) {
    http_response_code(403);
    exit('Forbidden');
}

final class FoxMail
{
    private PHPMailer $mail;
    private bool $html;
    public bool $send_error = false;
    public string $smtp_msg = '';
    public ?string $from = null;
    public array $bcc = [];
    public bool $keepalive = false;

    public function __construct(bool $isHtml = false, ?array $settingsOverride = null)
    {
        global $config;

        if (!defined('FoxMail')) {
            define('FoxMail', true);
        }
        require_once __DIR__ . '/Mailer.class.php';
        $this->mail = new PHPMailer();
        $this->html = $isHtml;
        $this->mail->CharSet = 'UTF-8';
        $this->mail->Encoding = 'base64';

        $settings = is_array($settingsOverride) ? $settingsOverride : ($config['siteSettings'] ?? []);
        $username = trim((string)($settings['smtpUsername'] ?? $settings['admin_mail'] ?? ''));
        $sender = trim((string)($settings['mailFromAddress'] ?? $settings['admin_mail'] ?? $settings['contactEmail'] ?? $username));
        if ($sender === '') {
            $sender = $username;
        }
        $title = trim((string)($settings['mailFromName'] ?? $settings['mail_title'] ?? 'FoxesCraft'));
        if (filter_var($sender, FILTER_VALIDATE_EMAIL) !== false) {
            $this->mail->setFrom($sender, $title !== '' ? $title : 'FoxesCraft');
        }

        if (($settings['mailMethod'] ?? $settings['mail_metod'] ?? '') === 'smtp') {
            $this->mail->isSMTP();
            $this->mail->Timeout = 10;
            $this->mail->Host = (string)($settings['smtpHost'] ?? $settings['smtp_host'] ?? 'smtp.mail.ru');
            $this->mail->Port = (int)($settings['smtpPort'] ?? $settings['smtp_port'] ?? 465);
            $this->mail->SMTPSecure = (string)($settings['smtpSecurity'] ?? $settings['smtp_secure'] ?? 'ssl');
            $this->mail->SMTPAuth = true;
            $this->mail->Username = $username;
            $this->mail->Password = (string)($settings['smtpPassword'] ?? $settings['smtp_pass'] ?? '');
            if (filter_var($sender, FILTER_VALIDATE_EMAIL) !== false) {
                $this->mail->From = $sender;
                $this->mail->Sender = $sender;
            }
        }

        $this->mail->XMailer = 'FoxesCraft';
        if ($isHtml) {
            $this->mail->isHTML();
        }
    }

    /** @return array{success:bool,message:string} */
    public function testConnection(): array
    {
        if ($this->mail->Mailer !== 'smtp') {
            return ['success' => false, 'message' => 'SMTP transport is disabled.'];
        }
        if ($this->mail->Host === '' || $this->mail->Username === '' || $this->mail->Password === '') {
            return ['success' => false, 'message' => 'SMTP host, username and password are required.'];
        }
        try {
            $success = (bool)$this->mail->smtpConnect();
            $message = $success
                ? 'SMTP connection and authentication succeeded.'
                : ($this->mail->ErrorInfo ?: 'SMTP connection failed.');
            $this->mail->smtpClose();
            return ['success' => $success, 'message' => $message];
        } catch (Throwable $error) {
            $this->mail->smtpClose();
            return ['success' => false, 'message' => $error->getMessage()];
        }
    }

    public function send(string $to, string $subject, string $templateName, array $entries = []): bool
    {
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Invalid email recipient.');
        }

        $message = $this->replaceEntries($this->getTemplate($templateName), $entries);
        if ($this->from !== null && filter_var($this->from, FILTER_VALIDATE_EMAIL)) {
            $this->mail->addReplyTo($this->from, $this->from);
        }

        $this->mail->addAddress($to);
        $this->mail->Subject = $subject;
        $this->mail->SMTPKeepAlive = $this->mail->Mailer === 'smtp' && $this->keepalive;
        if ($this->html) {
            $this->mail->msgHTML($message);
        } else {
            $this->mail->Body = $message;
        }

        foreach ($this->bcc as $bcc) {
            if (filter_var($bcc, FILTER_VALIDATE_EMAIL)) {
                $this->mail->addBCC($bcc);
            }
        }

        $sent = $this->mail->send();
        if (!$sent) {
            $this->smtp_msg = $this->mail->ErrorInfo;
            $this->send_error = true;
        }
        $this->mail->clearAllRecipients();
        $this->mail->clearAttachments();
        return $sent;
    }

    public function addAttachment(string $path, string $name = '', string $encoding = 'base64', string $type = '', string $disposition = 'attachment'): void
    {
        $this->mail->addAttachment($path, $name, $encoding, $type, $disposition);
    }

    private function replaceEntries(string $template, array $entries): string
    {
        foreach ($entries as $key => $value) {
            $safeValue = htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $template = str_replace('{{' . $key . '}}', $safeValue, $template);
        }
        return $template;
    }

    private function getTemplate(string $name): string
    {
        if (!preg_match('/^[A-Za-z0-9_-]+\.html$/D', $name)) {
            throw new InvalidArgumentException('Invalid mail template name: ' . $name);
        }

        $directory = CURRENT_TEMPLATE . 'data' . DIRECTORY_SEPARATOR . 'mail';
        $path = $directory . DIRECTORY_SEPARATOR . $name;
        if (!is_dir($directory)) {
            throw new RuntimeException('Mail template directory not found: ' . $directory);
        }
        if (!is_readable($directory)) {
            throw new RuntimeException('Mail template directory is not readable: ' . $directory);
        }
        if (!is_file($path)) {
            throw new RuntimeException('Mail template not found: ' . $path);
        }
        if (!is_readable($path)) {
            throw new RuntimeException('Mail template is not readable: ' . $path);
        }

        error_clear_last();
        $content = @file_get_contents($path);
        if (!is_string($content)) {
            $error = error_get_last();
            $detail = is_array($error) && is_string($error['message'] ?? null)
                ? trim((string)$error['message'])
                : 'PHP did not provide a filesystem warning.';
            throw new RuntimeException(
                'Mail template could not be read: ' . $path . '. System error: ' . $detail
            );
        }
        return $content;
    }
}
