<?php

declare(strict_types=1);

namespace FoxCMS\Engine\System;

use GdImage;

final class SystemRequestMediaController
{
    public function __construct(
        private \db $db,
        private \HttpRequest $request,
        private \UserSession $session,
        private array $config,
        private \PublicFileLocator $publicFiles,
        private \UserTextureLocator $textures,
    ) {
    }

    public function skin(): never
    {
        $this->renderSkinPreview($this->request->string('show') === 'head');
    }

    public function skinPreview(): never
    {
        $this->renderSkinPreview(false);
    }

    public function userHead(): never
    {
        $login = $this->request->string('login');
        if (preg_match('/^[A-Za-z0-9_.-]{1,64}$/D', $login) !== 1) {
            throw new \InvalidArgumentException('Invalid login.');
        }

        \UtilityLoader::load('LoadUserInfo', '1.0.0');
        $userData = \LoadUserInfo::byLogin($login, $this->db)->userInfoArray();
        $path = $this->publicFiles->resolve(
            (string)($userData['profilePhoto'] ?? ''),
            ROOT_DIR . UPLOADS_DIR,
            5_242_880,
        );
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($path);
        if (!is_string($mime) || !str_starts_with($mime, 'image/')) {
            throw new \HttpException('Invalid profile image.', 415);
        }
        $content = file_get_contents($path);
        if (!is_string($content)) {
            throw new \RuntimeException('Unable to read profile image.');
        }
        \JsonResponse::text(base64_encode($content), 'text/plain; charset=US-ASCII');
    }

    public function skinPath(): never
    {
        if (!$this->session->isLogged()) {
            throw new \HttpException('Authentication required.', 401);
        }
        $files = $this->session->gameFiles();
        \JsonResponse::send([
            'skin' => str_replace('\\', '/', substr($files['skin'], strlen(ROOT_DIR))),
            'cape' => str_replace('\\', '/', substr($files['cape'], strlen(ROOT_DIR))),
        ]);
    }

    public function serverImage(): never
    {
        $reference = trim(str_replace('\\', '/', $this->request->string('srvImgName')));
        if (str_starts_with($reference, 'uploads/')) {
            $reference = '/' . $reference;
        }

        if (str_starts_with($reference, '/uploads/servers/')) {
            if (preg_match('#^/uploads/servers/[A-Za-z0-9_.-]{1,180}\.(?:png|jpe?g|webp)$#iD', $reference) !== 1) {
                throw new \InvalidArgumentException('Invalid uploaded server image path.');
            }
            $path = $this->publicFiles->resolve(
                ltrim($reference, '/'),
                ROOT_DIR . UPLOADS_DIR . 'servers',
                12_582_912,
            );
        } else {
            if (preg_match('/^[A-Za-z0-9_.-]{1,96}\.(?:png|jpe?g|webp)$/iD', $reference) !== 1) {
                throw new \InvalidArgumentException('Invalid server image name.');
            }
            $path = $this->publicFiles->resolve(
                'templates/' . (string)$this->config['siteSettings']['siteTpl']
                    . '/assets/img/servers/' . $reference,
                TEMPLATE_DIR,
                10_485_760,
            );
        }

        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($path);
        if (!is_string($mime) || !in_array(strtolower($mime), ['image/jpeg', 'image/png', 'image/webp'], true)) {
            throw new \HttpException('Invalid server image.', 415);
        }
        $content = file_get_contents($path);
        if (!is_string($content)) {
            throw new \RuntimeException('Unable to read server image.');
        }
        \JsonResponse::text(base64_encode($content), 'text/plain; charset=US-ASCII');
    }

    private function renderSkinPreview(bool $headOnly): never
    {
        if (!extension_loaded('gd')) {
            throw new \HttpException('GD extension is unavailable.', 503);
        }

        $side = $this->request->string('side');
        if (!in_array($side, ['', 'front', 'back'], true)) {
            throw new \InvalidArgumentException('Invalid preview side.');
        }

        \UtilityLoader::load('SkinViewer', '1.0.0');
        \UtilityLoader::load('LoadUserInfo', '1.0.0');
        $requestedUuid = $this->request->string('userUuid');
        if ($requestedUuid !== '') {
            if (!\Uuid::isValid($requestedUuid)) {
                throw new \InvalidArgumentException('Invalid user UUID.');
            }
            $identity = \LoadUserInfo::byUuid(\Uuid::normalize($requestedUuid), $this->db)->userInfoArray();
        } else {
            $login = $this->request->string('login', 'default');
            if (preg_match('/^[A-Za-z0-9_.-]{1,64}$/D', $login) !== 1) {
                throw new \InvalidArgumentException('Invalid login.');
            }
            $identity = \LoadUserInfo::byLogin($login, $this->db)->userInfoArray();
        }

        $defaultSkin = ROOT_DIR . UPLOADS_DIR . USR_SUBFOLDER . 'default_skin.png';
        $identityUuid = (string)($identity['uuid'] ?? '');
        if (\Uuid::isValid($identityUuid)) {
            $files = $this->textures->locate($identityUuid);
            $skin = $files['skin'];
            $cape = $files['cape'];
        } else {
            $skin = $defaultSkin;
            $cape = '';
        }
        if (!is_file($skin) || !\skinViewer2D::isValidSkin($skin)) {
            $skin = $defaultSkin;
        }
        if (!is_file($skin) || !\skinViewer2D::isValidSkin($skin)) {
            throw new \HttpException('Skin is unavailable.', 404);
        }

        $image = $headOnly
            ? \skinViewer2D::createHead($skin, 64)
            : \skinViewer2D::createPreview($skin, is_file($cape) ? $cape : false, $side ?: false);
        if (!$image instanceof GdImage) {
            throw new \RuntimeException('Unable to render skin preview.');
        }
        try {
            ob_start();
            imagepng($image);
            $content = ob_get_clean();
        } finally {
            imagedestroy($image);
        }
        if (!is_string($content)) {
            throw new \RuntimeException('Unable to encode skin preview.');
        }
        \JsonResponse::text(base64_encode($content), 'text/plain; charset=US-ASCII');
    }
}
