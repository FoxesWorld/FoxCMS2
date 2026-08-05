<?php

declare(strict_types=1);

$rootDirectory = dirname(__DIR__);

require_once $rootDirectory . '/engine/data/environment.php';
require_once $rootDirectory . '/engine/classes/support/RuntimeErrorHandler.class.php';
RuntimeErrorHandler::register($rootDirectory, false);
foxLoadEnv($rootDirectory . '/.env');
RuntimeErrorHandler::setDebug(foxEnvBool('FOXESCRAFT_DEBUG', false));

if (!defined('FOXXEY')) {
    define('FOXXEY', true);
}

require_once $rootDirectory . '/engine/classes/http/NetworkContext.class.php';
$trustedProxies = array_values(array_filter(array_map(
    'trim',
    explode(',', foxEnv('FOXESCRAFT_TRUSTED_PROXIES', '') ?? ''),
), static fn (string $value): bool => $value !== ''));
$network = NetworkContext::fromGlobals($trustedProxies);
$GLOBALS['foxNetworkContext'] = $network;

require_once $rootDirectory . '/engine/data/const.php';
$config = require $rootDirectory . '/engine/data/config.php';
if (!is_array($config)) {
    throw new RuntimeException('FoxCMS configuration did not return an array.');
}

require_once $rootDirectory . '/engine/classes/http/SecurityHeaders.class.php';
SecurityHeaders::apply($network, false);

require_once ENGINE_DIR . 'classes/syslib/database.php';
require_once ENGINE_DIR . 'classes/modules/News/NewsContentSanitizer.class.php';
require_once ENGINE_DIR . 'classes/modules/News/NewsRepository.class.php';
require_once ENGINE_DIR . 'classes/modules/News/NewsSchemaManager.class.php';

function newsApiDatabase(array $config): db
{
    $database = is_array($config['database'] ?? null) ? $config['database'] : [];
    return new db(
        (string)($database['dbUser'] ?? ''),
        (string)($database['dbPass'] ?? ''),
        (string)($database['dbName'] ?? ''),
        (string)($database['dbHost'] ?? '127.0.0.1'),
        (int)($database['dbPort'] ?? 3306),
        (string)($database['dbCharset'] ?? 'utf8mb4'),
        (int)($database['connectTimeout'] ?? 5),
    );
}

function newsApiQueryValue(string $name): ?string
{
    if (!array_key_exists($name, $_GET)) {
        return null;
    }
    if (!is_scalar($_GET[$name])) {
        throw new InvalidArgumentException('Параметр ' . $name . ' должен быть скалярным значением.');
    }
    return trim((string)$_GET[$name]);
}

function newsApiInteger(string $name, int $default, int $minimum, int $maximum): int
{
    $raw = newsApiQueryValue($name);
    if ($raw === null || $raw === '') {
        return $default;
    }
    $value = filter_var($raw, FILTER_VALIDATE_INT, [
        'options' => [
            'min_range' => $minimum,
            'max_range' => $maximum,
        ],
    ]);
    if (!is_int($value)) {
        throw new InvalidArgumentException(
            'Параметр ' . $name . ' должен быть целым числом от ' . $minimum . ' до ' . $maximum . '.',
        );
    }
    return $value;
}

