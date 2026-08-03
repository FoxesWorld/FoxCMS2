<?php

declare(strict_types=1);

$rootDirectory = dirname(__DIR__);

require_once $rootDirectory . '/engine/data/environment.php';
foxLoadEnv($rootDirectory . DIRECTORY_SEPARATOR . '.env');

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

require_once ENGINE_DIR . 'classes/syslib/database.php';
require_once ENGINE_DIR . 'classes/themes/ThemeContentRepository.class.php';
require_once ENGINE_DIR . 'classes/themes/ThemeEmoticonRepository.class.php';
require_once ENGINE_DIR . 'classes/themes/BadgeSlug.class.php';
require_once ENGINE_DIR . 'classes/themes/ThemeBadgePageRepository.class.php';

function contentText(mixed $value): string
{
    $text = str_replace("\0", '', (string)$value);
    if (preg_match('//u', $text) !== 1) {
        throw new InvalidArgumentException('Content data must be valid UTF-8.');
    }
    return $text;
}

function contentDatabase(array $config): db
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

function contentBadgeCatalog(db $database, ThemeBadgePageRepository $repository): array
{
    $statement = $database->prepare(
        'SELECT `id`, `badgeName`, `description`, `img` FROM `badgesList` ORDER BY `badgeName`, `id`'
    );
    $statement->execute();
    $rows = BadgeSlug::assign($statement->fetchAll(PDO::FETCH_ASSOC) ?: []);

    $items = [];
    foreach ($rows as $rowIndex => $row) {
        try {
            if (!is_array($row)) {
                throw new UnexpectedValueException('Database row is not an array.');
            }
            $databaseId = max(0, (int)($row['id'] ?? 0));
            $badgeName = trim(contentText($row['badgeName'] ?? ''));
            if ($badgeName === '') {
                error_log('[FoxesCraft badge catalog] Skipped badge row without badgeName at index ' . $rowIndex);
                continue;
            }
            $slug = trim(contentText($row['pageSlug'] ?? ''));
            if (preg_match('/^[a-z0-9][a-z0-9-]{0,79}$/D', $slug) !== 1) {
                throw new UnexpectedValueException('Generated badge slug is invalid.');
            }
            $description = trim(contentText($row['description'] ?? ''));
            $image = trim(contentText($row['img'] ?? ''));
            $items[] = [
                'id' => $slug,
                'databaseId' => $databaseId,
                'badgeName' => $badgeName,
                'title' => $badgeName,
                'description' => $description,
                'image' => $image !== '' ? $image : null,
                'html' => '',
                'pageConfigured' => $repository->exists($slug),
            ];
        } catch (Throwable $error) {
            error_log('[FoxesCraft badge catalog] Skipped invalid badge row at index ' . $rowIndex
                . ': ' . $error::class . ': ' . $error->getMessage());
        }
    }
    return $items;
}

function contentBadgePage(db $database, ThemeBadgePageRepository $repository, string $slug): ?array
{
    $statement = $database->prepare(
        'SELECT `id`, `badgeName`, `description`, `img` FROM `badgesList` ORDER BY `badgeName`, `id`'
    );
    $statement->execute();
    $rows = BadgeSlug::assign($statement->fetchAll(PDO::FETCH_ASSOC) ?: []);

    $row = null;
    foreach ($rows as $candidate) {
        if (is_array($candidate) && hash_equals((string)($candidate['pageSlug'] ?? ''), $slug)) {
            $row = $candidate;
            break;
        }
    }
    if (!is_array($row) || !$repository->exists($slug)) {
        return null;
    }

    $page = $repository->read($slug);
    if (!is_array($page)) {
        return null;
    }

    $badgeName = trim(contentText($row['badgeName'] ?? ''));
    $description = trim(contentText($row['description'] ?? ''));
    $image = trim(contentText($row['img'] ?? ''));
    return [
        'id' => $slug,
        'databaseId' => max(0, (int)($row['id'] ?? 0)),
        'badgeName' => $badgeName,
        'title' => $badgeName,
        'description' => $description,
        'image' => $image !== '' ? $image : null,
        'html' => $repository->render($page, [
            'badgeName' => $badgeName,
            'description' => $description,
            'img' => $image,
        ]),
        'pageConfigured' => true,
    ];
}

function contentRespond(mixed $payload): never
{
    $content = json_encode(
        $payload,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR,
    );
    $etag = '"' . hash('sha256', $content) . '"';
    header('Content-Type: application/json; charset=UTF-8');
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: public, max-age=0, must-revalidate');
    header('ETag: ' . $etag);
    if (trim((string)($_SERVER['HTTP_IF_NONE_MATCH'] ?? '')) === $etag) {
        http_response_code(304);
        exit;
    }
    echo $content;
    exit;
}

try {
    $site = is_array($config['siteSettings'] ?? null) ? $config['siteSettings'] : [];
    $themeName = (string)($site['siteTpl'] ?? '');
    $contentRepository = new ThemeContentRepository(TEMPLATE_DIR, $themeName);
    $badgeRepository = new ThemeBadgePageRepository(TEMPLATE_DIR, $themeName);
    $registry = trim((string)($_GET['registry'] ?? ''));

    if (in_array($registry, ['project-pages', 'static-pages'], true)) {
        $document = $contentRepository->readProjectPages();
        $indexed = [];
        foreach ($document['pages'] ?? [] as $page) {
            if (is_array($page) && is_string($page['id'] ?? null)) {
                $indexed[$page['id']] = $page;
            }
        }
        contentRespond($indexed);
    }

    if ($registry === 'emoticons') {
        contentRespond((new ThemeEmoticonRepository(TEMPLATE_DIR, $themeName))->catalog());
    }

    if ($registry === 'badges') {
        contentRespond(contentBadgeCatalog(contentDatabase($config), $badgeRepository));
    }

    if ($registry === 'badge') {
        $slug = trim((string)($_GET['id'] ?? ''));
        if (preg_match('/^[a-z0-9][a-z0-9-]{0,79}$/D', $slug) !== 1) {
            http_response_code(400);
            contentRespond(['error' => 'invalid_badge_slug']);
        }
        $badge = contentBadgePage(contentDatabase($config), $badgeRepository, $slug);
        if (!is_array($badge)) {
            http_response_code(404);
            contentRespond(['error' => 'badge_page_not_found']);
        }
        contentRespond($badge);
    }

    http_response_code(404);
    contentRespond(['error' => 'content_registry_not_found']);
} catch (Throwable $error) {
    $requestId = bin2hex(random_bytes(8));
    error_log('[FoxesCraft content API][' . $requestId . '] registry=' . ($registry ?? 'unknown')
        . ' ' . $error::class . ': ' . $error->getMessage()
        . ' at ' . $error->getFile() . ':' . $error->getLine());
    http_response_code(503);
    header('Content-Type: application/json; charset=UTF-8');
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: no-store');
    header('X-Request-ID: ' . $requestId);
    echo json_encode([
        'error' => 'content_registry_unavailable',
        'requestId' => $requestId,
    ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
}
