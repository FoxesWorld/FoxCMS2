<?php

declare(strict_types=1);

namespace FoxCMS\Engine\Launcher;

final class LauncherRequestController
{
    public function __construct(
        private \db $db,
        private \HttpRequest $request,
        private LauncherAccess $access,
        private \PlayTimeService $playTime,
    ) {
    }

    public function startedPlaying(): void
    {
        $this->playTime->start(
            $this->access->authenticatedUserUuid(),
            $this->request->string('serverName'),
            $this->request->string('uuid'),
        );
    }

    public function playing(): void
    {
        $this->playTime->heartbeat(
            $this->access->authenticatedUserUuid(),
            $this->request->string('uuid'),
        );
    }

    public function checkStatus(): void
    {
        $this->playTime->status(
            $this->access->authenticatedUserUuid(),
            $this->request->string('serverName'),
            $this->request->string('uuid'),
        );
    }

    public function donePlaying(): void
    {
        $this->playTime->finish(
            $this->access->authenticatedUserUuid(),
            $this->request->string('serverName'),
            $this->request->string('uuid'),
        );
    }

    public function userData(): never
    {
        $launcher = $this->access->requireAuthenticated();
        $requestedProfile = strtolower($this->request->string('uuid'));
        if ($requestedProfile !== '' && !\Uuid::equals($launcher['userUuid'], $requestedProfile)) {
            throw new \HttpException('Launcher profile mismatch.', 403);
        }

        \UtilityLoader::load('LoadUserInfo', '1.0.0');
        $userData = \LoadUserInfo::byUuid($launcher['userUuid'], $this->db)->userInfoArray();
        $group = (new \GroupRepository($this->db))->find((string)($userData['groupTag'] ?? 'guest'));
        \JsonResponse::send([
            'login' => (string)($userData['login'] ?? ''),
            'realname' => (string)($userData['realname'] ?? ''),
            'colorScheme' => (string)($userData['colorScheme'] ?? ''),
            'userStatus' => (string)($userData['userStatus'] ?? ''),
            'land' => (string)($userData['land'] ?? ''),
            'profilePhoto' => (string)($userData['profilePhoto'] ?? ''),
            'groupTag' => (string)($group['groupTag'] ?? 'guest'),
            'groupName' => (string)($group['groupName'] ?? 'Гость'),
        ]);
    }
}
