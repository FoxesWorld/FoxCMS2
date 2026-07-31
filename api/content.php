<?php

declare(strict_types=1);

$registries = [
    'badges' => 'badges.json',
    'static-pages' => 'static-pages.json',
];
$name = isset($_GET['registry']) ? (string)$_GET['registry'] : '';
if (!isset($registries[$name])) {
    http_response_code(404);
    header('Content-Type: application/json; charset=UTF-8');
    echo '{"error":"content_registry_not_found"}';
    exit;
}

$path = dirname(__DIR__) . '/engine/data/content/' . $registries[$name];
$content = is_file($path) ? file_get_contents($path) : false;
if ($content === false) {
    http_response_code(503);
    header('Content-Type: application/json; charset=UTF-8');
    echo '{"error":"content_registry_unavailable"}';
    exit;
}

$etag = '"' . hash('sha256', $content) . '"';
header('Content-Type: application/json; charset=UTF-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: public, max-age=300, stale-while-revalidate=600');
header('ETag: ' . $etag);
if (trim((string)($_SERVER['HTTP_IF_NONE_MATCH'] ?? '')) === $etag) {
    http_response_code(304);
    exit;
}
echo $content;
