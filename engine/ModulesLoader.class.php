<?php

declare(strict_types=1);

final class ModulesLoader
{
    private array $manifest;

    public function __construct(
        private db $db,
        private Logger $logger,
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
        $startedAt = hrtime(true);
        $included = [];
        $moduleTimings = [];
        $phaseCandidates = 0;
        $skipped = [
            'notIncluded' => 0,
            'explicitlyExcluded' => 0,
            'invalidDefinition' => 0,
            'groupDenied' => 0,
        ];

        foreach ($this->manifest as $module) {
            if (($module['priority'] ?? '') !== $priority) {
                continue;
            }
            $phaseCandidates++;

            $name = (string)($module['name'] ?? '');
            if ($includeNames !== null && !in_array($name, $includeNames, true)) {
                $skipped['notIncluded']++;
                continue;
            }
            if (in_array($name, $excludeNames, true)) {
                $skipped['explicitlyExcluded']++;
                continue;
            }

            $main = (string)($module['main'] ?? '');
            $class = $module['class'] ?? null;
            $groups = $module['groups'] ?? null;
            if ($name === '' || $main === '') {
                $skipped['invalidDefinition']++;
                continue;
            }
            if (!$this->canLoad($groups)) {
                $skipped['groupDenied']++;
                continue;
            }

            $entrypoint = rtrim($modulesDirectory, '/\\')
                . DIRECTORY_SEPARATOR . $name
                . DIRECTORY_SEPARATOR . $main;
            if (!is_file($entrypoint)) {
                RequestTelemetry::deviation(
                    'module.entrypoint.missing',
                    'module_entrypoint_missing',
                    sprintf('Module %s cannot load because its entrypoint is missing.', $name),
                    'critical',
                    ['entrypointExists' => true],
                    ['entrypointExists' => false],
                    [
                        'component' => 'module_loader',
                        'moduleName' => $name,
                        'modulePriority' => $priority,
                        'moduleGroups' => $groups ?? '*',
                        'entrypoint' => $entrypoint,
                    ],
                );
                throw new RuntimeException('Module entrypoint not found: ' . $entrypoint);
            }

            $moduleStartedAt = hrtime(true);
            try {
                require_once $entrypoint;
                if (is_string($class) && $class !== '') {
                    if (!class_exists($class, false)) {
                        throw new RuntimeException('Module class not declared: ' . $class);
                    }
                    new $class($this->db, $this->logger, $this->request, $this->session, $this->config);
                }
            } catch (Throwable $error) {
                RequestTelemetry::failure(
                    'module.load.failed',
                    $error,
                    sprintf('Module %s failed while loading priority %s.', $name, $priority),
                    [
                        'component' => 'module_loader',
                        'moduleName' => $name,
                        'moduleClass' => is_string($class) ? $class : '',
                        'modulePriority' => $priority,
                        'moduleGroups' => $groups ?? '*',
                        'entrypoint' => $entrypoint,
                        'durationMs' => round((hrtime(true) - $moduleStartedAt) / 1_000_000, 3),
                    ],
                );
                throw $error;
            }

            $moduleDuration = round((hrtime(true) - $moduleStartedAt) / 1_000_000, 3);
            $moduleTimings[$name] = $moduleDuration;
            $included[] = [
                'moduleName' => $name,
                'moduleClass' => $class,
                'modulePriority' => $priority,
                'moduleGroups' => $groups ?? '*',
                'durationMs' => $moduleDuration,
            ];
        }

        $duration = round((hrtime(true) - $startedAt) / 1_000_000, 3);
        $loadedNames = array_values(array_map(
            static fn (array $module): string => (string)$module['moduleName'],
            $included,
        ));
        $skipCount = array_sum($skipped);
        $diagnosticSkipCount = $skipped['invalidDefinition'] + $skipped['groupDenied'];
        if ($loadedNames !== [] || $diagnosticSkipCount > 0) {
            $loadedLabel = $loadedNames !== [] ? implode(', ', $loadedNames) : 'none';
            RequestTelemetry::event(
                'module.phase.completed',
                sprintf(
                    'Module phase %s loaded %d of %d candidates in %.3f ms: %s.',
                    $priority,
                    count($loadedNames),
                    $phaseCandidates,
                    $duration,
                    $loadedLabel,
                ),
                [
                    'component' => 'module_loader',
                    'operation' => 'module.phase.' . $priority,
                    'modulePriority' => $priority,
                    'candidateCount' => $phaseCandidates,
                    'loadedCount' => count($loadedNames),
                    'loadedModules' => $loadedNames,
                    'moduleTimingsMs' => $moduleTimings,
                    'skippedCount' => $skipCount,
                    'skippedModules' => $skipped,
                    'includeNames' => $includeNames ?? '*',
                    'excludeNames' => array_values($excludeNames),
                    'actorGroup' => $this->session->group(),
                    'durationMs' => $duration,
                ],
                'DEBUG',
                'success',
            );
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
        return in_array($this->session->group(), array_map(
            static fn (mixed $group): string => GroupRepository::normalizeTag($group, ''),
            $groups,
        ), true);
    }
}
