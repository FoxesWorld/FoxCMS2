<?php

declare(strict_types=1);

final class GetJre implements JsonSerializable
{
    private array $payload;

    public function __construct(string $version, array $config)
    {
        if (preg_match('/^[A-Za-z0-9._-]{1,48}$/D', $version) !== 1) {
            throw new InvalidArgumentException('Invalid JRE version.');
        }

        $launcher = is_array($config['launcherSettings'] ?? null)
            ? $config['launcherSettings']
            : [];
        $relativeDirectory = (string)($launcher['jreDir'] ?? 'files/runtime/');
        $root = realpath(ROOT_DIR . UPLOADS_DIR . $relativeDirectory);
        if ($root === false || !is_dir($root)) {
            $this->payload = ['message' => 'Runtime directory not found.'];
            return;
        }

        $candidate = $root . DIRECTORY_SEPARATOR . $version . '.zip';
        $realFile = realpath($candidate);
        if (
            $realFile === false
            || !is_file($realFile)
            || !str_starts_with($realFile, $root . DIRECTORY_SEPARATOR)
        ) {
            $this->payload = ['message' => 'Runtime archive not found.'];
            return;
        }

        $relativePath = str_replace('\\', '/', substr($realFile, strlen(ROOT_DIR)));
        $this->payload = [
            'filename' => $relativePath,
            'hash' => md5_file($realFile),
            'sha256' => hash_file('sha256', $realFile),
            'size' => filesize($realFile),
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->payload;
    }
}
