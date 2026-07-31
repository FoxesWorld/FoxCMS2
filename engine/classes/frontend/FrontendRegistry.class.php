<?php

declare(strict_types=1);

final class FrontendRegistry
{
    private array $routes = [];
    private array $navigation = [];
    private array $legacy = [];
    private array $capabilities = [];
    private array $endpoints = [];
    private array $modules = [];

    public function __construct(
        private UserSession $session,
        string $themeManifestPath,
        string $modulesManifestPath,
    ) {
        $this->modules = $this->loadModules($modulesManifestPath);
        $owner = basename(dirname($themeManifestPath));
        $this->mergeManifest($this->readManifest($themeManifestPath), $owner);
    }

    public function manifest(): array
    {
        $routeNames = array_fill_keys(array_column($this->routes, 'name'), true);
        $navigation = array_values(array_filter(
            $this->navigation,
            static fn(array $item): bool => isset($item['action']) || isset($routeNames[$item['route'] ?? ''])
        ));
        usort($navigation, static fn(array $left, array $right): int =>
            [$left['area'], $left['order'], $left['label']] <=> [$right['area'], $right['order'], $right['label']]
        );
        $legacy = array_values(array_filter(
            $this->legacy,
            static fn(array $alias): bool => isset($routeNames[$alias['route'] ?? ''])
        ));

        return [
            'routes' => array_values($this->routes),
            'navigation' => $navigation,
            'legacy' => $legacy,
            'capabilities' => array_values(array_keys($this->capabilities)),
            'endpoints' => $this->endpoints,
        ];
    }

    private function mergeManifest(array $manifest, string $owner): void
    {
        if (($manifest['schema'] ?? null) !== 1) {
            throw new RuntimeException('Unsupported theme frontend manifest schema for ' . $owner);
        }

        foreach (($manifest['routes'] ?? []) as $route) {
            if (!is_array($route) || !$this->visible($route, $owner)) {
                continue;
            }
            $definitionOwner = $this->definitionOwner($route, $owner);
            $normalized = $this->normalizeRoute($route, $definitionOwner);
            foreach ($this->routes as $registered) {
                if ($registered['name'] === $normalized['name'] || $registered['path'] === $normalized['path']) {
                    throw new RuntimeException('Duplicate frontend route in ' . $owner . ': ' . $normalized['name']);
                }
            }
            $this->routes[] = $normalized;
        }

        foreach (($manifest['navigation'] ?? []) as $item) {
            if (!is_array($item) || !$this->visible($item, $owner)) {
                continue;
            }
            $this->navigation[] = $this->normalizeNavigation(
                $item,
                $this->definitionOwner($item, $owner)
            );
        }

        foreach (($manifest['legacy'] ?? []) as $legacy) {
            if (!is_array($legacy) || !$this->visible($legacy, $owner)) {
                continue;
            }
            unset($legacy['module'], $legacy['auth'], $legacy['groups']);
            $this->legacy[] = $legacy;
        }

        foreach (($manifest['capabilities'] ?? []) as $capability) {
            if (is_string($capability)) {
                $this->registerCapability($capability, $owner);
                continue;
            }
            if (!is_array($capability) || !$this->visible($capability, $owner)) {
                continue;
            }
            $this->registerCapability((string)($capability['name'] ?? ''), $owner);
        }

        foreach (($manifest['endpoints'] ?? []) as $name => $endpoint) {
            if (is_string($name) && is_string($endpoint) && str_starts_with($endpoint, '/')) {
                $this->endpoints[$name] = $endpoint;
            }
        }
    }

    private function normalizeRoute(array $route, string $owner): array
    {
        $name = (string)($route['name'] ?? '');
        $path = (string)($route['path'] ?? '');
        $view = (string)($route['view'] ?? '');
        $redirect = (string)($route['redirect'] ?? '');

        if (preg_match('/^[A-Za-z][A-Za-z0-9._-]{0,63}$/D', $name) !== 1) {
            throw new RuntimeException('Invalid frontend route name in ' . $owner);
        }
        if ($path === '' || !str_starts_with($path, '/') || str_contains($path, '<') || str_contains($path, '>')) {
            throw new RuntimeException('Invalid frontend route path in ' . $owner . ': ' . $name);
        }
        if ($redirect === '' && preg_match('/^[A-Za-z][A-Za-z0-9]*View$/D', $view) !== 1) {
            throw new RuntimeException('Invalid frontend view in ' . $owner . ': ' . $name);
        }
        if ($redirect !== '' && !str_starts_with($redirect, '/')) {
            throw new RuntimeException('Invalid frontend redirect in ' . $owner . ': ' . $name);
        }

        $normalized = [
            'path' => $path,
            'name' => $name,
            'title' => (string)($route['title'] ?? ''),
            'owner' => $owner,
        ];
        if ($redirect !== '') {
            $normalized['redirect'] = $redirect;
        } else {
            $normalized['view'] = $view;
        }
        if (isset($route['props']) && (is_bool($route['props']) || is_array($route['props']))) {
            $normalized['props'] = $route['props'];
        }
        return $normalized;
    }

