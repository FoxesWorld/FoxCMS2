# FoxCMS public user profile API

## Endpoint

```text
GET /api/users/?uuid=<user-uuid>
HEAD /api/users/?uuid=<user-uuid>
```

The endpoint is read-only and does not require authentication. UUID is the immutable FoxCMS identity. Both canonical `8-4-4-4-12` and compact 32-hexadecimal representations are accepted; the response always contains the canonical representation.

## Successful response

```json
{
  "schemaVersion": 1,
  "user": {
    "uuid": "0198de8f-1e14-7bf7-8ef8-b8ed6c2bd4d1",
    "login": "Kayla",
    "fullName": "Kayla Verner",
    "displayName": "Kayla Verner",
    "status": "Architecting autonomous intelligence",
    "location": "Amsterdam",
    "colorScheme": "#5bd08b",
    "profilePhoto": "https://foxescraft.ru/uploads/users/0198de8f-1e14-7bf7-8ef8-b8ed6c2bd4d1/profile.webp",
    "profilePhotoPath": "/uploads/users/0198de8f-1e14-7bf7-8ef8-b8ed6c2bd4d1/profile.webp",
    "registeredAt": 1700000000,
    "lastSeenAt": 1700000100,
    "group": {
      "tag": "admin",
      "name": "Администраторы",
      "color": "#e85d5d"
    },
    "badges": [],
    "serversOnline": {}
  }
}
```

`fullName`, `status`, `location`, `profilePhoto`, `profilePhotoPath`, `registeredAt` and `lastSeenAt` may be `null`. `displayName` falls back to `login` when the full name is empty. Relative photo paths are expanded with `FOXESCRAFT_PUBLIC_BASE_URL` when it is configured.

The public contract intentionally excludes email addresses, balances, permissions, password hashes, tokens, numeric database IDs, IP addresses, hardware reports and session data.

## Errors

Missing UUID:

```json
{
  "error": "user_uuid_required",
  "message": "Параметр uuid обязателен."
}
```

Invalid UUID returns HTTP `400` with `user_uuid_invalid`. An unknown UUID returns HTTP `404` with `user_not_found`. Unexpected database/runtime failures return HTTP `503` with the standard request identifier.

Successful responses use `Cache-Control: public, max-age=30, stale-while-revalidate=60` and an ETag. Error responses are not cached.

## Verification

```powershell
php -l api\users\index.php
php -l api\src\FoxCMS\Api\User\PublicUserProfileRepository.php
php -l api\src\FoxCMS\Api\User\UserProfilePresenter.php
php -l api\src\FoxCMS\Api\User\UserProfileApiApplication.php
php api\tests\user-profile-contract.php
```
