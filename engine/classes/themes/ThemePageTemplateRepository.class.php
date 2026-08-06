<?php

declare(strict_types=1);

require_once __DIR__ . '/ThemePageStorage.class.php';
require_once __DIR__ . '/ThemeRuntimeTplDocument.class.php';
require_once __DIR__ . '/ThemeRuntimeTplCompiler.class.php';

/** Runtime catalog and atomic storage for theme-owned page TPL files. */
final class ThemePageTemplateRepository
{
    private const ROOT_TAG = 'fox-page-template';
    private const DEFINITIONS = [
        'static-content' => ['file' => 'StaticContent.tpl', 'components' => ['StaticPage', 'Teleport'], 'allowVHtml' => false],
        'start-game' => ['file' => 'StartGame.tpl', 'components' => [], 'allowVHtml' => true],
        'badges' => ['file' => 'Badges.tpl', 'components' => [], 'allowVHtml' => false],
        'badge' => ['file' => 'Badge.tpl', 'components' => [], 'allowVHtml' => true],
        'achievements' => ['file' => 'Achievements.tpl', 'components' => ['Suspense', 'AchievementStatisticsTree'], 'allowVHtml' => false],
        'achievement-statistics' => ['file' => 'achievements/StatisticsTree.tpl', 'components' => ['AchievementTreeNode'], 'allowVHtml' => false],
        'achievement-tree-node' => ['file' => 'achievements/TreeNode.tpl', 'components' => ['RouterLink', 'AchievementTreeNode'], 'allowVHtml' => false],
        'achievement-profile-panel' => ['file' => 'achievements/ProfilePanel.tpl', 'components' => [], 'allowVHtml' => false],
    ];

    private string $directory;
    private ThemeRuntimeTplCompiler $compiler;

    public function __construct(string $templatesDirectory, string $themeName)
    {
        $this->directory = (new ThemePageStorage($templatesDirectory, $themeName))->templatesDirectory();
        $this->compiler = new ThemeRuntimeTplCompiler($templatesDirectory, $themeName);
    }

    public function supports(string $id): bool
    {
        return isset(self::DEFINITIONS[trim($id)]);
    }

    /** @return array{schema:int,revision:int,updatedAt:string,templates:list<array<string,mixed>>} */
    public function read(bool $includeSource = false): array
    {
        $templates = [];
        foreach (array_keys(self::DEFINITIONS) as $id) $templates[] = $this->readTemplate($id, $includeSource);
        $revision = 1;
        $updatedAt = '';
        foreach ($templates as $template) {
            $revision = max($revision, (int)$template['revision']);
            $updatedAt = max($updatedAt, (string)$template['updatedAt']);
        }
        return ['schema' => 1, 'revision' => $revision, 'updatedAt' => $updatedAt, 'templates' => $templates];
    }

    /** @return array<string,mixed> */
    public function template(string $id, bool $includeSource = false): array
    {
        return $this->readTemplate(trim($id), $includeSource);
    }

    /** @return array{schema:int,revision:int,updatedAt:string,templates:list<array<string,mixed>>} */
    public function saveTemplate(string $id, string $source): array
    {
        $id = trim($id);
        if (!$this->supports($id)) throw new InvalidArgumentException('Unknown runtime page TPL: ' . $id);
        $current = $this->readTemplate($id, true);
        $definition = self::DEFINITIONS[$id];
        ThemeRuntimeTplDocument::parse(
            $source,
            self::ROOT_TAG,
            $id,
            (string)$definition['file'],
            $definition['components'],
            true,
            (bool)$definition['allowVHtml'],
        );
        $revision = max(1, (int)$current['revision'] + 1);
        $source = ThemeRuntimeTplDocument::replaceRootAttribute($source, self::ROOT_TAG, 'revision', (string)$revision);
        $source = ThemeRuntimeTplDocument::replaceRootAttribute($source, self::ROOT_TAG, 'updated-at', gmdate('c'));
        $parsed = ThemeRuntimeTplDocument::parse(
            $source,
            self::ROOT_TAG,
            $id,
            (string)$definition['file'],
            $definition['components'],
            true,
            (bool)$definition['allowVHtml'],
        );

        // Publish the immutable revisioned render module first. The TPL switch then becomes atomic.
        $this->compiler->publish($id, $revision, (string)$parsed['html']);
        ThemeRuntimeTplDocument::write($this->directory, $this->path($id), rtrim($source) . PHP_EOL, 'page-template');
        $this->compiler->cleanup($id, $revision);
        return $this->read(true);
    }

    public function storageReady(): bool
    {
        return $this->compiler->storageReady() && ThemeRuntimeTplDocument::storageReady(
            $this->directory,
            array_values(array_map(static fn(array $definition): string => (string)$definition['file'], self::DEFINITIONS)),
        );
    }

    /** @return array<string,mixed> */
    private function readTemplate(string $id, bool $includeSource): array
    {
        $definition = self::DEFINITIONS[$id] ?? null;
        if (!is_array($definition)) throw new InvalidArgumentException('Unknown runtime page TPL: ' . $id);
        $source = ThemeRuntimeTplDocument::readSource($this->path($id), 'Runtime page TPL');
        $template = ThemeRuntimeTplDocument::parse(
            $source,
            self::ROOT_TAG,
            $id,
            (string)$definition['file'],
            $definition['components'],
            $includeSource,
            (bool)$definition['allowVHtml'],
        );
        $module = $this->compiler->ensure($id, (int)$template['revision'], (string)$template['html']);
        $template['moduleUrl'] = $module['moduleUrl'];
        $template['moduleFile'] = $module['moduleFile'];
        unset($template['_inner'], $template['_attributes']);
        if (!$includeSource) unset($template['html']);
        return $template;
    }

    private function path(string $id): string
    {
        $definition = self::DEFINITIONS[$id] ?? null;
        if (!is_array($definition)) throw new InvalidArgumentException('Unknown runtime page TPL: ' . $id);
        return ThemeRuntimeTplDocument::path($this->directory, (string)$definition['file']);
    }
}
