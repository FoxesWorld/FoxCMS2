<?php

declare(strict_types=1);

final class HCaptchaVerifier
{
    private const VERIFY_ENDPOINT = 'https://api.hcaptcha.com/siteverify';

    /**
     * @return array{success:bool,errorCodes:list<string>,transportError:bool}
     */
    public function verify(
        string $secret,
        string $responseToken,
        string $remoteIp = '',
        string $siteKey = '',
    ): array {
        $secret = trim($secret);
        $responseToken = trim($responseToken);
        $siteKey = trim($siteKey);

        if ($secret === '' || $responseToken === '') {
            return ['success' => false, 'errorCodes' => ['missing-input-response'], 'transportError' => false];
        }

        $payload = [
            'secret' => $secret,
            'response' => $responseToken,
        ];
        if ($remoteIp !== '') {
            $payload['remoteip'] = $remoteIp;
        }
        if ($siteKey !== '') {
            $payload['sitekey'] = $siteKey;
        }

        $body = $this->postForm(self::VERIFY_ENDPOINT, $payload);
        if ($body === null) {
            return ['success' => false, 'errorCodes' => [], 'transportError' => true];
        }

        try {
            $decoded = json_decode($body, true, 32, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            return ['success' => false, 'errorCodes' => [], 'transportError' => true];
        }
        if (!is_array($decoded)) {
            return ['success' => false, 'errorCodes' => [], 'transportError' => true];
        }

        $errors = [];
        foreach (is_array($decoded['error-codes'] ?? null) ? $decoded['error-codes'] : [] as $code) {
            if (is_string($code) && preg_match('/^[a-z0-9-]{1,80}$/D', $code) === 1) {
                $errors[] = $code;
            }
        }
        return [
            'success' => ($decoded['success'] ?? false) === true,
            'errorCodes' => array_values(array_unique($errors)),
            'transportError' => false,
        ];
    }

    /** @param array<string, string> $payload */
    private function postForm(string $url, array $payload): ?string
    {
        // hCaptcha explicitly requires application/x-www-form-urlencoded POST.
        $encoded = http_build_query($payload, '', '&', PHP_QUERY_RFC1738);

        if (function_exists('curl_init')) {
            $curl = curl_init($url);
            if ($curl === false) return null;
            curl_setopt_array($curl, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $encoded,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => 3,
                CURLOPT_TIMEOUT => 7,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/x-www-form-urlencoded',
                    'Accept: application/json',
                ],
                CURLOPT_USERAGENT => 'FoxesCraft/3.0 hCaptcha verifier',
            ]);
            $response = curl_exec($curl);
            $status = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
            curl_close($curl);
            return is_string($response) && $status >= 200 && $status < 300 ? $response : null;
        }

        if (!filter_var(ini_get('allow_url_fopen'), FILTER_VALIDATE_BOOL)) return null;
        $context = stream_context_create(['http' => [
            'method' => 'POST',
            'header' => implode("\r\n", [
                'Content-Type: application/x-www-form-urlencoded',
                'Accept: application/json',
                'User-Agent: FoxesCraft/3.0 hCaptcha verifier',
            ]),
            'content' => $encoded,
            'timeout' => 7,
            'ignore_errors' => true,
        ]]);
        $response = @file_get_contents($url, false, $context);
        return is_string($response) ? $response : null;
    }
}
