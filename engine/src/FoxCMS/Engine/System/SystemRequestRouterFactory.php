<?php

declare(strict_types=1);

namespace FoxCMS\Engine\System;

use FoxCMS\Engine\Launcher\LauncherAccess;
use FoxCMS\Engine\Launcher\LauncherRequestController;
use FoxCMS\Shared\Routing\ActionDispatcher;

/** Composition root for the legacy sysRequest transport. */
final class SystemRequestRouterFactory
{
    public function create(
        \db $db,
        \Logger $logger,
        \HttpRequest $request,
        \UserSession $session,
        array $config,
    ): ActionDispatcher {
        $launcherSessions = new \LauncherSessionService($db, $logger);
        $launcherAccess = new LauncherAccess($request, $session, $launcherSessions);
        $textures = new \UserTextureLocator(ROOT_DIR . UPLOADS_DIR . USR_SUBFOLDER);

        $server = new SystemRequestServerController($db, $logger, $request, $session, $config);
        $media = new SystemRequestMediaController(
            $db,
            $request,
            $session,
            $config,
            new \PublicFileLocator(ROOT_DIR),
            $textures,
        );
        $texture = new SystemRequestTextureController(
            $db,
            $logger,
            $request,
            $session,
            new \UploadService($db, $session, $logger, $request),
            $textures,
        );
        $runtime = new SystemRequestRuntimeController(
            $request,
            $config,
            new \ArtifactRepository(ROOT_DIR, ROOT_DIR . UPLOADS_DIR),
            $launcherSessions,
            $launcherAccess,
            new \HardwareReportService($db, $logger),
        );
        $launcher = new LauncherRequestController(
            $db,
            $request,
            $launcherAccess,
            new \PlayTimeService($db, $logger),
        );

        $router = new ActionDispatcher();
        $router->register('getJre', [$runtime, 'getJre'], ['handler' => 'SystemRequestRuntimeController::getJre']);
        $router->register('parseServers', [$server, 'parseServers'], ['handler' => 'SystemRequestServerController::parseServers']);
        $router->register('getLangPack', [$runtime, 'languagePack'], ['handler' => 'SystemRequestRuntimeController::languagePack']);
        $router->register('parseMonitor', [$server, 'monitor'], ['handler' => 'SystemRequestServerController::monitor']);
        $router->register('topPlayers', [$server, 'topPlayers'], ['handler' => 'SystemRequestServerController::topPlayers']);
        $router->register('infoBox', [$server, 'infoBox'], ['handler' => 'SystemRequestServerController::infoBox']);
        $router->register('skin', [$media, 'skin'], ['handler' => 'SystemRequestMediaController::skin']);
        $router->register('userHead', [$media, 'userHead'], ['handler' => 'SystemRequestMediaController::userHead']);
        $router->register('skinPath', [$media, 'skinPath'], ['handler' => 'SystemRequestMediaController::skinPath']);
        $router->register('skinPreview', [$media, 'skinPreview'], ['handler' => 'SystemRequestMediaController::skinPreview']);
        $router->register('serverImage', [$media, 'serverImage'], ['handler' => 'SystemRequestMediaController::serverImage']);
        $router->register('uploadFile', [$texture, 'upload'], ['handler' => 'SystemRequestTextureController::upload']);
        $router->register('deleteFile', [$texture, 'delete'], ['handler' => 'SystemRequestTextureController::delete']);
        $router->register('loadFiles', [$runtime, 'loadFiles'], ['handler' => 'SystemRequestRuntimeController::loadFiles']);
        $router->register('downloadLatest', [$runtime, 'downloadLatest'], ['handler' => 'SystemRequestRuntimeController::downloadLatest']);
        $router->register('downloadUpdater', [$runtime, 'downloadUpdater'], ['handler' => 'SystemRequestRuntimeController::downloadUpdater']);
        $router->register('startedPlaying', [$launcher, 'startedPlaying'], ['handler' => 'LauncherRequestController::startedPlaying']);
        $router->register('playing', [$launcher, 'playing'], ['handler' => 'LauncherRequestController::playing']);
        $router->register('checkStatus', [$launcher, 'checkStatus'], ['handler' => 'LauncherRequestController::checkStatus']);
        $router->register('donePlaying', [$launcher, 'donePlaying'], ['handler' => 'LauncherRequestController::donePlaying']);
        $router->register('getUserData', [$launcher, 'userData'], ['handler' => 'LauncherRequestController::userData']);
        return $router;
    }
}
