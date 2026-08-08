<?php

declare(strict_types=1);

namespace FoxCMS\Engine\Admin;

final class AdminThemeController
{
    public function __construct(
        private array $request,
        private \Logger $logger,
        private \ThemeSlidesRepository $slides,
        private \UploadService $uploads,
        private \AdminRequestPayload $payload,
        private \AdminResponder $responder,
    ) {
    }

    public function slides(): never
    {
        $this->responder->send([
            'settings' => $this->slides->read(),
            'routes' => $this->slides->routes(),
        ]);
    }

    public function saveSlides(): never
    {
        $payload = $this->payload->object('entry');
        try {
            $settings = $this->slides->save($payload);
        } catch (\InvalidArgumentException $error) {
            $this->responder->send(['message' => $error->getMessage(), 'type' => 'error'], 400);
        }
        $this->logger->event(
            'theme.slides.saved',
            'Theme slides saved.',
            [
                'component' => 'theme_slides',
                'operation' => 'save',
                'slidesCount' => count($settings['slides'] ?? []),
                'enabledCount' => count(array_filter(
                    $settings['slides'] ?? [],
                    static fn (array $slide): bool => ($slide['enabled'] ?? false) === true,
                )),
            ],
            'INFO',
            'success',
        );
        $this->responder->send([
            'message' => 'Слайды сохранены в JSON.',
            'type' => 'success',
            'settings' => $settings,
        ]);
    }

    public function uploadSlideImage(): never
    {
        $this->uploadImage(\UploadPurpose::SLIDER_IMAGE, '_slideUpload', 'Изображение слайда загружено.');
    }

    public function uploadSiteSocialImage(): never
    {
        $this->uploadImage(
            \UploadPurpose::SITE_SOCIAL_IMAGE,
            '_siteSocialImageUpload',
            'Изображение социальной карточки загружено.',
        );
    }

    public function uploadServerImage(): never
    {
        $this->uploadImage(\UploadPurpose::SERVER_IMAGE, '_serverImageUpload', 'Изображение сервера загружено.');
    }

    private function uploadImage(string $purpose, string $requestKey, string $message): never
    {
        try {
            $result = $this->uploads->store(
                $purpose,
                is_array($this->request[$requestKey] ?? null) ? $this->request[$requestKey] : null,
            );
        } catch (\UploadException $error) {
            $this->responder->send(['message' => $error->getMessage(), 'type' => 'error'], $error->httpStatus());
        }
        $this->responder->send([
            'message' => $message,
            'type' => 'success',
            'image' => $result->publicPath(),
            'upload' => $result,
        ], 201);
    }
}
