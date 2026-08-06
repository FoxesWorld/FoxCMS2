<?php

declare(strict_types=1);

namespace FoxCMS\Api\Bootstrap\Runtime;

use FoxCMS\Api\Core\Request;

/** Runtime selection input parsing and validation. */
final class RuntimeRequest
{
    /** @param array<string, scalar|null> $overrides @return array<string, mixed> */
    public static function fromRequest(Request $request, array $overrides = []): array
    {
        $input = [
            'platform' => $request->query('platform'),
            'version' => $request->query('version'),
            'distribution' => $request->query('distribution'),
            'vendor' => $request->query('vendor'),
            'allow_prerelease' => $request->query('allow_prerelease'),
            'client_version' => $request->query('client_version'),
        ];
        return self::fromArray(array_replace($input, $overrides));
    }

    /** @param array<string, scalar|null> $input @return array<string, mixed> */
    public static function fromArray(array $input): array
    {
        $platform = self::value($input, 'platform', 'windows-x86_64');
        if (preg_match('/^(?:(?:windows|linux)-(?:x86|x86_64|aarch64)|macos-(?:x86_64|aarch64))$/D', $platform) !== 1) {
            RuntimeSupport::fail(422, 'runtime_request_platform_invalid', 'The requested runtime platform is unsupported.', [
                'received' => $platform,
            ]);
        }

        $version = self::value($input, 'version');
        if (preg_match('/^[0-9]+(?:\.[0-9]+)*$/D', $version) !== 1) {
            RuntimeSupport::fail(422, 'runtime_request_version_invalid', 'version must be a Java major such as 17 or an exact version such as 17.0.16.', [
                'received' => $version,
            ]);
        }
        $versionMode = strpos($version, '.') === false ? 'major' : 'exact';

        $distribution = strtolower(self::value($input, 'distribution', 'any'));
        if (!in_array($distribution, ['any', 'jdk', 'jre'], true)) {
            RuntimeSupport::fail(422, 'runtime_request_distribution_invalid', 'distribution must be any, jdk or jre.', [
                'received' => $distribution,
            ]);
        }

        $vendor = self::value($input, 'vendor');
        if ($vendor !== '' && preg_match('/^[A-Za-z0-9 ._+-]{1,80}$/D', $vendor) !== 1) {
            RuntimeSupport::fail(422, 'runtime_request_vendor_invalid', 'vendor contains unsupported characters.');
        }

        $allowPrereleaseRaw = strtolower(self::value($input, 'allow_prerelease', 'false'));
        if (!in_array($allowPrereleaseRaw, ['1', '0', 'true', 'false', 'yes', 'no'], true)) {
            RuntimeSupport::fail(422, 'runtime_request_prerelease_invalid', 'allow_prerelease must be boolean.');
        }

        $clientVersion = self::value($input, 'client_version', 'legacy-or-unknown');
        if (strlen($clientVersion) > 128 || str_contains($clientVersion, "\0")) {
            RuntimeSupport::fail(422, 'runtime_request_client_version_invalid', 'client_version is invalid.');
        }

        return [
            'platform' => $platform,
            'version' => $version,
            'version_mode' => $versionMode,
            'java_major' => RuntimeMetadata::runtimeMajorFromVersion($version),
            'distribution' => $distribution,
            'vendor' => $vendor,
            'allow_prerelease' => in_array($allowPrereleaseRaw, ['1', 'true', 'yes'], true),
            'client_version' => $clientVersion,
        ];
    }

    /** @deprecated Pass Request explicitly through fromRequest(). @return array<string, mixed> */
    public static function parseRuntimeRequest(): array
    {
        return self::fromRequest(Request::fromGlobals());
    }

    /** @param array<string, scalar|null> $input */
    private static function value(array $input, string $key, string $default = ''): string
    {
        $value = $input[$key] ?? null;
        return is_scalar($value) ? trim((string)$value) : $default;
    }
}
