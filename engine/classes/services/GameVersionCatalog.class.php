<?php

declare(strict_types=1);

final class GameVersionCatalog
{
    private ?array $snapshot = null;

    public function __construct(private string $versionsDirectory)
    {
        $this->versionsDirectory = rtrim(trim($versionsDirectory), '/\\');
        if ($this->versionsDirectory === '') {
            throw new InvalidArgumentException('Каталог версий клиента не настроен.');
        }
    }

    public function versionsPath(): string
    {
        return $this->versionsDirectory;
    }

    /**
     * @return array{
     *   available: bool,
     *   root: string,
     *   directories: int,
     *   ignoredEntries: int,
     *   options: list<array{value: string, label: string}>
     * }
     */
    public function scan(): array
    {
        if (is_array($this->snapshot)) {
            return $this->snapshot;
        }

        $root = realpath($this->versionsDirectory);
        if (!is_string($root) || !is_dir($root)) {
            throw new RuntimeException('Каталог версий клиента не найден: ' . $this->versionsDirectory . '.');
        }
        if (!is_readable($root)) {
            throw new RuntimeException('Каталог версий клиента недоступен для чтения: ' . $root . '.');
        }

        $entries = scandir($root);
        if (!is_array($entries)) {
            throw new RuntimeException('Не удалось прочитать каталог версий клиента: ' . $root . '.');
        }

        $options = [];
        $ignoredEntries = 0;
        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            if (str_starts_with($entry, '.')) {
                ++$ignoredEntries;
                continue;
            }

            $path = $root . DIRECTORY_SEPARATOR . $entry;
            if (!is_dir($path) || is_link($path) || !is_readable($path)) {
                ++$ignoredEntries;
                continue;
            }
            if (preg_match('/^[A-Za-z0-9._+ -]{1,128}$/D', $entry) !== 1) {
                ++$ignoredEntries;
                continue;
            }

            $options[] = [
                'value' => $entry,
                'label' => $entry,
            ];
        }

        usort(
            $options,
            static fn(array $left, array $right): int => strnatcasecmp($right['value'], $left['value']),
        );

        return $this->snapshot = [
            'available' => true,
            'root' => str_replace('\\', '/', $root),
            'directories' => count($options),
            'ignoredEntries' => $ignoredEntries,
            'options' => $options,
        ];
    }
}
