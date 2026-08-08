<?php

declare(strict_types=1);

namespace FoxCMS\Engine\System;

use FoxCMS\Engine\Launcher\LauncherAccess;

final class SystemRequestRuntimeController
{
    public function __construct(
        private \HttpRequest $request,
        private array $config,
        private \ArtifactRepository $artifacts,
        private \LauncherSessionService $launcherSessions,
        private LauncherAccess $launcherAccess,
        private \HardwareReportService $hardwareReports,
    ) {
    }

    public function getJre(): never
    {
        \UtilityLoader::load('GetJre', '1.0.0');
        $runtime = new \GetJre(
            $this->request->string('jreVersion'),
            $this->request->string('platform'),
            $this->config,
        );
        \JsonResponse::send($runtime->jsonSerialize());
    }

    public function languagePack(): never
    {
        global $lang;

        $key = $this->request->string('langPackKey');
        if (preg_match('/^[A-Za-z0-9_.-]{1,64}$/D', $key) !== 1 || !array_key_exists($key, $lang)) {
            throw new \HttpException('Language entry not found.', 404);
        }
        \JsonResponse::send(['key' => $key, 'value' => $lang[$key]]);
    }

    public function loadFiles(): never
    {
        $scanner = new \GameScanner(
            $this->request->string('client'),
            $this->request->string('version'),
            $this->request->integer('platform', 0),
            $this->config,
        );
        $scanner->scan();
        \JsonResponse::rawJson($scanner->toJson());
    }

    public function downloadLatest(): never
    {
        $platform = $this->safeDirectorySegment($this->request->string('platform'), 'platform');
        \JsonResponse::send($this->artifacts->latest('uploads/files/launcher/' . $platform, ['jar']));
    }

    public function downloadUpdater(): never
    {
        $type = $this->safeDirectorySegment($this->request->string('type'), 'updater type');
        $version = $this->request->string('version');
        $root = $version === ''
            ? 'uploads/updater/' . $type
            : 'uploads/files/updater/' . $type;

        $systemInformation = $this->request->string('systemInformation');
        if ($systemInformation !== '') {
            $launcher = $this->launcherSessions->authenticate($this->launcherAccess->token());
            if ($launcher !== null) {
                $this->hardwareReports->store($systemInformation, $launcher['userUuid']);
            }
        }

        \JsonResponse::send($this->artifacts->latest($root, ['jar', 'exe', 'zip', 'msi', 'AppImage']));
    }

    private function safeDirectorySegment(string $value, string $name): string
    {
        if (preg_match('/^[A-Za-z0-9_-]{1,32}$/D', $value) !== 1) {
            throw new \InvalidArgumentException('Invalid ' . $name . '.');
        }
        return $value;
    }
}
