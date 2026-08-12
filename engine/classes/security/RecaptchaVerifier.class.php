<?php

declare(strict_types=1);

/**
 * Minimal server-side Google reCAPTCHA verifier.
 *
 * The verifier fails closed: malformed responses and transport failures are
 * treated as unsuccessful verification.
 */
final class RecaptchaVerifier
{
    private const VERIFY_ENDPOINT = 'https://www.google.com/recaptcha/api/siteverify';

    public function verify(string $secret, string $responseToken, string $remoteIp = ''): bool
    {
        $secret = trim($secret);
        $responseToken = trim($responseToken);

        if ($secret === '' || $responseToken === '') {
            return false;
        }

        $payload = [
            'secret' => $secret,
            'response' => $responseToken,
        ];

        if ($remoteIp !== '') {
            $payload['remoteip'] = $remoteIp;
        }

        $body = $this->postForm(self::VERIFY_ENDPOINT, $payload);
        if ($body === null) {
            return false;
        }

        $decoded = json_decode($body, true);

        return is_array($decoded) && ($decoded['success'] ?? false) === true;
    }

    /**
     * @param array<string, string> $payload
     */
    private function postForm(string $url, array $payload): ?string
    {
        $encodedPayload = http_build_query($payload, '', '&', PHP_QUERY_RFC3986);

        if (function_exists('curl_init')) {
            $curl = curl_init($url);
            if ($curl === false) {
                return null;
            }

            curl_setopt_array($curl, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $encodedPayload,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => 3,
                CURLOPT_TIMEOUT => 7,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/x-www-form-urlencoded',
                    'Accept: application/json',
                ],
                CURLOPT_USERAGENT => 'FoxesCraft/3.0 reCAPTCHA verifier',
            ]);

            $response = curl_exec($curl);
            $statusCode = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
            curl_close($curl);

            if (!is_string($response) || $statusCode < 200 || $statusCode >= 300) {
                return null;
            }

            return $response;
        }

        if (!filter_var(ini_get('allow_url_fopen'), FILTER_VALIDATE_BOOL)) {
            return null;
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => implode("\r\n", [
                    'Content-Type: application/x-www-form-urlencoded',
                    'Accept: application/json',
                    'User-Agent: FoxesCraft/3.0 reCAPTCHA verifier',
                ]),
                'content' => $encodedPayload,
                'timeout' => 7,
                'ignore_errors' => true,
            ],
        ]);

        $response = @file_get_contents($url, false, $context);

        return is_string($response) ? $response : null;
    }
}
