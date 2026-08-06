<?php

declare(strict_types=1);

/**
 * Owns editable project pages and badge-page administration.
 */
final class AdminContentController
{
    public function __construct(
        private db $db,
        private array $request,
        private UserSession $session,
        private Logger $logger,
        private ThemeContentRepository $contentRepository,
        private ThemePageTemplateRepository $pageTemplateRepository,
        private ThemeBadgePageRepository $badgePageRepository,
        private AdminBadgeCatalogSchema $badgeCatalogSchema,
        private AdminRequestPayload $payload,
        private AdminResponder $responder,
    ) {
    }

    public function content(): void {
        $this->badgeCatalogSchema->assertAvailable();
        $statement = $this->db->prepare(
            'SELECT `id`, `badgeName`, `description`, `img` FROM `badgesList` ORDER BY `badgeName`, `id`'
        );
        $statement->execute();
        $badges = BadgeSlug::assign($statement->fetchAll(PDO::FETCH_ASSOC) ?: []);
        $badgePages = [];

        foreach ($badges as &$badge) {
            $slug = (string)($badge['pageSlug'] ?? '');
            $configured = $slug !== '' && $this->badgePageRepository->exists($slug);
            $badge['pageConfigured'] = $configured;
            if (!$configured) {
                continue;
            }
            try {
                $page = $this->badgePageRepository->read($slug);
                if (is_array($page)) {
                    $page['badgeName'] = (string)($badge['badgeName'] ?? '');
                    $page['slug'] = $slug;
                    $badgePages[] = $page;
                }
            } catch (Throwable $error) {
                $badge['pageConfigured'] = false;
                $this->logger->deviation(
                    'theme.content.badge_html.invalid',
                    'badge_html_invalid',
                    'Invalid badge HTML page was skipped in administrative content.',
                    'warning',
                    ['pageValid' => true],
                    ['pageValid' => false],
                    [
                        'component' => 'theme_content',
                        'badgeName' => (string)($badge['badgeName'] ?? ''),
                        'slug' => $slug,
                        'reason' => $error->getMessage(),
                    ],
                );
            }
        }
        unset($badge);

        $this->responder->send([
            'projectPages' => $this->contentRepository->readProjectPages(),
            'pageTemplates' => $this->pageTemplateRepository->read(true),
            'pageTemplatesStorageReady' => $this->pageTemplateRepository->storageReady(),
            'systemPages' => [
                [
                    'id' => 'achievements',
                    'title' => 'Достижения',
                    'description' => 'Общая статистика, дерево достижений и страницы прогресса игроков.',
                    'route' => '/achievements?view=statistics',
                    'routeName' => 'achievements',
                    'view' => 'AchievementsView',
                    'source' => 'engine/client/views/AchievementsView.vue',
                    'capability' => 'game.achievements',
                    'editable' => false,
                ],
            ],
            'badgePages' => ['pages' => $badgePages],
            'badges' => array_values($badges),
        ]);
    }

    public function saveProjectPages(): void {
        $payload = $this->payload->object('entry');
        try {
            $document = $this->contentRepository->saveProjectPages($payload);
        } catch (InvalidArgumentException $error) {
            $this->responder->send(['message' => $error->getMessage(), 'type' => 'error'], 400);
        }
        $this->logger->event(
            'theme.content.project_pages.saved',
            'Theme project pages saved.',
            [
                'component' => 'theme_content',
                'operation' => 'save_project_pages',
                'pagesCount' => count($document['pages'] ?? []),
            ],
            'INFO',
            'success',
        );
        $this->responder->send([
            'message' => 'HTML-страницы проекта сохранены в pages/content.',
            'type' => 'success',
            'document' => $document,
        ]);
    }

