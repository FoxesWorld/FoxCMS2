<?php

declare(strict_types=1);

if (!defined('FOXXEY')) {
    http_response_code(403);
    exit('Forbidden');
}

final class FoxMail
{
    private \PHPMailer\PHPMailer\PHPMailer $mail;
    private bool $html;
    public bool $send_error = false;
    public string $smtp_msg = '';
    public ?string $from = null;
    public array $bcc = [];
    public bool $keepalive = false;

    public function __construct(bool $isHtml = false, ?array $settingsOverride = null, bool $diagnosticMode = false)
    {
        global $config;

        $autoload = defined('ROOT_DIR') ? ROOT_DIR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php' : '';
        if ($autoload !== '' && is_file($autoload)) {
            require_once $autoload;
        }
        if (!class_exists(\PHPMailer\PHPMailer\PHPMailer::class, false)) {
            $vendor = __DIR__ . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'PHPMailer' . DIRECTORY_SEPARATOR;
            require_once $vendor . 'Exception.php';
            require_once $vendor . 'SMTP.php';
            require_once $vendor . 'PHPMailer.php';
        }

        $this->mail = new \PHPMailer\PHPMailer\PHPMailer($diagnosticMode);
        $this->html = $isHtml;
        $this->mail->CharSet = \PHPMailer\PHPMailer\PHPMailer::CHARSET_UTF8;
        $this->mail->Encoding = \PHPMailer\PHPMailer\PHPMailer::ENCODING_BASE64;

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
            $this->configureSmtp($settings, $username, $sender);
        }