function newsApiBoolean(string $name, bool $default): bool
{
    $raw = newsApiQueryValue($name);
    if ($raw === null || $raw === '') {
        return $default;
    }
    $value = filter_var($raw, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    if (!is_bool($value)) {
        throw new InvalidArgumentException('Параметр ' . $name . ' должен быть логическим значением.');
    }
    return $value;
}

function newsApiNullableText(mixed $value): ?string
{
    $text = trim((string)$value);
    return $text === '' ? null : $text;
}

function newsApiImageDataUrl(?string $source, string $rootDirectory): ?string
{
    if ($source === null || trim($source) === '') {
        return null;
    }

    $source = trim($source);
    if (preg_match('#^data:(image/(?:jpeg|png|gif|webp));base64,(.+)$#is', $source, $matches) === 1) {
        $bytes = base64_decode(preg_replace('/\s+/', '', $matches[2]) ?? '', true);
        if (!is_string($bytes)) {
            return null;
        }
        return newsApiEncodeImageDataUrl($bytes, strtolower($matches[1]));
    }

    $parts = parse_url($source);
    if ($parts === false) {
        return null;
    }
    if (isset($parts['scheme']) && !newsApiIsLocalImageHost((string)($parts['host'] ?? ''))) {
        return null;
    }

    $relativePath = rawurldecode((string)($parts['path'] ?? $source));
    $relativePath = str_replace('\\', '/', $relativePath);
    if (str_contains($relativePath, "\0")) {
        return null;
    }
    $relativePath = ltrim($relativePath, '/');
    if (!str_starts_with($relativePath, 'uploads/')) {
        return null;
    }

    $uploadsDirectory = realpath($rootDirectory . DIRECTORY_SEPARATOR . 'uploads');
    $absolutePath = realpath($rootDirectory . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath));
    if ($uploadsDirectory === false || $absolutePath === false || !is_file($absolutePath) || !is_readable($absolutePath)) {
        return null;
    }
    $uploadsPrefix = rtrim($uploadsDirectory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    if (!str_starts_with($absolutePath, $uploadsPrefix)) {
        return null;
    }

    $size = filesize($absolutePath);
    if (!is_int($size) || $size < 1 || $size > 2 * 1024 * 1024) {
        return null;
    }
    $bytes = file_get_contents($absolutePath);
    if (!is_string($bytes)) {
        return null;
    }

    $mime = null;
    if (class_exists('finfo')) {
        $detector = new finfo(FILEINFO_MIME_TYPE);
        $detected = $detector->buffer($bytes);
        $mime = is_string($detected) ? strtolower($detected) : null;
    }
    return newsApiEncodeImageDataUrl($bytes, $mime);
}

function newsApiIsLocalImageHost(string $host): bool
{
    $host = strtolower(trim($host));
    if ($host === '') {
        return true;
    }
    $currentHost = strtolower(preg_replace('/:\d+$/', '', (string)($_SERVER['HTTP_HOST'] ?? '')) ?? '');
    return $host === $currentHost
        || in_array($host, ['foxescraft.ru', 'www.foxescraft.ru', 'localhost', '127.0.0.1'], true);
}

function newsApiEncodeImageDataUrl(string $bytes, ?string $declaredMime): ?string
{
    if ($bytes === '' || strlen($bytes) > 2 * 1024 * 1024) {
        return null;
    }
    $imageInfo = @getimagesizefromstring($bytes);
    if (!is_array($imageInfo)) {
        return null;
    }
    $width = (int)($imageInfo[0] ?? 0);
    $height = (int)($imageInfo[1] ?? 0);
    $mime = strtolower((string)($imageInfo['mime'] ?? $declaredMime ?? ''));
    if ($width < 1 || $height < 1 || $width > 4096 || $height > 4096) {
        return null;
    }
    if (!in_array($mime, ['image/jpeg', 'image/png', 'image/gif', 'image/webp'], true)) {
        return null;
    }

    if (function_exists('imagecreatefromstring') && function_exists('imagejpeg')) {
        $source = @imagecreatefromstring($bytes);
        if ($source !== false) {
            $maxWidth = 960;
            $maxHeight = 540;
            $scale = min(1.0, $maxWidth / $width, $maxHeight / $height);
            $targetWidth = max(1, (int)round($width * $scale));
            $targetHeight = max(1, (int)round($height * $scale));
            $target = imagecreatetruecolor($targetWidth, $targetHeight);
            if ($target !== false) {
                $background = imagecolorallocate($target, 30, 25, 21);
                imagefill($target, 0, 0, $background);
                imagecopyresampled($target, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);
                ob_start();
                imagejpeg($target, null, 84);
                $encodedBytes = ob_get_clean();
                imagedestroy($target);
                imagedestroy($source);
                if (is_string($encodedBytes) && $encodedBytes !== '') {
                    return 'data:image/jpeg;base64,' . base64_encode($encodedBytes);
                }
            } else {
                imagedestroy($source);
            }
        }
    }

    if ($mime === 'image/webp') {
        return null;
    }
    return 'data:' . $mime . ';base64,' . base64_encode($bytes);
}

/** @param array<string, mixed> $post */
function newsApiPost(
    array $post,
    NewsContentSanitizer $sanitizer,
    string $rootDirectory,
    bool $includeImages,
): array {
    $coverImage = newsApiNullableText($post['coverImage'] ?? null);
    $result = [
        'id' => (int)($post['id'] ?? 0),
        'title' => (string)($post['title'] ?? ''),
        'summary' => (string)($post['summary'] ?? ''),
        'coverImage' => $coverImage,
        'publishedAt' => newsApiNullableText($post['publishedAt'] ?? null),
        'createdAt' => (string)($post['createdAt'] ?? ''),
        'updatedAt' => (string)($post['updatedAt'] ?? ''),
        'authorLogin' => (string)($post['authorLogin'] ?? ''),
        'authorName' => (string)($post['authorName'] ?? ''),
        'authorPhoto' => newsApiNullableText($post['authorPhoto'] ?? null),
        'authorGroup' => newsApiNullableText($post['authorGroup'] ?? null),
        'authorColor' => newsApiNullableText($post['authorColor'] ?? null),
        'likesCount' => max(0, (int)($post['likesCount'] ?? 0)),
        'commentsCount' => max(0, (int)($post['commentsCount'] ?? 0)),
        'viewsCount' => max(0, (int)($post['viewsCount'] ?? 0)),
    ];
    if ($includeImages) {
        $result['coverImageDataUrl'] = newsApiImageDataUrl($coverImage, $rootDirectory);
    }
    if (array_key_exists('content', $post)) {
        $result['content'] = $sanitizer->sanitize((string)$post['content']);
    }
    return $result;
}

/** @param array<string, mixed> $comment */
function newsApiComment(array $comment): array
{
    return [
        'id' => (int)($comment['id'] ?? 0),
        'content' => (string)($comment['content'] ?? ''),
        'createdAt' => (string)($comment['createdAt'] ?? ''),
        'updatedAt' => (string)($comment['updatedAt'] ?? ''),
        'authorLogin' => (string)($comment['authorLogin'] ?? ''),
        'authorName' => (string)($comment['authorName'] ?? ''),
        'authorPhoto' => newsApiNullableText($comment['authorPhoto'] ?? null),
        'authorGroup' => newsApiNullableText($comment['authorGroup'] ?? null),
        'authorColor' => newsApiNullableText($comment['authorColor'] ?? null),
    ];
}

function newsApiRespond(
    mixed $payload,
    int $status = 200,
    string $cacheControl = 'public, max-age=60, stale-while-revalidate=300',
    bool $conditional = true,
): never {
    $content = json_encode(
        $payload,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR,
    );
    $etag = '"' . hash('sha256', $content) . '"';

    http_response_code($status);
    header('Content-Type: application/json; charset=UTF-8');
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: ' . $cacheControl);
    header('ETag: ' . $etag);

    if ($conditional && trim((string)($_SERVER['HTTP_IF_NONE_MATCH'] ?? '')) === $etag) {
        http_response_code(304);
        exit;
    }
    if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'HEAD') {
        exit;
    }
    echo $content;
    exit;
}

