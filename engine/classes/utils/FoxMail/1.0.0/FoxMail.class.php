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

    public function __construct(bool $isHtml = false)
    {
        global $config;

        require_once __DIR__ . '/Mailer.class.php';
        $this->mail = new PHPMailer();
        $this->html = $isHtml;
        $this->mail->CharSet = 'UTF-8';
        $this->mail->Encoding = 'base64';

        $settings = $config['siteSettings'] ?? [];
        $sender = (string)($settings['admin_mail'] ?? $settings['contactEmail'] ?? '');
        $title = (string)($settings['mail_title'] ?? 'FoxesCraft');
        $this->mail->setFrom($sender, $title);

        if (($settings['mail_metod'] ?? '') === 'smtp') {
            $this->mail->isSMTP();
            $this->mail->Timeout = 10;
            $this->mail->Host = (string)($settings['smtp_host'] ?? 'localhost');
            $this->mail->Port = (int)($settings['smtp_port'] ?? 465);
            $this->mail->SMTPSecure = (string)($settings['smtp_secure'] ?? 'ssl');
            $this->mail->SMTPAuth = true;
            $this->mail->Username = $sender;
            $this->mail->Password = (string)($settings['smtp_pass'] ?? '');
            $this->mail->From = $sender;
            $this->mail->Sender = $sender;
        }

        $this->mail->XMailer = 'FoxesCraft';
        if ($isHtml) {
            $this->mail->isHTML();
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
        global $config;

        if (!preg_match('/^[A-Za-z0-9_-]+\.html$/', $name)) {
            throw new InvalidArgumentException('Invalid mail template name.');
        }
        $path = CURRENT_TEMPLATE . 'mail' . DIRECTORY_SEPARATOR . $name;
        if (!is_file($path) || !is_readable($path)) {
            throw new RuntimeException('Mail template not found: ' . $name);
        }
        $content = file_get_contents($path);
        if ($content === false) {
            throw new RuntimeException('Mail template could not be read: ' . $name);
        }
        return $content;
    }
}
