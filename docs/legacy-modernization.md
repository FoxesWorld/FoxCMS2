# FoxesCraft 3.0 modernization

## Status

The repository-level legacy migration is complete. FoxCMS now uses an explicit PHP application bootstrap, deterministic module manifest, Vue 3 theme runtime, prepared PDO access, versioned schema migrations and UUID-bound user identity.

## Runtime architecture

```text
index.php
  -> engine/bootstrap.php
  -> engine/autoload.php
  -> Application (legacy-compatible adapter)
      -> FoxCMS\Engine\Application\ApplicationKernel
          -> PhpSessionStarter
          -> LegacyLibraries (procedural compatibility boundary)
          -> ApplicationContextFactory
              -> PDO facade + structured Logger
              -> HttpRequest + NetworkContext
              -> UserSessionSynchronizer
              -> ModulesLoader
          -> MaintenanceGate
          -> SystemRequests (HTTP facade)
              -> FoxCMS\Engine\Launcher\LauncherAccess
              -> FoxCMS\Engine\Launcher\LauncherRequestController
          -> FrontendResponder
  -> templates/<theme>/ Vue 3 + TypeScript application
```

The browser receives a whitelist JSON bootstrap containing public account fields, including the canonical user UUID, site metadata and a session-bound CSRF token. Password hashes, remember tokens, launcher tokens, internal permissions and credentials are never serialized.

## Engine decomposition contract

New engine code belongs to the `FoxCMS\Engine` namespace and is loaded from `engine/src/FoxCMS/Engine` through the engine autoloader. Existing global classes remain available through an explicit compatibility map so migration can proceed incrementally without a flag-day rename of every module.

The runtime follows these ownership boundaries:

- `bootstrap.php` configures process-level concerns and constructs the application adapter;
- `ApplicationKernel` controls request phases but does not implement infrastructure or rendering details;
- `ApplicationContextFactory` is the composition root for request-scoped dependencies;
- `UserSessionSynchronizer` owns database refresh, activity touch, browser-session registry synchronization and related telemetry;
- `MaintenanceGate` owns maintenance authorization and the 503 response;
- `FrontendResponder` owns theme resolution and frontend bootstrap rendering;
- `SystemRequests` remains a transport facade; launcher authentication and playtime commands are delegated to launcher services;
- `AdminOptions` remains a compatibility facade; cohesive administrative use-cases are extracted into focused controllers such as `AdminRewardController`, `AdminContentController`, `AdminServerController`, `AdminCatalogController` and `AdminUserController`; shared `AdminResponder`, `AdminRequestPayload`, `AdminBadgeOptionsProvider` and schema guards remove repeated transport, lookup and validation code;
- vendored libraries and guarded utilities remain behind `UtilityLoader` and are not folded into the application namespace.

Presentation ownership is strict: reusable runtime controls may live under `engine/client`, while theme-specific layout and visual components belong to `templates/<theme>`. Backend modules do not own route manifests; the selected theme owns route composition.

## User identity contract

`users.uuid` is the immutable account identity. New accounts receive an RFC 9562 UUIDv7 during registration. `users.login` is a unique, mutable username used only to resolve a human-entered login or locate a public profile.

After resolution, ownership and persistence use UUID:

- PHP session identity and authorization checks;
- remember-token updates and logout;
- profile/admin mutations;
- password reset tokens;
- launcher sessions and Minecraft profile IDs;
- playtime and hardware reports;
- user badges and user-owned filesystem paths;
- avatar, skin and cape storage.

The internal representation is canonical (`xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx`). Minecraft protocol responses use the same UUID without hyphens. Login-derived MD5 identifiers are accepted only as a temporary read fallback while `scripts/migrate-user-storage.php` moves historical files.

## Database lifecycle

Schema changes are stored in `database/migrations` and applied by:

```bash
php scripts/migrate.php --status
php scripts/migrate.php --dry-run
php scripts/migrate.php
```

Migration `003_uuid_user_identity.sql` converts historical accounts, launcher sessions, badge assignments, password resets and hardware reports to UUID identity. It deliberately invalidates obsolete login-derived associations when they cannot be resolved safely.

Migration `004_repair_legacy_schema.sql` reconciles databases upgraded from FoxEngine-era installations with the current runtime contract. It conditionally creates missing tables and columns, including `users.serversOnline`, without dropping existing user data. See `docs/database-repair.md`.

Use a separate schema-owner account for migrations through process-only `FOXESCRAFT_MIGRATION_DB_*` variables. The runtime database user should not have `CREATE`, `ALTER` or `DROP` privileges.

After migration 003, move legacy user files:

```bash
php scripts/migrate-user-storage.php --dry-run
php scripts/migrate-user-storage.php
```

## Security contracts

- `HttpRequest` owns bounded, normalized input and uploaded files.
- `NetworkContext` trusts forwarding headers only from configured proxy addresses.
- `UserSession` stores only whitelisted public/session fields and enforces idle and absolute expiry.
- State-changing browser operations require same-origin cookies and a session-bound CSRF token.
- Passwords use `password_hash`; historical double-MD5 values are upgraded only after successful authentication.
- Remember and launcher tokens are random bearer values whose SHA-256 digests are stored in the database.
- Logs use bounded JSON Lines, file locking and automatic secret redaction.
- User-provided values are validated before SQL, filesystem or protocol use.
- Health readiness fails for pending migrations, incomplete UUID schema, missing theme assets or unwritable runtime directories.

## Removed legacy

- FoxEngine, Vue 2, jQuery, Bootstrap runtime and browser plugin bundles;
- Smarty and `.tpl`/`.ftpl` page templates;
- `init.php`, `initHelper`, `RequestHandler` and static request/user globals;
- runtime module scanning and `incOptions.json` metadata;
- SSV, SessionManager, CheckUserAccess and unreachable utilities;
- login-derived account ownership and launcher session columns;
- dynamic SQL helpers, runtime DDL and public secret-bearing payloads;
- inline handlers/styles, backup directories and committed current-source credentials.

## Release gates

`npm run check` performs Vue typecheck/build, asset and route validation, PHP parsing and architecture checks, API contract checks, UUID identity enforcement, secret scanning, removed-runtime checks, zero-regression legacy audit and bundle-budget validation.

The aggregate pipeline also invokes `check:achievements`. The engine and theme gates pass independently; the aggregate remains blocked until the separate Forge 1.7.10 achievements target and game API integration are completed.

The UUID gate rejects mutation by login/user ID, legacy identity columns, login-derived user storage and launcher sessions not keyed by `userUuid`.

## Runtime acceptance

Static checks do not replace infrastructure tests. Production acceptance requires PHP 8.1+, MariaDB/MySQL, migrations through `003`, writable runtime directories and smoke tests for registration, login, login rename, remember-cookie restoration, password reset, launcher join/hasJoined, skin/cape migration and playtime persistence.