function newsApiError(string $code, string $message, int $status): never
{
    newsApiRespond(
        ['error' => $code, 'message' => $message],
        $status,
        'no-store, max-age=0',
        false,
    );
}

try {
    $method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    if (!in_array($method, ['GET', 'HEAD'], true)) {
        header('Allow: GET, HEAD');
        newsApiError('method_not_allowed', 'Разрешены только методы GET и HEAD.', 405);
    }

    $database = newsApiDatabase($config);
    (new NewsSchemaManager($database))->ensure();
    $repository = new NewsRepository($database);
    $sanitizer = new NewsContentSanitizer();
    $includeImages = newsApiBoolean('includeImages', false);
    $id = newsApiQueryValue('id');

    if ($id !== null && $id !== '') {
        $postId = newsApiInteger('id', 0, 1, PHP_INT_MAX);
        $post = $repository->findPost($postId, '', false, false);
        if (!is_array($post)) {
            newsApiError('news_not_found', 'Новость не найдена.', 404);
        }

        $includeComments = newsApiBoolean('includeComments', true);
        $comments = $includeComments
            ? array_map('newsApiComment', $repository->comments($postId))
            : [];
        newsApiRespond([
            'post' => newsApiPost($post, $sanitizer, $rootDirectory, $includeImages),
            'comments' => $comments,
            'commentsIncluded' => $includeComments,
        ]);
    }

    $limit = newsApiInteger('limit', 10, 1, 50);
    $offset = newsApiInteger('offset', 0, 0, 1000000);
    $total = $repository->countPosts(false);
    $items = $repository->listPosts($limit, $offset, '', false);
    $loaded = $offset + count($items);

    newsApiRespond([
        'items' => array_map(
            static fn (array $post): array => newsApiPost($post, $sanitizer, $rootDirectory, $includeImages),
            $items,
        ),
        'total' => $total,
        'limit' => $limit,
        'offset' => $offset,
        'hasMore' => $loaded < $total,
    ]);
} catch (InvalidArgumentException $error) {
    newsApiError('invalid_request', $error->getMessage(), 400);
} catch (Throwable $error) {
    $requestId = bin2hex(random_bytes(8));
    error_log('[FoxCMS news API][' . $requestId . '] '
        . $error::class . ': ' . $error->getMessage()
        . ' at ' . $error->getFile() . ':' . $error->getLine());
    newsApiRespond([
        'error' => 'news_unavailable',
        'message' => 'Сервис новостей временно недоступен.',
        'requestId' => $requestId,
    ], 503, 'no-store, max-age=0', false);
}