    public function savePageTemplate(): void {
        $entry = $this->payload->object('entry');
        $templateId = trim((string)($entry['templateId'] ?? ''));
        $source = (string)($entry['source'] ?? '');
        try {
            $document = $this->pageTemplateRepository->saveTemplate($templateId, $source);
        } catch (InvalidArgumentException $error) {
            $this->responder->send(['message' => $error->getMessage(), 'type' => 'error'], 400);
        } catch (RuntimeException $error) {
            $this->logger->exception(
                'theme.pages.template.compile_failed',
                $error,
                'Runtime page TPL compilation failed; the previous revision remains active.',
                [
                    'component' => 'theme_pages',
                    'operation' => 'compile_page_template',
                    'templateId' => $templateId,
                    'actorUuid' => $this->session->uuid(),
                ],
            );
            $this->responder->send([
                'message' => 'Компилятор runtime-шаблонов недоступен. Предыдущая ревизия страницы остаётся активной.',
                'type' => 'error',
            ], 503);
        }
        $this->logger->event(
            'theme.pages.template.saved',
            'Theme page runtime TPL saved.',
            [
                'component' => 'theme_pages',
                'operation' => 'save_page_template',
                'templateId' => $templateId,
                'revision' => (int)($document['revision'] ?? 0),
                'templatesCount' => count($document['templates'] ?? []),
                'actorUuid' => $this->session->uuid(),
            ],
            'INFO',
            'success',
        );
        $this->responder->send([
            'message' => 'Runtime-шаблон страницы сохранён в pages/templates и опубликован без пересборки frontend chunks.',
            'type' => 'success',
            'document' => $document,
            'storageReady' => $this->pageTemplateRepository->storageReady(),
        ]);
    }

    public function saveBadgePage(): void {
        $this->badgeCatalogSchema->assertAvailable();
        $payload = $this->payload->object('entry');
        $requestedName = trim((string)($payload['badgeName'] ?? ''));
        $statement = $this->db->prepare(
            'SELECT `id`, `badgeName`, `description`, `img` FROM `badgesList` ORDER BY `badgeName`, `id`'
        );
        $statement->execute();
        $badges = BadgeSlug::assign($statement->fetchAll(PDO::FETCH_ASSOC) ?: []);
        $badge = null;
        foreach ($badges as $candidate) {
            if (is_array($candidate) && hash_equals((string)($candidate['badgeName'] ?? ''), $requestedName)) {
                $badge = $candidate;
                break;
            }
        }
        if (!is_array($badge)) {
            $this->responder->send(['message' => 'Бейдж для HTML-страницы не найден в badgesList.', 'type' => 'error'], 404);
        }

        $slug = (string)($badge['pageSlug'] ?? '');
        try {
            $page = $this->badgePageRepository->save(
                $payload,
                (string)$badge['badgeName'],
                $slug,
            );
        } catch (InvalidArgumentException $error) {
            $this->logger->deviation(
                'theme.content.badge_html.rejected',
                'badge_html_validation_failed',
                'Badge HTML page validation rejected the document.',
                'notice',
                ['pageValid' => true],
                ['pageValid' => false],
                [
                    'component' => 'theme_content',
                    'badgeName' => (string)$badge['badgeName'],
                    'slug' => $slug,
                    'reason' => $error->getMessage(),
                ],
            );
            $this->responder->send(['message' => $error->getMessage(), 'type' => 'error'], 400);
        } catch (RuntimeException $error) {
            $this->logger->exception(
                'theme.content.badge_html.storage_failed',
                $error,
                'Badge HTML page storage failed.',
                [
                    'component' => 'theme_content',
                    'badgeName' => (string)$badge['badgeName'],
                    'slug' => $slug,
                ],
            );
            $this->responder->send(['message' => $error->getMessage(), 'type' => 'error'], 500);
        }
        $this->logger->event(
            'theme.content.badge_html.saved',
            'Individual theme badge HTML page saved.',
            [
                'component' => 'theme_content',
                'operation' => 'save_badge_page',
                'badgeName' => (string)$page['badgeName'],
                'slug' => (string)$page['slug'],
                'file' => 'data/badges/' . (string)$page['slug'] . '.html',
            ],
            'INFO',
            'success',
        );
        $this->responder->send([
            'message' => 'HTML-страница бейджа сохранена.',
            'type' => 'success',
            'page' => $page,
        ]);
    }

    public function deleteBadgePage(): void {
        $slug = trim((string)($this->request['slug'] ?? ''));
        try {
            $this->badgePageRepository->delete($slug);
        } catch (InvalidArgumentException $error) {
            $this->responder->send(['message' => $error->getMessage(), 'type' => 'error'], 400);
        }
        $this->logger->event(
            'theme.content.badge_html.deleted',
            'Individual theme badge HTML page deleted.',
            [
                'component' => 'theme_content',
                'operation' => 'delete_badge_page',
                'slug' => $slug,
                'file' => 'data/badges/' . $slug . '.html',
            ],
            'INFO',
            'success',
        );
        $this->responder->send([
            'message' => 'HTML-файл страницы удалён. Запись бейджа в БД сохранена.',
            'type' => 'success',
            'slug' => $slug,
        ]);
    }
}
