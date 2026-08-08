<?php

declare(strict_types=1);

namespace FoxCMS\Engine\System;

final class SystemRequestTextureController
{
    public function __construct(
        private \db $db,
        private \Logger $logger,
        private \HttpRequest $request,
        private \UserSession $session,
        private \UploadService $uploads,
        private \UserTextureLocator $textures,
    ) {
    }

    public function upload(): never
    {
        if (!$this->session->isLogged()) {
            throw new \HttpException('Authentication required.', 401);
        }
        $type = $this->request->string('type');
        $purpose = match ($type) {
            'skin' => \UploadPurpose::MINECRAFT_SKIN,
            'cloak' => \UploadPurpose::MINECRAFT_CAPE,
            default => throw new \InvalidArgumentException('Unknown file type.'),
        };
        $targetUuid = $this->resolveMutationUserUuid(false);
        try {
            $result = $this->uploads->store(
                $purpose,
                $this->request->file('0') ?? $this->request->file('file'),
                ['ownerUuid' => $targetUuid],
            );
        } catch (\UploadException $error) {
            throw new \HttpException($error->getMessage(), $error->httpStatus(), [], $error);
        }

        \JsonResponse::send([
            'message' => $type === 'skin' ? 'Скин загружен.' : 'Плащ загружен.',
            'type' => 'success',
            'upload' => $result,
        ]);
    }

    public function delete(): never
    {
        global $lang;

        $this->requireBrowserMutation();
        $type = $this->request->string('type');
        $targetUuid = $this->resolveMutationUserUuid();
        $files = $this->textures->locate($targetUuid);
        $path = match ($type) {
            'skin' => $files['skin'],
            'cloak' => $files['cape'],
            default => null,
        };
        if (!is_string($path)) {
            throw new \InvalidArgumentException('Unknown file type.');
        }

        $label = $type === 'skin'
            ? ($lang['userProfile']['skin'] ?? 'skin')
            : ($lang['userProfile']['cape'] ?? 'cape');
        if (!is_file($path)) {
            throw new \HttpException('У вас нет ' . $label . '.', 404);
        }
        if (is_link($path) || !unlink($path)) {
            throw new \RuntimeException('Unable to delete user texture.');
        }
        $this->logger->event('user.texture.deleted', 'User texture deleted.', [
            'component' => 'user_texture',
            'operation' => 'delete',
            'targetUserUuid' => $targetUuid,
            'textureType' => $type,
        ], 'INFO', 'success');
        \JsonResponse::send(['message' => ucfirst((string)$label) . ' удалён.', 'type' => 'success']);
    }

    private function resolveMutationUserUuid(bool $authorize = true): string
    {
        $requestedUuid = $this->request->string('userUuid');
        $targetIdentity = $requestedUuid !== '' ? $requestedUuid : $this->session->uuid();
        if (!\Uuid::isValid($targetIdentity)) {
            throw new \InvalidArgumentException('Invalid user UUID.');
        }
        $targetIdentity = \Uuid::normalize($targetIdentity);
        if ($authorize && !$this->session->isAdmin()
            && !\Uuid::equals($this->session->uuid(), $targetIdentity)) {
            throw new \HttpException('Insufficient rights.', 403);
        }

        $placeholders = [];
        $parameters = [];
        foreach (\Uuid::databaseCandidates($targetIdentity) as $index => $candidate) {
            $placeholder = ':userUuid_' . $index;
            $placeholders[] = $placeholder;
            $parameters[$placeholder] = $candidate;
        }
        $statement = $this->db->prepare(
            'SELECT `uuid` FROM `users` WHERE `uuid` IN (' . implode(', ', $placeholders) . ') LIMIT 1'
        );
        $statement->execute($parameters);
        $storedUuid = $statement->fetchColumn();
        if (!is_string($storedUuid) || !\Uuid::isValid($storedUuid)) {
            throw new \HttpException('User not found.', 404);
        }
        return \Uuid::normalize($storedUuid);
    }

    private function requireBrowserMutation(): void
    {
        if (!$this->session->isLogged()) {
            throw new \HttpException('Authentication required.', 401);
        }
        \CsrfToken::requireValid($this->request->csrfToken());
    }
}
