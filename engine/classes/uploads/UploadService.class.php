<?php

declare(strict_types=1);

final class UploadService
{
    private UploadPolicyFactory $policies;
    private UploadFilesystem $filesystem;
    private UploadFileInspector $inspector;

    public function __construct(
        db $db,
        private UserSession $session,
        private Logger $logger,
        private HttpRequest $request,
    ) {
        // Constructor compatibility is preserved; uploads no longer depend on the database directly.
        unset($db);
        $this->policies = new UploadPolicyFactory($session);
        $this->filesystem = new UploadFilesystem();
        $this->inspector = new UploadFileInspector($this->filesystem);
    }

    public function store(string $purpose, ?array $file, array $context = []): UploadResult
    {
        $purpose = trim($purpose);
        $audit = $this->baseAudit($purpose, $file, $context);
        $trace = OperationTrace::begin(
            $this->logger,
            'upload.store',
            array_merge($audit, ['component' => 'upload']),
            1_000,
        );

        try {
            $purpose = UploadPurpose::assert($purpose);
            $this->assertRequestSecurity($purpose);
            $policy = $this->policies->create($purpose, $context);
            $upload = $this->inspector->inspect($file, $policy);
            $directory = $this->filesystem->resolveDestinationDirectory($policy, $context);
            $storedName = $this->storedName($purpose, $upload, $context);
            $destination = $this->filesystem->resolveNewFilePath($directory, $storedName);
            $result = $this->filesystem->publish(
                $purpose,
                $upload,
                $destination,
                $policy->overwrite,
            );

            $trace->success('Upload accepted.', [
                'purpose' => $purpose,
                'mime' => $result->mime(),
                'size' => $result->size(),
                'target' => $result->relativePath(),
                'sha256Prefix' => substr($result->sha256(), 0, 16),
            ]);
            return $result;
        } catch (UploadException $error) {
            $trace->rejected(
                'upload_rejected',
                'Upload rejected by policy or validation.',
                $this->severityForStatus($error->httpStatus()),
                ['httpStatusRange' => '200-399'],
                [
                    'httpStatus' => $error->httpStatus(),
                    'reason' => $error->getMessage(),
                ],
                $error->auditContext(),
            );
            throw $error;
        } catch (Throwable $error) {
            $trace->failed($error, 'Upload failed unexpectedly.');
            throw new UploadException('Не удалось сохранить загруженный файл.', 500, [], $error);
        }
    }

    public function validateReference(string $purpose, string $publicPath, array $context = []): string
    {
        $policy = $this->policies->create(UploadPurpose::assert($purpose), $context, false);
        $reference = $this->filesystem->resolveReference($publicPath, $policy, $context);
        $this->inspector->validateExistingFile($reference['absolute'], $policy);
        return $reference['public'];
    }

    public function removeReference(string $purpose, string $publicPath, array $context = []): void
    {
        if (trim($publicPath) === '') {
            return;
        }
        $policy = $this->policies->create(UploadPurpose::assert($purpose), $context, false);
        $reference = $this->filesystem->resolveReference($publicPath, $policy, $context);
        $this->inspector->validateExistingFile($reference['absolute'], $policy);
        $this->filesystem->removeResolvedReference($reference['absolute']);
        $this->logger->event('upload.reference.removed', 'Uploaded file reference removed.', [
            'component' => 'upload',
            'operation' => 'remove_reference',
            'purpose' => $purpose,
            'path' => $reference['relative'],
        ], 'INFO', 'success');
    }

    private function assertRequestSecurity(string $purpose): void
    {
        if (!$this->request->isPost()) {
            throw new UploadException('Для загрузки требуется POST-запрос.', 405, ['purpose' => $purpose]);
        }
        if (!$this->session->isLogged()) {
            throw new UploadException('Для загрузки необходимо войти в аккаунт.', 401, ['purpose' => $purpose]);
        }
        if (!CsrfToken::validate($this->request->csrfToken())) {
            throw new UploadException('Недействительный токен защиты запроса.', 403, ['purpose' => $purpose]);
        }
    }

    private function storedName(
        string $purpose,
        InspectedUpload $upload,
        array $context,
    ): string {
        return match ($purpose) {
            UploadPurpose::NEWS_COVER => 'news-' . bin2hex(random_bytes(16)) . '.' . $upload->extension,
            UploadPurpose::SLIDER_IMAGE => 'slide-' . bin2hex(random_bytes(16)) . '.' . $upload->extension,
            UploadPurpose::SERVER_IMAGE => 'server-' . bin2hex(random_bytes(16)) . '.' . $upload->extension,
            UploadPurpose::SITE_SOCIAL_IMAGE => 'social-card-' . bin2hex(random_bytes(16)) . '.' . $upload->extension,
            UploadPurpose::PROFILE_PHOTO => 'profile-photo-' . bin2hex(random_bytes(12)) . '.' . $upload->extension,
            UploadPurpose::MINECRAFT_SKIN => Uuid::canonical($this->policies->ownerUuid($context)) . '-skin.png',
            UploadPurpose::MINECRAFT_CAPE => Uuid::canonical($this->policies->ownerUuid($context)) . '-cape.png',
            UploadPurpose::ADMIN_FILE => $this->filesystem->safeFileName($upload->originalName),
            default => throw new UploadException('Неизвестное назначение загрузки.', 400),
        };
    }

    /** @return array<string, mixed> */
    private function baseAudit(string $purpose, ?array $file, array $context): array
    {
        return [
            'purpose' => $purpose,
            'ownerUuid' => isset($context['ownerUuid']) ? (string)$context['ownerUuid'] : '',
            'requestedDirectory' => isset($context['directory']) ? (string)$context['directory'] : '',
            'originalName' => is_array($file)
                ? basename(str_replace('\\', '/', (string)($file['name'] ?? '')))
                : '',
            'reportedSize' => is_array($file) ? max(0, (int)($file['size'] ?? 0)) : 0,
        ];
    }

    private function severityForStatus(int $status): string
    {
        return $status >= 500 ? 'critical' : ($status === 401 || $status === 403 ? 'warning' : 'notice');
    }
}
