<?php

declare(strict_types=1);

namespace FoxCMS\Engine\Admin;

use FoxCMS\Shared\Routing\ActionDispatcher;

/** Composition root for the legacy admPanel transport. */
final class AdminActionRouterFactory
{
    private const LOG_FILES = ['lastlog', 'error', 'access'];

    public function create(
        array $request,
        \db $db,
        \UserSession $session,
        \Logger $logger,
        \HttpRequest $httpRequest,
        array $config,
        \AdminResponder $responder,
    ): ActionDispatcher {
        $payload = new \AdminRequestPayload($request, $responder);
        $uploads = new \UploadService($db, $session, $logger, $httpRequest);
        $fileManager = new \AdminFileManager($uploads, $session, $logger);
        $badgeSchema = new \AdminBadgeCatalogSchema($db);
        $badgeOptions = new \AdminBadgeOptionsProvider($db, $badgeSchema);
        $groups = new \GroupRepository($db);
        $groupNormalizer = new \AdminGroupListNormalizer($groups);
        $maintenance = new \MaintenanceModeRepository($db);
        $siteSettings = new \SiteSettingsRepository($db);

        $server = new \AdminServerController(
            $db,
            $request,
            $logger,
            $uploads,
            $payload,
            $responder,
            $groups,
            $groupNormalizer,
        );
        $reward = new \AdminRewardController(
            $db,
            $request,
            $session,
            $logger,
            $payload,
            $responder,
            $badgeOptions,
        );
        $user = new \AdminUserController(
            $db,
            $request,
            $session,
            $logger,
            $groups,
            $payload,
            $responder,
            $badgeOptions,
        );
        $achievement = new \AdminAchievementController($db, $request, $session, $logger, $responder);

        $site = is_array($config['siteSettings'] ?? null) ? $config['siteSettings'] : [];
        $template = (string)($site['siteTpl'] ?? '');
        $slides = new \ThemeSlidesRepository(TEMPLATE_DIR, $template);
        $contentRepository = new \ThemeContentRepository(TEMPLATE_DIR, $template);
        $userOptionsRepository = new \ThemeUserOptionsRepository(TEMPLATE_DIR, $template);
        $pageTemplateRepository = new \ThemePageTemplateRepository(TEMPLATE_DIR, $template);
        $badgePageRepository = new \ThemeBadgePageRepository(TEMPLATE_DIR, $template);

        $runtimeOptions = new \AdminRuntimeOptionsController(
            $userOptionsRepository,
            $session,
            $logger,
            $payload,
            $responder,
        );
        $content = new \AdminContentController(
            $db,
            $request,
            $session,
            $logger,
            $contentRepository,
            $pageTemplateRepository,
            $badgePageRepository,
            $badgeSchema,
            $payload,
            $responder,
        );
        $catalog = new \AdminCatalogController(
            $db,
            $request,
            $logger,
            $groups,
            $maintenance,
            $badgePageRepository,
            $badgeSchema,
            $payload,
            $responder,
            $groupNormalizer,
        );
        $system = new AdminSystemController(
            $db,
            $logger,
            $session,
            $request,
            $config,
            $siteSettings,
            $maintenance,
            $groups,
            new \LogQueryService(self::LOG_FILES),
            $payload,
            $responder,
        );
        $theme = new AdminThemeController($request, $logger, $slides, $uploads, $payload, $responder);
        $files = new AdminFileController($request, $fileManager, $responder);

        $router = new ActionDispatcher();
        $router->register('overview', [$system, 'overview'], ['handler' => 'AdminSystemController::overview']);
        $router->register('siteSettings', [$system, 'siteSettings'], ['handler' => 'AdminSystemController::siteSettings']);
        $router->register('saveSiteSettings', [$system, 'saveSiteSettings'], ['handler' => 'AdminSystemController::saveSiteSettings']);
        $router->register('userOptions', [$runtimeOptions, 'userOptions'], ['handler' => 'AdminRuntimeOptionsController::userOptions']);
        $router->register('saveUserOptions', [$runtimeOptions, 'saveUserOptions'], ['handler' => 'AdminRuntimeOptionsController::saveUserOptions']);
        $router->register('users', [$user, 'users'], ['handler' => 'AdminUserController::users']);
        $router->register('updateUser', [$user, 'updateUser'], ['handler' => 'AdminUserController::updateUser']);
        $router->register('grantUserBadge', [$user, 'grantUserBadge'], ['handler' => 'AdminUserController::grantUserBadge']);
        $router->register('revokeUserBadge', [$user, 'revokeUserBadge'], ['handler' => 'AdminUserController::revokeUserBadge']);
        $router->register('achievementsAdmin', [$achievement, 'overview'], ['handler' => 'AdminAchievementController::overview']);
        $router->register('saveAchievementEconomy', [$achievement, 'saveEconomy'], ['handler' => 'AdminAchievementController::saveEconomy']);
        $router->register('clearAchievementServer', [$achievement, 'clearServer'], ['handler' => 'AdminAchievementController::clearServer']);
        $router->register('clearAchievementPlayer', [$achievement, 'clearPlayer'], ['handler' => 'AdminAchievementController::clearPlayer']);
        $router->register('servers', [$server, 'servers'], ['handler' => 'AdminServerController::servers']);
        $router->register('saveServer', [$server, 'saveServer'], ['handler' => 'AdminServerController::saveServer']);
        $router->register('deleteServer', [$server, 'deleteServer'], ['handler' => 'AdminServerController::deleteServer']);
        $router->register('hardware', [$system, 'hardware'], ['handler' => 'AdminSystemController::hardware']);
        $router->register('maintenance', [$system, 'maintenance'], ['handler' => 'AdminSystemController::maintenance']);
        $router->register('saveMaintenance', [$system, 'saveMaintenance'], ['handler' => 'AdminSystemController::saveMaintenance']);
        $router->register('log', [$system, 'showLog'], ['handler' => 'AdminSystemController::showLog']);
        $router->register('clearLog', [$system, 'clearLog'], ['handler' => 'AdminSystemController::clearLog']);
        $router->register('slides', [$theme, 'slides'], ['handler' => 'AdminThemeController::slides']);
        $router->register('saveSlides', [$theme, 'saveSlides'], ['handler' => 'AdminThemeController::saveSlides']);
        $router->register('uploadSlideImage', [$theme, 'uploadSlideImage'], ['handler' => 'AdminThemeController::uploadSlideImage']);
        $router->register('uploadServerImage', [$theme, 'uploadServerImage'], ['handler' => 'AdminThemeController::uploadServerImage']);
        $router->register('uploadSiteSocialImage', [$theme, 'uploadSiteSocialImage'], ['handler' => 'AdminThemeController::uploadSiteSocialImage']);
        $router->register('content', [$content, 'content'], ['handler' => 'AdminContentController::content']);
        $router->register('saveProjectPages', [$content, 'saveProjectPages'], ['handler' => 'AdminContentController::saveProjectPages']);
        $router->register('savePageTemplate', [$content, 'savePageTemplate'], ['handler' => 'AdminContentController::savePageTemplate']);
        $router->register('saveBadgePage', [$content, 'saveBadgePage'], ['handler' => 'AdminContentController::saveBadgePage']);
        $router->register('deleteBadgePage', [$content, 'deleteBadgePage'], ['handler' => 'AdminContentController::deleteBadgePage']);
        $router->register('rewards', [$reward, 'rewards'], ['handler' => 'AdminRewardController::rewards']);
        $router->register('saveReward', [$reward, 'saveReward'], ['handler' => 'AdminRewardController::saveReward']);
        $router->register('deleteReward', [$reward, 'deleteReward'], ['handler' => 'AdminRewardController::deleteReward']);
        $router->register('issueRewardClaimKey', [$reward, 'issueRewardClaimKey'], ['handler' => 'AdminRewardController::issueRewardClaimKey']);
        $router->register('revokeRewardClaimKey', [$reward, 'revokeRewardClaimKey'], ['handler' => 'AdminRewardController::revokeRewardClaimKey']);
        $router->register('fileList', [$files, 'list'], ['handler' => 'AdminFileController::list']);
        $router->register('fileCreateDirectory', [$files, 'createDirectory'], ['handler' => 'AdminFileController::createDirectory']);
        $router->register('fileUpload', [$files, 'upload'], ['handler' => 'AdminFileController::upload']);
        $router->register('fileRename', [$files, 'rename'], ['handler' => 'AdminFileController::rename']);
        $router->register('fileDelete', [$files, 'delete'], ['handler' => 'AdminFileController::delete']);
        $router->register('catalog', [$catalog, 'catalog'], ['handler' => 'AdminCatalogController::catalog']);
        $router->register('saveCatalogEntry', [$catalog, 'saveCatalogEntry'], ['handler' => 'AdminCatalogController::saveCatalogEntry']);
        $router->register('deleteCatalogEntry', [$catalog, 'deleteCatalogEntry'], ['handler' => 'AdminCatalogController::deleteCatalogEntry']);
        return $router;
    }
}
