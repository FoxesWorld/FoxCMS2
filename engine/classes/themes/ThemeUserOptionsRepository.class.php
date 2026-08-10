<?php

declare(strict_types=1);

/**
 * Runtime parser and atomic storage for theme-owned userOptions TPL files.
 *
 * TPL files contain declarative fox-* metadata plus Vue-compatible HTML inside
 * <fox-template-body>. Executable controllers and component adapters remain
 * whitelisted in the client and on the server.
 */
final class ThemeUserOptionsRepository
{
    private const TEMPLATE_FILES = [
        'profile-settings' => 'ProfileSettings.tpl',
        'admin-panel' => 'AdminPanel.tpl',
    ];
    private const PROFILE_ADAPTERS = [
        'profile' => 'ProfileOption',
        'appearance' => 'AppearanceOption',
        'security' => 'SecurityOption',
    ];
    private const ADMIN_ADAPTERS = [
        'overview' => ['component' => 'Overview', 'tab' => 'overview'],
        'logs' => ['component' => 'Logs', 'tab' => 'logs'],
        'users' => ['component' => 'Users', 'tab' => 'users'],
        'achievements' => ['component' => 'Achievements', 'tab' => 'achievements'],
        'infobox' => ['component' => 'Catalogs', 'tab' => 'catalogs', 'catalog' => 'infobox'],
        'badges' => ['component' => 'Catalogs', 'tab' => 'catalogs', 'catalog' => 'badges'],
        'rewards' => ['component' => 'Rewards', 'tab' => 'rewards'],
        'groups' => ['component' => 'Catalogs', 'tab' => 'catalogs', 'catalog' => 'groups'],
        'content' => ['component' => 'Content', 'tab' => 'content'],
        'slides' => ['component' => 'Slides', 'tab' => 'slides'],
        'settings' => ['component' => 'SiteSettings', 'tab' => 'settings'],
        'hcaptcha' => ['component' => 'HCaptcha', 'tab' => 'hcaptcha'],
        'mail' => ['component' => 'Mail', 'tab' => 'mail'],
        'runtime-options' => ['component' => 'RuntimeOptions', 'tab' => 'runtime-options'],
        'servers' => ['component' => 'Servers', 'tab' => 'servers'],
        'files' => ['component' => 'FileManager', 'tab' => 'files'],
        'maintenance' => ['component' => 'Maintenance', 'tab' => 'maintenance'],
    ];
    private const REQUIRED_ADMIN_ADAPTERS = ['runtime-options'];
    private const ALLOWED_COMPONENTS = [
        'profile-settings' => ['Suspense', 'ProfileOption', 'AppearanceOption', 'SecurityOption'],
        'admin-panel' => [
            'Suspense', 'AdminDashboard', 'AdminCategoryView', 'AdminOverview', 'AdminSiteSettings', 'AdminHCaptcha', 'AdminMail',
            'AdminSlides', 'AdminContent', 'AdminRewards', 'AdminMaintenance', 'AdminUsers', 'AdminAchievements',
            'AdminServers', 'AdminFileManager', 'AdminLogs', 'AdminRuntimeOptions', 'AdminCatalogs',
        ],
    ];

    private string $directory;
    private ThemeRuntimeTplCompiler $compiler;

    public function __construct(string $templatesDirectory, string $themeName)
    {
        $this->directory = ThemeRuntimeTplDocument::resolveDirectory(
            $templatesDirectory,
            $themeName,
            'userOptions',
        );
        $this->compiler = new ThemeRuntimeTplCompiler($templatesDirectory, $themeName);
    }

    /** @return array<string,mixed> */
    public function read(bool $includeSource = true): array
    {
        $profileTemplate = $this->readTemplate('profile-settings', $includeSource);
        $adminTemplate = $this->readTemplate('admin-panel', $includeSource);
        $revision = max((int)$profileTemplate['revision'], (int)$adminTemplate['revision']);
        $updatedAt = max((string)$profileTemplate['updatedAt'], (string)$adminTemplate['updatedAt']);

        return [
            'schema' => 1,
            'revision' => max(1, $revision),
            'updatedAt' => $updatedAt,
            'profile' => ['tabs' => $profileTemplate['profileTabs']],
            'admin' => [
                'categories' => $adminTemplate['adminCategories'],
                'tools' => $adminTemplate['adminTools'],
            ],
            'templates' => [
                'profileSettings' => $this->publicTemplate($profileTemplate, $includeSource),
                'adminPanel' => $this->publicTemplate($adminTemplate, $includeSource),
            ],
        ];
    }

