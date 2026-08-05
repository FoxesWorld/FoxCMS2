<?php

declare(strict_types=1);

final class LoginContextResolver
{
    /** @return array{ip:string,country:string,region:string,city:string,locationLabel:string,browser:string,operatingSystem:string,deviceLabel:string,userAgent:string} */
    public function resolve(HttpRequest $request): array
    {
        $ip = trim($request->clientIp());
        $record = $this->geoIpRecord($ip);
        $country = $this->firstNonEmpty(
            $this->configuredHeader($request, 'FOXESCRAFT_GEO_COUNTRY_HEADER'),
            (string)($record['country_name'] ?? $record['country'] ?? ''),
        );
        $region = $this->firstNonEmpty(
            $this->configuredHeader($request, 'FOXESCRAFT_GEO_REGION_HEADER'),
            (string)($record['region'] ?? $record['region_name'] ?? ''),
        );
        $city = $this->firstNonEmpty(
            $this->configuredHeader($request, 'FOXESCRAFT_GEO_CITY_HEADER'),
            (string)($record['city'] ?? ''),
        );
        $country = $this->countryName($country);
        $parts = array_values(array_unique(array_filter(
            [trim($city), trim($region), trim($country)],
            static fn (string $value): bool => $value !== '',
        )));
        if ($parts !== []) {
            $locationLabel = implode(', ', $parts);
        } elseif ($this->isPrivateAddress($ip)) {
            $locationLabel = 'локальная сеть';
        } else {
            $locationLabel = 'регион не определён';
        }
        $userAgent = $this->truncate($request->userAgent(), 512);
        $browser = $this->browserLabel($userAgent);
        $operatingSystem = $this->operatingSystemLabel($userAgent);

        return [
            'ip' => $ip,
            'country' => trim($country),
            'region' => trim($region),
            'city' => trim($city),
            'locationLabel' => $locationLabel,
            'browser' => $browser,
            'operatingSystem' => $operatingSystem,
            'deviceLabel' => $browser . ', ' . $operatingSystem,
            'userAgent' => $userAgent,
        ];
    }

    public function welcomeBackThresholdSeconds(): int
    {
        $days = filter_var(foxEnv('FOXESCRAFT_WELCOME_BACK_DAYS', '30'), FILTER_VALIDATE_INT);
        $days = $days === false ? 30 : max(7, min(365, (int)$days));
        return $days * 86400;
    }

    /** @return array<string,mixed> */
    private function geoIpRecord(string $ip): array
    {
        if ($ip === '' || filter_var($ip, FILTER_VALIDATE_IP) === false || $this->isPrivateAddress($ip)) {
            return [];
        }
        if (function_exists('geoip_record_by_name')) {
            $record = @geoip_record_by_name($ip);
            return is_array($record) ? $record : [];
        }
        if (function_exists('geoip_country_name_by_name')) {
            $country = @geoip_country_name_by_name($ip);
            return is_string($country) && trim($country) !== '' ? ['country_name' => trim($country)] : [];
        }
        return [];
    }

    private function configuredHeader(HttpRequest $request, string $environmentName): string
    {
        $header = trim((string)(foxEnv($environmentName, '') ?? ''));
        if ($header === '' || preg_match('/^[A-Za-z0-9-]{1,64}$/D', $header) !== 1) {
            return '';
        }
        return $this->truncate($request->header($header), 128);
    }

    private function countryName(string $value): string
    {
        $value = trim($value);
        if (preg_match('/^[A-Za-z]{2}$/D', $value) !== 1) {
            return $value;
        }
        $code = strtoupper($value);
        if (class_exists('Locale')) {
            $display = Locale::getDisplayRegion('-' . $code, 'ru_RU');
            if (is_string($display) && trim($display) !== '' && strtoupper($display) !== $code) {
                return trim($display);
            }
        }
        return $code;
    }

    private function browserLabel(string $userAgent): string
    {
        if ($userAgent === '') {
            return 'Неизвестный браузер';
        }
        return match (true) {
            preg_match('/YaBrowser\/([\d.]+)/i', $userAgent, $match) === 1 => 'Яндекс Браузер ' . $match[1],
            preg_match('/Edg\/([\d.]+)/i', $userAgent, $match) === 1 => 'Edge ' . $match[1],
            preg_match('/OPR\/([\d.]+)/i', $userAgent, $match) === 1 => 'Opera ' . $match[1],
            preg_match('/Firefox\/([\d.]+)/i', $userAgent, $match) === 1 => 'Firefox ' . $match[1],
            preg_match('/Chrome\/([\d.]+)/i', $userAgent, $match) === 1 => 'Chrome ' . $match[1],
            preg_match('/Version\/([\d.]+).*Safari/i', $userAgent, $match) === 1 => 'Safari ' . $match[1],
            default => 'Неизвестный браузер',
        };
    }

    private function operatingSystemLabel(string $userAgent): string
    {
        return match (true) {
            str_contains($userAgent, 'Windows NT 10.0') => 'Windows',
            str_contains($userAgent, 'Windows') => 'Windows',
            str_contains($userAgent, 'Android') => 'Android',
            preg_match('/iPhone|iPad|iPod/i', $userAgent) === 1 => 'iOS',
            str_contains($userAgent, 'Mac OS X') => 'macOS',
            str_contains($userAgent, 'Linux') => 'Linux',
            default => 'Неизвестная ОС',
        };
    }

    private function isPrivateAddress(string $ip): bool
    {
        if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
            return true;
        }
        return filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
        ) === false;
    }

    private function firstNonEmpty(string ...$values): string
    {
        foreach ($values as $value) {
            $value = trim($value);
            if ($value !== '') {
                return $value;
            }
        }
        return '';
    }

    private function truncate(string $value, int $length): string
    {
        return function_exists('mb_substr') ? mb_substr($value, 0, $length, 'UTF-8') : substr($value, 0, $length);
    }
}
