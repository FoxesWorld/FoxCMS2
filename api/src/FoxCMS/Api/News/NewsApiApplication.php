<?php

declare(strict_types=1);

namespace FoxCMS\Api\News;

use FoxCMS\Api\Core\ApplicationContext;
use FoxCMS\Api\Core\DatabaseFactory;
use FoxCMS\Api\Core\HttpException;
use FoxCMS\Api\Core\JsonResponse;
use FoxCMS\Api\Core\Request;
use FoxCMS\Api\Core\RequestId;
use Throwable;

final class NewsApiApplication
{
    private const DEFAULT_LIMIT = 10;
    private const MAX_LIMIT = 50;
    private const MAX_OFFSET = 1_000_000;
    private const SUCCESS_CACHE = 'public, max-age=60, stale-while-revalidate=300';

    public function __construct(
        private readonly ApplicationContext $context,
        private readonly Request $request,
    ) {
        $context->requireEngine(
            'classes/syslib/database.php',
            'classes/modules/News/NewsContentSanitizer.class.php',
            'classes/modules/News/NewsRepository.class.php',
            'classes/modules/News/NewsSchemaManager.class.php',
        );
    }

    public function run(): never
    {
        try {
            $this->request->requireMethod('GET', 'HEAD');
            $database = DatabaseFactory::create($this->context->config());
            (new \NewsSchemaManager($database))->ensure();
            $repository = new \NewsRepository($database);
            $presenter = new NewsPresenter(
                new \NewsContentSanitizer(),
                new ImageDataUrlEncoder(
                    $this->context->rootDirectory(),
                    ImageDataUrlEncoder::allowedHosts($this->context->network(), $this->context->environment()),
                ),
            );
            $includeImages = $this->request->booleanQuery('includeImages', false);
            $id = $this->request->query('id');

            if ($id !== null && $id !== '') {
                $this->single($repository, $presenter, $includeImages);
            }
            $this->catalog($repository, $presenter, $includeImages);
        } catch (HttpException $error) {
            JsonResponse::error($error->errorCode(), $error->getMessage(), $error->statusCode(), $error->details());
        } catch (Throwable $error) {
            $requestId = RequestId::create();
            \FoxCMS\Api\Core\FatalResponse::send(
                $error,
                $this->context,
                'news_unavailable',
                503,
                $requestId,
            );
        }
    }

    private function single(\NewsRepository $repository, NewsPresenter $presenter, bool $includeImages): never
    {
        $postId = $this->request->integerQuery('id', 0, 1, PHP_INT_MAX);
        $post = $repository->findPost($postId, '', false, false);
        if (!is_array($post)) {
            throw new HttpException(404, 'news_not_found', 'Новость не найдена.');
        }

        $includeComments = $this->request->booleanQuery('includeComments', true);
        $comments = $includeComments
            ? array_map($presenter->comment(...), $repository->comments($postId))
            : [];
        JsonResponse::send([
            'post' => $presenter->post($post, $includeImages),
            'comments' => $comments,
            'commentsIncluded' => $includeComments,
        ], headers: ['Cache-Control' => self::SUCCESS_CACHE], conditional: true);
    }

    private function catalog(\NewsRepository $repository, NewsPresenter $presenter, bool $includeImages): never
    {
        $limit = $this->request->integerQuery('limit', self::DEFAULT_LIMIT, 1, self::MAX_LIMIT);
        $offset = $this->request->integerQuery('offset', 0, 0, self::MAX_OFFSET);
        $total = $repository->countPosts(false);
        $items = $repository->listPosts($limit, $offset, '', false);

        JsonResponse::send([
            'items' => array_map(
                static fn (array $post): array => $presenter->post($post, $includeImages),
                $items,
            ),
            'total' => $total,
            'limit' => $limit,
            'offset' => $offset,
            'hasMore' => $offset + count($items) < $total,
        ], headers: ['Cache-Control' => self::SUCCESS_CACHE], conditional: true);
    }
}