        $this->mail->XMailer = 'FoxesCraft';
        if ($isHtml) {
            $this->mail->isHTML(true);
        }
    }

    /** @return array{success:bool,message:string,code:string,detail?:string,smtpCode?:string,smtpReply?:string,library:string} */
    public function testConnection(): array
    {
        if ($this->mail->Mailer !== 'smtp') {
            return $this->diagnostic(false, 'SMTP transport is disabled.', 'smtp_disabled');
        }
        if ($this->mail->Host === '' || $this->mail->Username === '' || $this->mail->Password === '') {
            return $this->diagnostic(false, 'SMTP host, username and password are required.', 'smtp_configuration_missing');
        }
        if ($this->mail->SMTPSecure !== '' && !extension_loaded('openssl')) {
            return $this->diagnostic(false, 'PHP OpenSSL extension is not enabled.', 'tls_extension_missing');
        }

        try {
            $success = $this->mail->smtpConnect();
            if ($success) {
                $this->mail->smtpClose();
                return $this->diagnostic(true, 'SMTP connection and authentication succeeded.', 'ok');
            }
            return $this->smtpFailure('SMTP connection failed.');
        } catch (\PHPMailer\PHPMailer\Exception $error) {
            return $this->smtpFailure($error->getMessage());
        } catch (\Throwable $error) {
            return $this->smtpFailure($error->getMessage());
        } finally {
            $this->mail->smtpClose();
        }
    }

    public function send(string $to, string $subject, string $templateName, array $entries = []): bool
    {
        if (filter_var($to, FILTER_VALIDATE_EMAIL) === false) {
            throw new \InvalidArgumentException('Invalid email recipient.');
        }

        $message = $this->replaceEntries($this->getTemplate($templateName), $entries);
        if ($this->from !== null && filter_var($this->from, FILTER_VALIDATE_EMAIL) !== false) {
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
            if (filter_var($bcc, FILTER_VALIDATE_EMAIL) !== false) {
                $this->mail->addBCC($bcc);
            }
        }

        try {
            $sent = $this->mail->send();
        } catch (\PHPMailer\PHPMailer\Exception $error) {
            $sent = false;
            $this->mail->ErrorInfo = $error->getMessage();
        }
        if (!$sent) {
            $this->smtp_msg = trim($this->mail->ErrorInfo);
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

    /** @param array<string,mixed> $settings */
    private function configureSmtp(array $settings, string $username, string $sender): void
    {
        $this->mail->isSMTP();
        $this->mail->Timeout = 15;
        $this->mail->Host = trim((string)($settings['smtpHost'] ?? $settings['smtp_host'] ?? 'smtp.mail.ru'));
        $this->mail->Port = (int)($settings['smtpPort'] ?? $settings['smtp_port'] ?? 465);
        $security = strtolower(trim((string)($settings['smtpSecurity'] ?? $settings['smtp_secure'] ?? 'ssl')));
        $this->mail->SMTPSecure = match ($security) {
            'ssl' => \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS,
            'tls' => \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS,
            default => '',
        };
        $this->mail->SMTPAuth = true;
        $this->mail->Username = $username;
        $this->mail->Password = (string)($settings['smtpPassword'] ?? $settings['smtp_pass'] ?? '');
        $this->mail->SMTPAutoTLS = true;
        if (filter_var($sender, FILTER_VALIDATE_EMAIL) !== false) {
            $this->mail->Sender = $sender;
        }
    }

    /** @return array{success:bool,message:string,code:string,detail?:string,smtpCode?:string,smtpReply?:string,library:string} */
    private function smtpFailure(string $fallback): array
    {
        $smtp = $this->mail->getSMTPInstance();
        $error = $smtp->getError();
        $message = trim($this->mail->ErrorInfo);
        if ($message === '') {
            $message = trim((string)($error['error'] ?? ''));
        }
        if ($message === '') {
            $message = trim($fallback) !== '' ? trim($fallback) : 'SMTP connection failed.';
        }
        $detail = trim((string)($error['detail'] ?? ''));
        $smtpCode = trim((string)($error['smtp_code'] ?? ''));
        $smtpReply = trim($smtp->getLastReply());
        $combined = implode(' ', array_filter([$message, $detail, $smtpCode, $smtpReply]));

        $result = $this->diagnostic(false, $message, $this->diagnosticCode($combined));
        if ($detail !== '') {
            $result['detail'] = mb_substr($detail, 0, 700, 'UTF-8');
        }
        if ($smtpCode !== '') {
            $result['smtpCode'] = mb_substr($smtpCode, 0, 32, 'UTF-8');
        }
        if ($smtpReply !== '') {
            $result['smtpReply'] = mb_substr($smtpReply, 0, 1000, 'UTF-8');
        }
        return $result;
    }

    /** @return array{success:bool,message:string,code:string,library:string} */
    private function diagnostic(bool $success, string $message, string $code): array
    {
        return [
            'success' => $success,
            'message' => $message,
            'code' => $code,
            'library' => 'PHPMailer ' . \PHPMailer\PHPMailer\PHPMailer::VERSION,
        ];
    }

    private function diagnosticCode(string $message): string
    {
        $message = strtolower($message);
        if (str_contains($message, 'authenticate') || str_contains($message, 'authentication') || str_contains($message, '535')) {
            return 'authentication_failed';
        }
        if (str_contains($message, 'certificate') || str_contains($message, 'crypto') || str_contains($message, 'tls') || str_contains($message, 'ssl')) {
            return 'tls_failed';
        }
        if (str_contains($message, 'timed out') || str_contains($message, 'timeout') || str_contains($message, 'getaddrinfo') || str_contains($message, 'failed to connect') || str_contains($message, 'connection refused')) {
            return 'connection_failed';
        }
        return 'smtp_failed';
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
            throw new \InvalidArgumentException('Invalid mail template name: ' . $name);
        }

        $directory = CURRENT_TEMPLATE . 'data' . DIRECTORY_SEPARATOR . 'mail';
        $path = $directory . DIRECTORY_SEPARATOR . $name;
        if (!is_dir($directory)) {
            throw new \RuntimeException('Mail template directory not found: ' . $directory);
        }
        if (!is_readable($directory)) {
            throw new \RuntimeException('Mail template directory is not readable: ' . $directory);
        }
        if (!is_file($path)) {
            throw new \RuntimeException('Mail template not found: ' . $path);
        }
        if (!is_readable($path)) {
            throw new \RuntimeException('Mail template is not readable: ' . $path);
        }

        error_clear_last();
        $content = @file_get_contents($path);
        if (!is_string($content)) {
            $error = error_get_last();
            $detail = is_array($error) && is_string($error['message'] ?? null)
                ? trim((string)$error['message'])
                : 'PHP did not provide a filesystem warning.';
            throw new \RuntimeException('Mail template could not be read: ' . $path . '. System error: ' . $detail);
        }
        return $content;
    }
}