    /** @return array<string,mixed> */
    public function saveTemplate(string $id, string $source): array
    {
        $id = trim($id);
        if (!isset(self::TEMPLATE_FILES[$id])) {
            throw new InvalidArgumentException('Неизвестный runtime TPL userOptions: ' . $id);
        }
        $current = $this->readTemplate($id, true);
        $this->parse($source, $id, true);
        $revision = max(1, (int)$current['revision'] + 1);
        $updatedAt = gmdate('c');
        $source = $this->replaceRootAttribute($source, 'revision', (string)$revision);
        $source = $this->replaceRootAttribute($source, 'updated-at', $updatedAt);
        $parsed = $this->parse($source, $id, true);

        // Publish the immutable render module before switching the TPL revision.
        $this->compiler->publish($id, $revision, (string)$parsed['html']);
        $this->write($this->path($id), rtrim($source) . PHP_EOL);
        $this->compiler->cleanup($id, $revision);
        return $this->read(true);
    }

    public function storageReady(): bool
    {
        return $this->compiler->storageReady() && ThemeRuntimeTplDocument::storageReady(
            $this->directory,
            array_values(self::TEMPLATE_FILES),
        );
    }

    /** @return array<string,mixed> */
    private function readTemplate(string $id, bool $includeSource): array
    {
        $source = ThemeRuntimeTplDocument::readSource($this->path($id), 'Runtime userOptions TPL');
        $template = $this->parse($source, $id, $includeSource);
        $module = $this->compiler->ensure($id, (int)$template['revision'], (string)$template['html']);
        $template['moduleUrl'] = $module['moduleUrl'];
        $template['moduleFile'] = $module['moduleFile'];
        return $template;
    }

    /** @return array<string,mixed> */
    private function parse(string $source, string $expectedId, bool $includeSource): array
    {
        $base = ThemeRuntimeTplDocument::parse(
            $source,
            'fox-user-options-template',
            $expectedId,
            self::TEMPLATE_FILES[$expectedId] ?? '',
            self::ALLOWED_COMPONENTS[$expectedId] ?? [],
            $includeSource,
            false,
        );
        $id = (string)$base['id'];
        $inner = (string)$base['_inner'];
        $template = [
            'id' => $id,
            'file' => $base['file'],
            'revision' => $base['revision'],
            'updatedAt' => $base['updatedAt'],
            'html' => $base['html'],
            'profileTabs' => [],
            'adminCategories' => [],
            'adminTools' => [],
        ];
        if ($includeSource) $template['source'] = $base['source'];

        if ($id === 'profile-settings') {
            $template['profileTabs'] = $this->normalizeProfileTabs(
                $this->elements(ThemeRuntimeTplDocument::block($inner, 'fox-profile-options'), 'fox-profile-option'),
            );
        } else {
            $template['adminCategories'] = $this->normalizeCategories(
                $this->elements(ThemeRuntimeTplDocument::block($inner, 'fox-admin-categories'), 'fox-admin-category'),
            );
            $template['adminTools'] = $this->normalizeTools(
                $this->elements(ThemeRuntimeTplDocument::block($inner, 'fox-admin-tools'), 'fox-admin-tool'),
                array_column($template['adminCategories'], null, 'id'),
            );
        }
        return $template;
    }

    /** @param array<string,mixed> $template @return array<string,mixed> */
    private function publicTemplate(array $template, bool $includeSource): array
    {
        $result = [
            'id' => $template['id'],
            'file' => $template['file'],
            'revision' => $template['revision'],
            'updatedAt' => $template['updatedAt'],
            'moduleUrl' => $template['moduleUrl'],
            'moduleFile' => $template['moduleFile'],
        ];
        if ($includeSource) {
            $result['html'] = $template['html'];
            $result['source'] = $template['source'];
        }
        return $result;
    }

    /** @return list<array<string,string>> */
    private function elements(string $source, string $tag): array
    {
        preg_match_all('/<' . preg_quote($tag, '/') . '\b([^>]*)\/>/u', $source, $matches);
        $result = [];
        foreach ($matches[1] ?? [] as $attributes) {
            $result[] = $this->attributes((string)$attributes);
        }
        return $result;
    }

