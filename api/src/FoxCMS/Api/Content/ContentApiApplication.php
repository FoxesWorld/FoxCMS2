<?php

declare(strict_types=1);

namespace FoxCMS\Api\Content;

use FoxCMS\Api\Core\ApplicationContext;
use FoxCMS\Api\Core\DatabaseFactory;
use FoxCMS\Api\Core\HttpException;
use FoxCMS\Api\Core\JsonResponse;
use FoxCMS\Api\Core\Request;
use FoxCMS\Api\Core\RequestId;
use Throwable;

final class ContentApiApplication
{
    private const CACHE_CONTROL = 'public, max-age=0, must-revalidate';
    private const BADGE_SLUG_PATTERN = '/^[a-z0-9][a-z0-9-]{0,79}$/D';

    public function __construct(
        private readonly ApplicationContext $context,
        private readonly Request $request,
    ) {
    }

    public function run(): never
    {
        $registry = 'unknown';
        try {
            $this->request->requireMethod('GET', 'HEAD');
            $registry = trim((string)$this->request->query('registry'));
            $this->requireRegistryDependencies($registry);

            $site = is_array($this->context->config()['siteSettings'] ?? null)
                ? $this->context->config()['siteSettings']
                : [];
            $themeName = (string)($site['siteTpl'] ?? '');

            match ($registry) {
                'project-pages', 'static-pages' => $this->projectPages(
                    new \ThemeContentRepository(TEMPLATE_DIR, $themeName),
                ),
                'user-options' => $this->respond((new \ThemeUserOptionsRepository(TEMPLATE_DIR, $themeName))->read(false)),
                'page-templates' => $this->respond((new \ThemePageTemplateRepository(TEMPLATE_DIR, $themeName))->read(false)),
                'emoticons' => $this->respond((new \ThemeEmoticonRepository(TEMPLATE_DIR, $themeName))->catalog()),
                'badges' => $this->badges(new \ThemeBadgePageRepository(TEMPLATE_DIR, $themeName)),
                'badge' => $this->badge(new \ThemeBadgePageRepository(TEMPLATE_DIR, $themeName)),
                default => throw new HttpException(404, 'content_registry_not_found', 'Content registry not found.'),
            };
        } catch (HttpException $error) {
            JsonResponse::error($error->errorCode(), $error->getMessage(), $error->statusCode(), $error->details());
        } catch (Throwable $error) {
            $requestId = RequestId::create();
            \FoxCMS\Api\Core\FatalResponse::send(
                $error,
                $this->context,
                'content_registry_unavailable',
                503,
                $requestId,
                ['registry' => $registry],
            );
        }
    }

    private function requireRegistryDependencies(string $registry): void
    {
        $files = match ($registry) {
            'project-pages', 'static-pages' => [
                'classes/themes/ThemePageStorage.class.php',
                'classes/themes/ThemeContentRepository.class.php',
            ],
            'user-options' => [
                'classes/themes/ThemeRuntimeTplDocument.class.php',
                'classes/themes/ThemeRuntimeTplCompiler.class.php',
                'classes/themes/ThemeUserOptionsRepository.class.php',
            ],
            'page-templates' => [
                'classes/themes/ThemePageStorage.class.php',
                'classes/themes/ThemeRuntimeTplDocument.class.php',
                'classes/themes/ThemeRuntimeTplCompiler.class.php',
                'classes/themes/ThemePageTemplateRepository.class.php',
            ],
            'emoticons' => [
                'classes/themes/ThemeEmoticonRepository.class.php',
            ],
            'badges', 'badge' => [
                'classes/syslib/database.php',
                'classes/themes/BadgeSlug.class.php',
                'classes/themes/ThemeBadgePageRepository.class.php',
            ],
            default => [],
        };

        if ($files !== []) {
            $this->context->requireEngine(...$files);
        }
    }

    private function projectPages(\ThemeContentRepository $repository): never
    {
        $document = $repository->readProjectPages();
        $indexed = [];
        foreach ($document['pages'] ?? [] as $page) {
            if (is_array($page) && is_string($page['id'] ?? null)) {
                $indexed[$page['id']] = $page;
            }
        }
        $this->respond($indexed);
    }

    private function badges(\ThemeBadgePageRepository $repository): never
    {
        $service = new BadgeCatalogService(
            DatabaseFactory::create($this->context->config()),
            $repository,
        );
        $this->respond($service->catalog());
    }

    private function badge(\ThemeBadgePageRepository $repository): never
    {
        $slug = trim((string)$this->request->query('id'));
        if (preg_match(self::BADGE_SLUG_PATTERN, $slug) !== 1) {
            throw new HttpException(400, 'invalid_badge_slug', 'Badge slug is invalid.');
        }
        $service = new BadgeCatalogService(
            DatabaseFactory::create($this->context->config()),
            $repository,
        );
        $badge = $service->page($slug);
        if (!is_array($badge)) {
            throw new HttpException(404, 'badge_page_not_found', 'Badge page not found.');
        }
        $this->respond($badge);
    }

    private function respond(mixed $payload): never
    {
        JsonResponse::send(
            $payload,
            headers: ['Cache-Control' => self::CACHE_CONTROL],
            conditional: true,
        );
    }
}
