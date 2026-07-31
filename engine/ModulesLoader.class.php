<?php

declare(strict_types=1);

final class ModulesLoader
{
    private array $manifest;

    public function __construct(
        private $db,
        private $logger,
        private HttpRequest $request,
        private UserSession $session,
        private array $config,
    ) {
        $manifestPath = __DIR__ . '/data/modules.json';
        $manifestJson = file_get_contents($manifestPath);
        if ($manifestJson === false) {
            throw new RuntimeException('Module manifest is unavailable: ' . $manifestPath);
        }

        $manifest = json_decode($manifestJson, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($manifest)) {
            throw new RuntimeException('Invalid module manifest: ' . $manifestPath);
        }
        $this->manifest = $manifest;
    }

    /** @param list<string>|null $includeNames @param list<string> $excludeNames */
    public function loadPriority(
        string $modulesDirectory,
        string $priority,
        ?array $includeNames = null,
        array $excludeNames = [],
    ): array {
        $included = [];
        foreach ($this->manifest as $module) {
            if (($module['priority'] ?? '') !== $priority) {
                continue;
            }

            $name = (string)($module['name'] ?? '');
            if (($includeNames !== null && !in_array($name, $includeNames, true))
                || in_array($name, $excludeNames, true)) {
                continue;
            }
            $main = (string)($module['main'] ?? '');
            $class = $module['class'] ?? null;
            $groups = $module['groups'] ?? null;
            if ($name === '' || $main === '' || !$this->canLoad($groups)) {
                continue;
            }

            $entrypoint = rtrim($modulesDirectory, '/\\')
                . DIRECTORY_SEPARATOR . $name
                . DIRECTORY_SEPARATOR . $main;
            if (!is_file($entrypoint)) {
                throw new RuntimeException('Module entrypoint not found: ' . $entrypoint);
            }

            require_once $entrypoint;
            if (is_string($class) && $class !== '') {
                if (!class_exists($class, false)) {
                    throw new RuntimeException('Module class not declared: ' . $class);
                }
                new $class($this->db, $this->logger, $this->request, $this->session, $this->config);
            }

            $included[] = [
                'moduleName' => $name,
                'moduleClass' => $class,
                'modulePriority' => $priority,
                'moduleGroups' => $groups ?? '*',
            ];
        }

        return $included;
    }

    private function canLoad(mixed $groups): bool
    {
        if ($groups === null || $groups === '*') {
            return true;
        }
        if (!is_array($groups)) {
            $groups = [$groups];
        }
        return in_array($this->session->group(), array_map('intval', $groups), true);
    }
}
