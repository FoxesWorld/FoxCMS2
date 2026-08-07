<?php

declare(strict_types=1);

use FoxCMS\Api\Badge\BadgeIdentifierMatcher;
use FoxCMS\Api\Badge\BadgePresenter;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__) . '/autoload.php';

$presenter = new BadgePresenter('https://foxescraft.ru/');
$badge = $presenter->present([
    'id' => 'arasaka',
    'databaseId' => 7,
    'badgeName' => 'Arasaka',
    'title' => 'Arasaka',
    'description' => 'Корпоративный бейдж.',
    'image' => '/uploads/badges/arasaka.svg',
    'html' => '<article>must not leak</article>',
    'pageConfigured' => true,
]);

assertSame('arasaka', $badge['id'], 'Public badge ID must be preserved');
assertSame(7, $badge['databaseId'], 'Database badge ID must be preserved');
assertSame('https://foxescraft.ru/uploads/badges/arasaka.svg', $badge['image'], 'Badge image must be absolute');
assertSame($badge['image'], $badge['badgeImg'], 'Legacy badgeImg alias must match image');
assertSame('/uploads/badges/arasaka.svg', $badge['imagePath'], 'Badge image path must remain available');
assertSame('image/svg+xml', $badge['imageMimeType'], 'SVG MIME type must be detected');
assertSame(true, $badge['pageConfigured'], 'Badge page state must be preserved');
assertSame(false, array_key_exists('html', $badge), 'Badge metadata endpoint must not expose page HTML');

$webp = $presenter->present([
    'id' => 'support',
    'databaseId' => 8,
    'badgeName' => 'Support',
    'image' => 'uploads/badges/support.webp',
]);
assertSame('https://foxescraft.ru/uploads/badges/support.webp', $webp['image'], 'Relative badge image must be resolved');
assertSame('image/webp', $webp['imageMimeType'], 'WebP MIME type must be detected');

$unsafe = $presenter->present([
    'id' => 'unsafe',
    'databaseId' => 9,
    'badgeName' => 'Unsafe',
    'image' => 'javascript:alert(1)',
]);
assertSame(null, $unsafe['image'], 'Unsafe badge image schemes must be rejected');

$matcher = new BadgeIdentifierMatcher();
$catalog = [$badge, $webp];
assertSame('arasaka', $matcher->find($catalog, 'ARASAKA')['id'] ?? null, 'Public ID lookup must ignore case');
assertSame('arasaka', $matcher->find($catalog, '7')['id'] ?? null, 'Database ID lookup must work');
assertSame('support', $matcher->find($catalog, 'Support')['id'] ?? null, 'Badge name lookup must work');
assertSame(null, $matcher->find($catalog, 'missing'), 'Unknown badge lookup must return null');

fwrite(STDOUT, "FoxCMS badge API contract test passed.\n");

function assertSame(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException(
            $message . '; expected=' . var_export($expected, true)
            . '; actual=' . var_export($actual, true),
        );
    }
}