    /** @return array<string,string> */
    private function attributes(string $source): array
    {
        return ThemeRuntimeTplDocument::attributes($source);
    }

    /** @param list<array<string,string>> $source @return list<array<string,mixed>> */
    private function normalizeProfileTabs(array $source): array
    {
        if (count($source) !== count(self::PROFILE_ADAPTERS)) {
            throw new InvalidArgumentException('ProfileSettings.tpl должен объявлять все поддерживаемые вкладки.');
        }
        $indexed = [];
        foreach ($source as $entry) {
            $id = $this->id((string)($entry['id'] ?? ''), 'вкладки профиля');
            $component = self::PROFILE_ADAPTERS[$id] ?? null;
            if (!is_string($component) || ($entry['component'] ?? '') !== $component || isset($indexed[$id])) {
                throw new InvalidArgumentException('Недопустимый адаптер вкладки профиля: ' . $id);
            }
            $indexed[$id] = [
                'id' => $id,
                'component' => $component,
                'label' => $this->text($entry['label'] ?? '', 80, 'label вкладки ' . $id),
                'description' => $this->text($entry['description'] ?? '', 240, 'description вкладки ' . $id, true),
                'icon' => $this->icon($entry['icon'] ?? ''),
                'order' => $this->order($entry['order'] ?? '100'),
                'enabled' => $this->boolean($entry['enabled'] ?? 'false'),
            ];
        }
        if (array_diff_key(self::PROFILE_ADAPTERS, $indexed) !== []
            || count(array_filter($indexed, static fn(array $entry): bool => $entry['enabled'])) === 0) {
            throw new InvalidArgumentException('Хотя бы одна обязательная вкладка профиля должна быть включена.');
        }
        return $this->ordered(array_values($indexed));
    }

    /** @param list<array<string,string>> $source @return list<array<string,mixed>> */
    private function normalizeCategories(array $source): array
    {
        if ($source === [] || count($source) > 24) {
            throw new InvalidArgumentException('AdminPanel.tpl должен объявлять от 1 до 24 категорий.');
        }
        $indexed = [];
        foreach ($source as $entry) {
            $id = $this->id((string)($entry['id'] ?? ''), 'категории admin');
            if (isset($indexed[$id])) {
                throw new InvalidArgumentException('Повторяющаяся категория admin: ' . $id);
            }
            $indexed[$id] = [
                'id' => $id,
                'label' => $this->text($entry['label'] ?? '', 80, 'label категории ' . $id),
                'description' => $this->text($entry['description'] ?? '', 240, 'description категории ' . $id, true),
                'icon' => $this->icon($entry['icon'] ?? ''),
                'order' => $this->order($entry['order'] ?? '100'),
                'enabled' => $this->boolean($entry['enabled'] ?? 'false'),
            ];
        }
        return $this->ordered(array_values($indexed));
    }

