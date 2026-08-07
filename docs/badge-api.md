# FoxCMS public badge API

## Endpoints

Return the complete public badge catalog:

```text
GET /api/badges/
HEAD /api/badges/
```

Return one badge:

```text
GET /api/badges/?id=<identifier>
HEAD /api/badges/?id=<identifier>
```

The `id` parameter accepts the public badge slug, numeric database ID, `badgeName` or title. Text matching is case-insensitive.

## Catalog response

```json
{
  "schemaVersion": 1,
  "badges": [
    {
      "id": "arasaka",
      "databaseId": 7,
      "badgeName": "Arasaka",
      "title": "Arasaka",
      "description": "Корпоративный бейдж.",
      "image": "https://foxescraft.ru/uploads/badges/arasaka.svg",
      "badgeImg": "https://foxescraft.ru/uploads/badges/arasaka.svg",
      "imagePath": "/uploads/badges/arasaka.svg",
      "imageMimeType": "image/svg+xml",
      "pageConfigured": true
    }
  ],
  "total": 1
}
```

The single-badge response uses the same item contract under `badge`:

```json
{
  "schemaVersion": 1,
  "badge": {
    "id": "support",
    "databaseId": 8,
    "badgeName": "Support",
    "title": "Support",
    "description": "Поддержка проекта.",
    "image": "https://foxescraft.ru/uploads/badges/support.webp",
    "badgeImg": "https://foxescraft.ru/uploads/badges/support.webp",
    "imagePath": "/uploads/badges/support.webp",
    "imageMimeType": "image/webp",
    "pageConfigured": false
  }
}
```

Badge definitions come from the canonical `badgesList` catalog. A badge is returned even when it has no separate HTML page. The endpoint does not expose the page HTML.

Both `image` and compatibility alias `badgeImg` contain the absolute icon URL. Root-relative and safe relative paths are expanded with `FOXESCRAFT_PUBLIC_BASE_URL`. Supported MIME detection includes SVG, PNG, JPEG, WebP and GIF.

## Errors

An unknown badge returns HTTP `404` with `badge_not_found`. An invalid identifier returns HTTP `400` with `badge_identifier_invalid`. Database or runtime failures return HTTP `503` with `badge_catalog_unavailable`.

Successful responses include an ETag and use:

```text
Cache-Control: public, max-age=60, stale-while-revalidate=300
```

## Verification

```powershell
php -l api\badges\index.php
php -l api\src\FoxCMS\Api\Badge\BadgeApiApplication.php
php -l api\src\FoxCMS\Api\Badge\BadgePresenter.php
php -l api\src\FoxCMS\Api\Badge\BadgeIdentifierMatcher.php
php api\tests\badge-api-contract.php
```