    private function normalizeNavigation(array $item, string $owner): array
    {
        $area = (string)($item['area'] ?? 'header');
        $label = trim((string)($item['label'] ?? ''));
        $route = (string)($item['route'] ?? '');
        $action = (string)($item['action'] ?? '');
        if (preg_match('/^[a-z][a-z0-9-]{1,31}$/D', $area) !== 1 || $label === '') {
            throw new RuntimeException('Invalid frontend navigation item in ' . $owner);
        }
        if ($route === '' && $action === '') {
            throw new RuntimeException('Navigation item has no route or action in ' . $owner);
        }

        $normalized = [
            'area' => $area,
            'label' => $label,
            'order' => (int)($item['order'] ?? 100),
            'owner' => $owner,
        ];
        foreach (['route', 'action', 'intent'] as $field) {
            if (isset($item[$field]) && is_string($item[$field]) && $item[$field] !== '') {
                $normalized[$field] = $item[$field];
            }
        }
        if (isset($item['paramsFromUser']) && is_array($item['paramsFromUser'])) {
            $normalized['paramsFromUser'] = $item['paramsFromUser'];
        }
        return $normalized;
    }

    private function visible(array $definition, string $owner): bool
    {
        if (!$this->moduleAllowed($definition, $owner)) {
            return false;
        }

        $auth = (string)($definition['auth'] ?? 'any');
        if (!in_array($auth, ['any', 'guest', 'user'], true)) {
            throw new RuntimeException('Invalid frontend auth rule in ' . $owner);
        }
        if ($auth === 'guest' && $this->session->isLogged()) {
            return false;
        }
        if ($auth === 'user' && !$this->session->isLogged()) {
            return false;
        }
        return $this->groupsAllowed($definition['groups'] ?? null);
    }

    private function moduleAllowed(array $definition, string $owner): bool
    {
        if (!array_key_exists('module', $definition)) {
            return true;
        }

        $module = (string)$definition['module'];
        if (preg_match('/^[A-Za-z][A-Za-z0-9]*$/D', $module) !== 1) {
            throw new RuntimeException('Invalid frontend module reference in ' . $owner);
        }
        if (!isset($this->modules[$module])) {
            throw new RuntimeException('Unknown frontend module in ' . $owner . ': ' . $module);
        }
        return $this->groupsAllowed($this->modules[$module]['groups'] ?? null);
    }

    private function definitionOwner(array $definition, string $fallback): string
    {
        $module = $definition['module'] ?? null;
        return is_string($module) && $module !== '' ? $module : $fallback;
    }

    private function groupsAllowed(mixed $groups): bool
    {
        if ($groups === null || $groups === '*') {
            return true;
        }
        if (!is_array($groups)) {
            $groups = [$groups];
        }
        return in_array($this->session->group(), array_map('intval', $groups), true);
    }

    private function registerCapability(string $capability, string $owner): void
    {
        if (preg_match('/^[a-z][a-z0-9.-]{1,63}$/D', $capability) !== 1) {
            throw new RuntimeException('Invalid frontend capability in ' . $owner);
        }
        $this->capabilities[$capability] = true;
    }

    private function loadModules(string $path): array
    {
        $modules = $this->readJsonArray($path);
        if (!array_is_list($modules)) {
            throw new RuntimeException('Backend module manifest must be a JSON array: ' . $path);
        }

        $indexed = [];
        foreach ($modules as $module) {
            if (!is_array($module)) {
                throw new RuntimeException('Backend module manifest contains a non-object entry: ' . $path);
            }
            $name = (string)($module['name'] ?? '');
            if (preg_match('/^[A-Za-z][A-Za-z0-9]*$/D', $name) !== 1) {
                throw new RuntimeException('Invalid backend module name in manifest: ' . $path);
            }
            if (isset($indexed[$name])) {
                throw new RuntimeException('Duplicate backend module name in manifest: ' . $name);
            }
            $indexed[$name] = $module;
        }
        return $indexed;
    }

    private function readManifest(string $path): array
    {
        $manifest = $this->readJsonArray($path);
        if (array_is_list($manifest)) {
            throw new RuntimeException('Theme frontend manifest must be a JSON object: ' . $path);
        }
        return $manifest;
    }

    private function readJsonArray(string $path): array
    {
        $json = is_file($path) ? file_get_contents($path) : false;
        if ($json === false) {
            throw new RuntimeException('Manifest is unavailable: ' . $path);
        }
        $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($data)) {
            throw new RuntimeException('Manifest must contain JSON array or object: ' . $path);
        }
        return $data;
    }
}
