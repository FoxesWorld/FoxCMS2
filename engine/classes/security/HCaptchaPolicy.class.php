<?php

declare(strict_types=1);

final class HCaptchaPolicy
{
    private const SCOPES = [
        'login' => 'hcaptchaProtectLogin',
        'registration' => 'hcaptchaProtectRegistration',
        'passwordRecovery' => 'hcaptchaProtectPasswordRecovery',
        'passwordReset' => 'hcaptchaProtectPasswordReset',
    ];

    public static function required(array $config, string $scope): bool
    {
        $site = is_array($config['siteSettings'] ?? null) ? $config['siteSettings'] : [];
        $field = self::SCOPES[$scope] ?? null;
        return $field !== null
            && filter_var($site['hcaptchaEnabled'] ?? false, FILTER_VALIDATE_BOOLEAN)
            && filter_var($site[$field] ?? true, FILTER_VALIDATE_BOOLEAN);
    }

    /** @return array{success:bool,errorCodes:list<string>,transportError:bool,configured:bool} */
    public static function verify(array $config, HttpRequest $request): array
    {
        $site = is_array($config['siteSettings'] ?? null) ? $config['siteSettings'] : [];
        $siteKey = trim((string)($site['hcaptchaSiteKey'] ?? ''));
        $secret = trim((string)($site['hcaptchaSecret'] ?? ''));
        if ($siteKey === '' || $secret === '') {
            return ['success' => false, 'errorCodes' => [], 'transportError' => false, 'configured' => false];
        }
        $token = $request->string('hcaptchaToken');
        if ($token === '') {
            $token = $request->string('h-captcha-response');
        }
        $result = (new HCaptchaVerifier())->verify($secret, $token, $request->clientIp(), $siteKey);
        return $result + ['configured' => true];
    }
}
