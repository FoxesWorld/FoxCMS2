<?php

declare(strict_types=1);

final class UpdateProfilePhoto
{
    private UploadService $uploads;

    public function __construct(
        private db $db,
        private HttpRequest $request,
        private UserSession $session,
        private Logger $logger,
    ) {
        $this->uploads = new UploadService($db, $session, $logger, $request);
    }

    public function upload(): never
    {
        RequestTelemetry::identify('profile.photo.update', ['component' => 'profile']);
        $target = $this->resolveTarget();
        try {
            $result = $this->uploads->store(
                UploadPurpose::PROFILE_PHOTO,
                $this->request->file('image'),
                ['ownerUuid' => (string)$target['uuid']],
            );
        } catch (UploadException $error) {
            $this->respond($error->getMessage(), 'error', null, $error->httpStatus());
        }

        $targetUuid = (string)$target['uuid'];
        $storageUuid = (string)$target['storageUuid'];
        $publicPath = $result->publicPath();
        $currentPath = (string)($target['profilePhoto'] ?? '');

        try {
            $statement = $this->db->prepare(
                'UPDATE `users` SET `profilePhoto` = :path WHERE `uuid` = :userUuid'
            );
            $statement->execute([
                ':path' => $publicPath,
                ':userUuid' => $storageUuid,
            ]);
            if ($statement->rowCount() !== 1) {
                throw new RuntimeException('Profile row was not updated.');
            }
        } catch (Throwable $error) {
            @unlink($result->absolutePath());
            $this->logger->exception(
                'profile.photo.commit_failed',
                $error,
                'Profile photo database update failed.',
                [
                    'component' => 'profile',
                    'targetUserUuid' => $targetUuid,
                    'target' => $result->relativePath(),
                ],
            );
            $requestId = RequestTelemetry::requestId();
            if ($requestId === '') {
                $requestId = ExceptionContext::requestId('profile-photo');
            }
            JsonResponse::send(
                \FoxCMS\Shared\Error\ThrowableDiagnostic::payload(
                    $error,
                    $requestId,
                    defined('ROOT_DIR') ? ROOT_DIR : '',
                    false,
                    ['type' => 'error', 'operation' => 'profile_photo_update'],
                ),
                500,
            );
        }

        if ($currentPath !== '' && $currentPath !== $publicPath) {
            try {
                $this->uploads->removeReference(
                    UploadPurpose::PROFILE_PHOTO,
                    $currentPath,
                    ['ownerUuid' => $targetUuid],
                );
            } catch (UploadException $error) {
                $this->logger->deviation(
                    'profile.photo.cleanup_failed',
                    'previous_profile_photo_not_removed',
                    'Previous profile photo could not be removed after replacement.',
                    'warning',
                    ['previousFileRemoved' => true],
                    ['previousFileRemoved' => false],
                    [
                        'component' => 'profile',
                        'targetUserUuid' => $targetUuid,
                        'path' => $currentPath,
                        'reason' => $error->getMessage(),
                    ],
                );
            }
        }

        if (Uuid::equals($this->session->uuid(), $targetUuid)) {
            $this->session->set('profilePhoto', $publicPath, true);
        }

        $this->logger->event(
            'profile.photo.updated',
            'Profile photo updated.',
            [
                'component' => 'profile',
                'operation' => 'update_photo',
                'targetUserUuid' => $targetUuid,
                'path' => $result->relativePath(),
            ],
            'INFO',
            'success',
        );
        $this->respond('Фото профиля обновлено.', 'success', $publicPath . '?v=' . time());
    }

    /** @return array{uuid:string,storageUuid:string,profilePhoto:string|null} */
    private function resolveTarget(): array
    {
        $sessionUuid = $this->session->uuid();
        if (!Uuid::isValid($sessionUuid)) {
            $this->respond('Некорректный аккаунт.', 'error', null, 400);
        }

        $requestedUuid = $this->request->string('userUuid');
        if ($requestedUuid !== '' && !Uuid::isValid($requestedUuid)) {
            $this->respond('Некорректный UUID пользователя.', 'error', null, 400);
        }

        $targetIdentity = $requestedUuid !== '' ? $requestedUuid : $sessionUuid;

        $placeholders = [];
        $parameters = [];
        foreach (Uuid::databaseCandidates($targetIdentity) as $index => $candidate) {
            $placeholder = ':userUuid_' . $index;
            $placeholders[] = $placeholder;
            $parameters[$placeholder] = $candidate;
        }

        $statement = $this->db->prepare(
            'SELECT `uuid`, `profilePhoto` FROM `users` '
            . 'WHERE `uuid` IN (' . implode(', ', $placeholders) . ') LIMIT 1'
        );
        $statement->execute($parameters);
        $target = $statement->fetch(PDO::FETCH_ASSOC);
        if (!is_array($target)) {
            $this->respond('Пользователь не найден.', 'error', null, 404);
        }

        $storageUuid = (string)$target['uuid'];
        return [
            'uuid' => Uuid::normalize($storageUuid),
            'storageUuid' => $storageUuid,
            'profilePhoto' => isset($target['profilePhoto']) ? (string)$target['profilePhoto'] : null,
        ];
    }

    private function respond(string $message, string $type, ?string $url = null, int $status = 200): never
    {
        if ($status >= 400) {
            RequestTelemetry::rejectHttp('profile.photo.rejected', $status, $message);
        }
        $response = ['message' => $message, 'type' => $type];
        if ($url !== null) {
            $response['url'] = $url;
        }
        JsonResponse::send($response, $status);
    }
}
