<?php

declare(strict_types=1);

namespace FoxCMS\Api\Badge;

use FoxCMS\Api\Content\BadgeCatalogService;
use FoxCMS\Api\Core\ApplicationContext;
use FoxCMS\Api\Core\DatabaseFactory;
use FoxCMS\Api\Core\HttpException;
use FoxCMS\Api\Core\JsonResponse;
use FoxCMS\Api\Core\Request;
use FoxCMS\Api\Core\RequestId;
use Throwable;

final class BadgeApiApplication
{
    private const SCHEMA_VERSION = 1;
    private const IDENTIFIER_MAX_LENGTH = 191;
    private const SUCCESS_CACHE = 'public, max-age=60, stale-while-revalidate=300';

    public function __construct(
        private readonly ApplicationContext $context,
        private readonly Request $request,
    ) {
        $context->requireEngine(
            'classes/syslib/database.php',
            'classes/themes/ThemeRuntimeTplDocument.class.php',
            'classes/themes/ThemeRuntimeTplCompiler.class.php',
            'classes/themes/BadgeSlug.class.php',
            'classes/themes/ThemeBadgePageRepository.class.php',
        );
    }

    public function run(): never
    {
        try {
            $this->request->requireMethod('GET', 'HEAD');

            $site = is_array($this->context->config()['siteSettings'] ?? null)
                ? $this->context->config()['siteSettings']
                : [];
            $environment = is_array($this->context->config()['environment'] ?? null)
                ? $this->context->config()['environment']
                : [];
            $themeName = (string)($site['siteTpl'] ?? '');
            $service = new BadgeCatalogService(
                DatabaseFactory::create($this->context->config()),
                new \ThemeBadgePageRepository(TEMPLATE_DIR, $themeName),
            );
            $presenter = new BadgePresenter(
                (string)($environment['publicBaseUrl'] ?? ''),
            );
            $badges = array_map(
                $presenter->present(...),
                $service->catalog(),
            );

            $identifier = trim((string)$this->request->query('id'));
            if ($identifier === '') {
                $this->respond([
                    'schemaVersion' => self::SCHEMA_VERSION,
                    'badges' => $badges,
                    'total' => count($badges),
                ]);
            }
            $this->validateIdentifier($identifier);

            $badge = (new BadgeIdentifierMatcher())->find($badges, $identifier);
            if ($badge === null) {
                throw new HttpException(
                    404,
                    'badge_not_found',
                    'Бейдж не найден.',
                );
            }

            $this->respond([
                'schemaVersion' => self::SCHEMA_VERSION,
                'badge' => $badge,
            ]);
        } catch (HttpException $error) {
            JsonResponse::error(
                $error->errorCode(),
                $error->getMessage(),
                $error->statusCode(),
                $error->details(),
            );
        } catch (Throwable $error) {
            \FoxCMS\Api\Core\FatalResponse::send(
                $error,
                $this->context,
                'badge_catalog_unavailable',
                503,
                RequestId::create(),
            );
        }
    }

    private function validateIdentifier(string $identifier): void
    {
        $length = function_exists('mb_strlen')
            ? mb_strlen($identifier, 'UTF-8')
            : strlen($identifier);
        if (preg_match('//u', $identifier) !== 1
            || $length > self::IDENTIFIER_MAX_LENGTH
            || preg_match('/[\x00-\x1f\x7f]/u', $identifier) === 1) {
            throw new HttpException(
                400,
                'badge_identifier_invalid',
                'Некорректный идентификатор бейджа.',
            );
        }
    }

    /** @param array<string, mixed> $payload */
    private function respond(array $payload): never
    {
        JsonResponse::send(
            $payload,
            headers: ['Cache-Control' => self::SUCCESS_CACHE],
            conditional: true,
        );
    }
}
