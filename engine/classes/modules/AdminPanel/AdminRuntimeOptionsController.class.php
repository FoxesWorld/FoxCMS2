<?php

declare(strict_types=1);

/** Owns the administrative read/write boundary for theme userOptions TPL files. */
final class AdminRuntimeOptionsController
{
    public function __construct(
        private ThemeUserOptionsRepository $repository,
        private UserSession $session,
        private Logger $logger,
        private AdminRequestPayload $payload,
        private AdminResponder $responder,
    ) {
    }

    public function userOptions(): void
    {
        $document = $this->repository->read(true);
        $this->responder->send([
            'document' => $document,
            'updatedAt' => (string)($document['updatedAt'] ?? ''),
            'storageReady' => $this->repository->storageReady(),
        ]);
    }

    public function saveUserOptions(): void
    {
        $entry = $this->payload->object('entry');
        $templateId = trim((string)($entry['templateId'] ?? ''));
        $source = (string)($entry['source'] ?? '');
        try {
            $document = $this->repository->saveTemplate($templateId, $source);
        } catch (InvalidArgumentException $error) {
            $this->responder->send(['message' => $error->getMessage(), 'type' => 'error'], 400);
        } catch (RuntimeException $error) {
            $this->logger->exception(
                'theme.runtime_tpl.compile_failed',
                $error,
                'Runtime userOptions TPL compilation failed; the previous revision remains active.',
                [
                    'component' => 'theme_runtime_tpl',
                    'operation' => 'compile_user_options',
                    'templateId' => $templateId,
                    'actorUuid' => $this->session->uuid(),
                ],
            );
            $this->responder->send([
                'message' => 'Runtime TPL compiler is unavailable. The existing published revision remains active.',
                'type' => 'error',
            ], 503);
        }
        $this->logger->event(
            'theme.runtime_tpl.saved',
            'Theme userOptions runtime TPL saved.',
            [
                'component' => 'theme_runtime_tpl',
                'operation' => 'save_user_options',
                'revision' => (int)($document['revision'] ?? 0),
                'templateId' => $templateId,
                'profileTabs' => count($document['profile']['tabs'] ?? []),
                'adminCategories' => count($document['admin']['categories'] ?? []),
                'adminTools' => count($document['admin']['tools'] ?? []),
                'actorUuid' => $this->session->uuid(),
            ],
            'INFO',
            'success',
        );
        $this->responder->send([
            'message' => 'Runtime TPL сохранён и применён без пересборки frontend chunks.',
            'type' => 'success',
            'document' => $document,
            'updatedAt' => (string)($document['updatedAt'] ?? ''),
            'storageReady' => $this->repository->storageReady(),
        ]);
    }
}
