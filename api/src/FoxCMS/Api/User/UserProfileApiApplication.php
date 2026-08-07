<?php

declare(strict_types=1);

namespace FoxCMS\Api\User;

use FoxCMS\Api\Core\ApplicationContext;
use FoxCMS\Api\Core\DatabaseFactory;
use FoxCMS\Api\Core\HttpException;
use FoxCMS\Api\Core\JsonResponse;
use FoxCMS\Api\Core\Request;
use FoxCMS\Api\Core\RequestId;
use Throwable;

final class UserProfileApiApplication
{
    private const SCHEMA_VERSION = 1;
    private const SUCCESS_CACHE = 'public, max-age=30, stale-while-revalidate=60';

    public function __construct(
        private readonly ApplicationContext $context,
        private readonly Request $request,
    ) {
        $context->requireEngine(
            'classes/syslib/database.php',
            'classes/identity/Uuid.class.php',
        );
    }

    public function run(): never
    {
        try {
            $this->request->requireMethod('GET', 'HEAD');
            $uuid = trim((string)$this->request->query('uuid'));
            if ($uuid === '') {
                throw new HttpException(
                    400,
                    'user_uuid_required',
                    'Параметр uuid обязателен.',
                );
            }
            if (!\Uuid::isValid($uuid)) {
                throw new HttpException(
                    400,
                    'user_uuid_invalid',
                    'Некорректный UUID пользователя.',
                );
            }

            $repository = new PublicUserProfileRepository(
                DatabaseFactory::create($this->context->config()),
            );
            $profile = $repository->findByUuid($uuid);

            $environment = is_array($this->context->config()['environment'] ?? null)
                ? $this->context->config()['environment']
                : [];
            $presenter = new UserProfilePresenter(
                (string)($environment['publicBaseUrl'] ?? ''),
            );

            $user = $profile === null
                ? $presenter->anonymous($uuid)
                : $presenter->present($profile);

            JsonResponse::send([
                'schemaVersion' => self::SCHEMA_VERSION,
                'user' => $user,
            ], headers: ['Cache-Control' => self::SUCCESS_CACHE], conditional: true);
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
                'user_profile_unavailable',
                503,
                RequestId::create(),
            );
        }
    }
}
