# FoxCMS API architecture

`api/` contains standalone HTTP entrypoints and a namespaced application layer. Public URLs remain stable; endpoint files only bootstrap the corresponding application class.

## Layout

```text
api/
├── autoload.php                      # FoxCMS\Api PSR-4 loader
├── index.php                         # Game achievements router
├── news.php                          # Public news endpoint
├── content.php                       # Theme content registries
├── health.php                        # Health endpoint
├── bootstrap/                        # Stable public bootstrap URLs
├── launcher/                         # Private launcher bridge URLs
├── src/FoxCMS/Api/
│   ├── Core/                         # Request, response, context, errors, DB factory
│   ├── Game/                         # Achievement API orchestration
│   ├── News/                         # News presentation and image encoding
│   ├── Content/                      # Content registries and badge catalog
│   ├── Health/                       # Health-check application
│   ├── Bootstrap/                    # Manifest, artifacts, hardware inventory, JDK selection
│   └── Launcher/                     # Bridge authentication, cache and runtime bridge
└── tests/architecture-smoke.php
```

## Architectural rules

1. **Entrypoints contain no business logic.** They load `autoload.php`, create a request/context, and dispatch an application class.
2. **All application classes use the `FoxCMS\Api` namespace.** Legacy engine classes remain global until the engine itself is migrated.
3. **HTTP details stay in controllers and Core.** Domain services return values or throw `HttpException`; they do not emit responses.
4. **Configuration access is centralized.** Main API endpoints use `ApplicationContext`; bootstrap endpoints use `BootstrapConfig` and validated `BootstrapSettings`.
5. **Database construction is centralized.** Main API code uses `DatabaseFactory`; bootstrap hardware inventory owns its isolated PDO connection because it uses the bootstrap configuration schema.
6. **Filesystem boundaries are explicit.** `PublishedArtifactInspector` and `RuntimeArchiveLocator` reject traversal, symlinks, and paths outside configured storage. ZIP inspection uses `ZipArchive` when available and falls back to `PharData`.
7. **No API-level global helper functions.** The JDK resolver is split into stateless runtime classes for request parsing, metadata, archive inspection, selection and orchestration.

## Adding an endpoint

Create an application/controller under `src/FoxCMS/Api/<Domain>/`, use `Request` and `JsonResponse`, and leave the public PHP file as a minimal adapter. Shared limits, patterns and defaults belong in constants or validated settings objects rather than inline literals spread across endpoints.

## Validation

```powershell
php -l api\index.php
php api\tests\architecture-smoke.php
php scripts\check-bootstrap-hardware.php
python scripts\verify-deployment.py
```

The deployment verifier also checks theme-owned resources and may report failures outside `api/`.
