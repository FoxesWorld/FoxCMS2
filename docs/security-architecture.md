# FoxCMS2 security and application architecture

## Purpose

FoxCMS2 uses explicit boundaries between process configuration, HTTP transport, application use-cases, persistence and presentation. New code must prefer constructor-injected objects and namespaced classes over global state, superglobals or procedural helpers.

## Repository autoloading

The repository root `autoload.php` is the fallback PSR-4 loader for:

- `FoxCMS\Shared\` from `src/FoxCMS/Shared`;
- `FoxCMS\Api\` from `api/src/FoxCMS/Api`;
- `FoxCMS\Engine\` from `engine/src/FoxCMS/Engine`.

`engine/autoload.php` retains the case-insensitive legacy global-class map while delegating all namespaced loading to the root loader. This permits incremental migration without allowing new namespaced code to depend on filename scanning or manual `require_once` lists.

## Process environment

`FoxCMS\Shared\Environment\Environment` is the canonical process-configuration boundary. It owns `.env` loading and typed access through `string`, `boolean`, `integer` and `csv`.

The old `foxEnv*` functions remain deprecated compatibility adapters. New application code receives `Environment` explicitly through its composition root or application context.

Environment files do not override values already supplied by the process, web server or container. Variable names and declarations are validated before they are added to the process environment.

## HTTP request and response boundaries

Raw request globals are restricted to request factories such as `HttpRequest::fromGlobals`, API `Request::fromGlobals`, `NetworkContext::fromGlobals` and the shared environment boundary.

Business services receive request objects or validated value arrays. In particular, launcher runtime selection uses:

```text
Request
  -> RuntimeRequest::fromRequest() / RuntimeRequest::fromArray()
  -> RuntimeResolver
  -> RuntimeCatalog
```

No runtime-selection code temporarily rewrites `$_GET`.

`FoxCMS\Shared\Http\ResponseHeaders` is the shared response-header policy for the API, engine JSON responses and authlib. It validates status codes and header names and rejects CR/LF header injection. JSON responders remain responsible for serialization and telemetry, while the shared policy owns safe header emission.

## Bootstrap manifest CORS

The bootstrap manifest is public for read operations, but hardware inventory is a write operation:

- `GET` may return `Access-Control-Allow-Origin: *`;
- native launcher `POST` requests without an `Origin` header remain supported;
- browser-origin `POST` and its preflight require an exact allowed HTTP(S) origin;
- credentials, query strings, fragments, paths and CR/LF characters are rejected in origins.

Allowed browser write origins are configured through:

```dotenv
FOXESCRAFT_BOOTSTRAP_CORS_ORIGINS=https://launcher.example.com,https://admin.example.com
```

The origin derived from `FOXESCRAFT_PUBLIC_BASE_URL` is also included automatically.

## User application layer

`UserActions` is a compatibility transport facade for the historical `user_doaction` contract. It delegates to focused namespaced use-case controllers:

- `UserRewardController`;
- `UserBrowserSessionController`;
- `UserNotificationController`;
- `UserProfileQueryController`.

`AuthenticatedUserGuard` centralizes authenticated UUID validation and guest reward restrictions. `UserActionResponder` centralizes JSON responses and rejection telemetry.

Controllers do not read superglobals and do not emit raw headers.

## Authentication lifecycle

`AuthManager` remains the legacy action facade and maintenance-policy coordinator. Remembered-session restoration, browser-session registry cleanup and logout are owned by `AuthSessionLifecycle`.

`RememberCookie` is the single cookie policy used by password login, legacy migration, remembered restoration, rotation and logout. Remember cookies always use:

- `Secure` according to the validated request network context;
- `HttpOnly`;
- `SameSite=Lax`;
- root path `/`.

Authentication persistence is UUID-bound. Raw remember tokens are never stored in the database; stored values are digests.

## Monitoring storage

`FoxesMon` is a transport-neutral monitoring domain service. `SystemRequests` owns the HTTP response.

`MonitoringRecordStore` owns maximum-player record persistence. It:

- creates storage directories with restricted permissions;
- rejects symbolic-link storage;
- locks reads with `LOCK_SH`;
- compares and writes under the same `LOCK_EX` critical section;
- uses truncate, write and flush before releasing the lock;
- never decreases a stored maximum.

This prevents lost updates when multiple monitoring requests execute concurrently.

## News image host policy

News image conversion receives trusted `NetworkContext` and `Environment` objects. It does not trust raw `HTTP_HOST`. Local upload URLs are accepted only for the validated application host or explicitly configured `FOXESCRAFT_PUBLIC_HOSTS` entries.

## Verification

The release pipeline includes `check:security-boundaries`, which performs source contracts and executable PHP tests for:

- PSR-4 and environment ownership;
- response header validation;
- restricted CORS;
- runtime request validation;
- remember-cookie policy;
- monitoring record locking and persistence;
- news image host validation;
- absence of `eval` and `unserialize` in the protected boundaries.

Security checks complement, rather than replace, production tests with a real database, reverse proxy and filesystem permissions.
