# Theme and frontend engine boundary

## Invariants

0. The repository-level `/frontend` directory must not exist; each template owns its build toolchain.

1. The active theme is resolved from `templates/<theme>/theme.json`.
2. Every theme owns its complete route contract in `templates/<theme>/frontend.json`.
3. `engine/data/frontend.json` must not exist.
4. Backend modules must not contain `frontend.json` route manifests.
5. Route controllers remain engine-owned: `engine/client/views` or `engine/classes/modules/<Module>/client/views`.
6. Controllers own API calls, validation and state, but delegate HTML to the selected theme.
7. The theme owns shell composition and presentation files under `src/foxEngine` and `src/userOptions`.
8. PHP loads, validates and filters the selected theme's routes by authentication, user group and referenced backend module.
9. Route data is declarative JSON; page HTML is Vue SFC markup, never JSON content.

## Theme contract

```text
templates/<theme>/
├── theme.json
├── frontend.json
├── index.html
├── tsconfig.json
├── src/
│   ├── Main.vue
│   ├── Header.vue
│   ├── Footer.vue
│   ├── foxEngine/
│   └── userOptions/
└── assets/
```

`theme.json` points to the route manifest:

```json
{
  "schema": 1,
  "name": "example-theme",
  "shell": "index.html",
  "frontend": "frontend.json"
}
```

`frontend.json` may declare:

- routes;
- navigation areas;
- legacy URL aliases;
- capabilities;
- engine endpoints used by the client runtime.

A route backed by a module view declares that module explicitly:

```json
{
  "path": "/auth",
  "name": "auth",
  "view": "AuthView",
  "module": "AuthReg",
  "auth": "guest"
}
```

The engine rejects unknown module references. It omits routes whose module or route-level group policy does not allow the current user.

## Adding a module view

1. Add a thin `client/views/ExampleView.vue` controller under the owning backend module.
2. Add its HTML component under the theme, normally in `src/userOptions` or `src/foxEngine`.
3. Import that theme component from the controller; keep API calls out of the theme.
4. Do not add a route manifest to the module.
5. Add the route to every theme that should expose that controller and set `module`.
6. Run `npm run check` from `templates/<theme>/`.

## Adding a theme

1. Create a directory under `templates/`.
2. Provide `theme.json`, `frontend.json`, `index.html`, `tsconfig.json`, `src/` and `assets/`.
3. Select any subset or composition of available engine/module views in the theme's `frontend.json`.
4. Run `npm run build` inside that template directory.
5. Select it at runtime with `FOXESCRAFT_TEMPLATE=<name>`.

Two themes can therefore expose different routes, navigation and capabilities while using the same backend engine and view implementations.