    /** @param list<array<string,string>> $source @param array<string,array<string,mixed>> $categories @return list<array<string,mixed>> */
    private function normalizeTools(array $source, array $categories): array
    {
        if ($source === []) {
            throw new InvalidArgumentException('AdminPanel.tpl должен объявлять хотя бы один поддерживаемый инструмент.');
        }
        $indexed = [];
        foreach ($source as $entry) {
            $id = $this->id((string)($entry['id'] ?? ''), 'инструмента admin');
            $binding = self::ADMIN_ADAPTERS[$id] ?? null;
            $category = $this->id((string)($entry['category'] ?? ''), 'категории инструмента');
            if (!is_array($binding) || isset($indexed[$id]) || !isset($categories[$category])
                || ($entry['component'] ?? '') !== $binding['component']
                || ($entry['tab'] ?? '') !== $binding['tab']) {
                throw new InvalidArgumentException('Недопустимый адаптер admin: ' . $id);
            }
            if (isset($binding['catalog']) && ($entry['catalog'] ?? '') !== $binding['catalog']) {
                throw new InvalidArgumentException('Недопустимый catalog adapter: ' . $id);
            }
            $tool = [
                'id' => $id,
                'component' => $binding['component'],
                'tab' => $binding['tab'],
                'category' => $category,
                'label' => $this->text($entry['label'] ?? '', 80, 'label инструмента ' . $id),
                'description' => $this->text($entry['description'] ?? '', 240, 'description инструмента ' . $id, true),
                'icon' => $this->icon($entry['icon'] ?? ''),
                'order' => $this->order($entry['order'] ?? '100'),
                'enabled' => $id === 'runtime-options' ? true : $this->boolean($entry['enabled'] ?? 'false'),
            ];
            if (isset($binding['catalog'])) {
                $tool['catalog'] = $binding['catalog'];
            }
            if ($id === 'runtime-options') {
                $tool['protected'] = true;
            }
            $indexed[$id] = $tool;
        }
        $missingRequired = array_values(array_diff(self::REQUIRED_ADMIN_ADAPTERS, array_keys($indexed)));
        if ($missingRequired !== []) {
            throw new InvalidArgumentException(
                'В AdminPanel.tpl отсутствуют обязательные инструменты: ' . implode(', ', $missingRequired),
            );
        }
        foreach ($indexed as $tool) {
            if ($tool['enabled'] && !($categories[$tool['category']]['enabled'] ?? false)) {
                throw new InvalidArgumentException('Включённый инструмент находится в отключённой категории: ' . $tool['id']);
            }
        }
        return $this->ordered(array_values($indexed));
    }

    /** @param list<array<string,mixed>> $items @return list<array<string,mixed>> */
    private function ordered(array $items): array
    {
        usort($items, static fn(array $left, array $right): int =>
            [$left['order'], $left['id']] <=> [$right['order'], $right['id']]
        );
        return $items;
    }

    private function id(string $value, string $context): string
    {
        $value = trim($value);
        if (preg_match('/^[a-z][a-z0-9-]{1,63}$/D', $value) !== 1) {
            throw new InvalidArgumentException('Некорректный id ' . $context . ': ' . $value);
        }
        return $value;
    }

    private function text(string $value, int $maximum, string $context, bool $allowEmpty = false): string
    {
        $value = trim($value);
        if ((!$allowEmpty && $value === '') || $this->unicodeLength($value) > $maximum) {
            throw new InvalidArgumentException('Некорректный ' . $context . '.');
        }
        return $value;
    }

    private function unicodeLength(string $value): int
    {
        if (function_exists('mb_strlen')) return mb_strlen($value, 'UTF-8');
        if (function_exists('iconv_strlen')) return (int)iconv_strlen($value, 'UTF-8');
        return preg_match_all('/./us', $value, $matches) ?: strlen($value);
    }

    private function icon(string $value): string
    {
        $value = trim($value);
        if (preg_match('/^fa-[a-z0-9-]{1,63}$/D', $value) !== 1) {
            throw new InvalidArgumentException('Некорректная Font Awesome icon: ' . $value);
        }
        return $value;
    }

    private function order(string $value): int
    {
        if (preg_match('/^\d{1,5}$/D', trim($value)) !== 1) {
            throw new InvalidArgumentException('Некорректный order runtime TPL.');
        }
        return min(10_000, (int)$value);
    }

    private function boolean(string $value): bool
    {
        $normalized = strtolower(trim($value));
        if (!in_array($normalized, ['true', 'false', '1', '0'], true)) {
            throw new InvalidArgumentException('Некорректное boolean-значение runtime TPL.');
        }
        return in_array($normalized, ['true', '1'], true);
    }

    private function timestamp(string $value): string
    {
        $value = trim($value);
        if ($value === '') return '';
        return strtotime($value) === false ? '' : gmdate('c', (int)strtotime($value));
    }

    private function path(string $id): string
    {
        $file = self::TEMPLATE_FILES[$id] ?? null;
        if (!is_string($file)) throw new InvalidArgumentException('Неизвестный runtime TPL: ' . $id);
        return $this->directory . DIRECTORY_SEPARATOR . $file;
    }

    private function replaceRootAttribute(string $source, string $attribute, string $value): string
    {
        return ThemeRuntimeTplDocument::replaceRootAttribute(
            $source,
            'fox-user-options-template',
            $attribute,
            $value,
        );
    }

    private function write(string $path, string $source): void
    {
        ThemeRuntimeTplDocument::write($this->directory, $path, $source, 'user-options');
    }

}
