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
9. Route data is declarative JSON. Runtime profile/admin HTML belongs to theme-owned TPL files, not JavaScript chunks.
10. Runtime TPL files are parsed and validated by PHP. Their immutable revision render modules are compiled server-side and loaded by the runtime-only Vue build.
11. Runtime TPL files may reference only whitelisted executable adapters. Arbitrary component names and executable HTML elements are rejected.
12. TPL support must remain compatible with `script-src 'self'`; `unsafe-eval` and browser-side template compilation are forbidden.

## Theme contract

```text
templates/<theme>/
├── theme.json
├── frontend.json
├── userOptions/
│   ├── ProfileSettings.tpl
│   └── AdminPanel.tpl
├── pages/
│   ├── content/                 sanitized runtime page bodies (`*.html`)
│   └── templates/               runtime Vue presentation (`*.tpl`)
│       ├── StaticContent.tpl
│       ├── StartGame.tpl
│       ├── Badges.tpl
│       ├── Badge.tpl
│       └── achievements/*.tpl
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


## Runtime userOptions TPL contract

The editable source of truth is always:

```text
templates/<theme>/userOptions/ProfileSettings.tpl
templates/<theme>/userOptions/AdminPanel.tpl
```

Each file contains declarative `fox-*` metadata and Vue-compatible HTML inside `<fox-template-body>`. PHP parses and validates the files on every `registry=user-options` request. The public registry returns runtime metadata and an immutable revisioned `moduleUrl`; raw HTML and TPL source are returned only by the administrative boundary.

The browser uses `vue.runtime.esm-bundler.js`. `RuntimeTpl.vue` imports the same-origin render module from `assets/runtime/templates/<template>.<revision>.js`; it never passes a string to the Vue compiler and never calls `eval` or `Function`.

Administrative writes use `AdminRuntimeOptionsController` and `ThemeUserOptionsRepository`. A save performs this order:

1. validate the submitted TPL and its adapter allowlist;
2. increment `revision` and update `updated-at`;
3. compile a CSP-safe immutable render module with the standalone server compiler;
4. atomically replace the `.tpl` source;
5. retain recent module revisions so already-open clients keep working;
6. install the returned runtime document in the current client.

The generated module is a derivative cache, not the source of page data. Editing does not run Vite and does not modify `theme.js` or `chunks/*`.


## Unified page storage

All theme-owned page documents live under one domain root:

```text
templates/<theme>/pages/
├── content/<route-id>.html
└── templates/<runtime-template>.tpl
```

`content` documents contain server-sanitized page bodies. `templates` documents contain validated Vue presentation and produce revisioned CSP-safe render caches. The formats retain separate validators, but they no longer use separate storage roots. `ThemePageStorage` is the only resolver for both directories; `templates/<theme>/data/pages` is obsolete and forbidden.

## Runtime page TPL contract

Runtime-editable route and achievement presentation lives in:

```text
templates/<theme>/pages/templates/StaticContent.tpl
templates/<theme>/pages/templates/StartGame.tpl
templates/<theme>/pages/templates/Badges.tpl
templates/<theme>/pages/templates/Badge.tpl
templates/<theme>/pages/templates/Achievements.tpl
templates/<theme>/pages/templates/achievements/StatisticsTree.tpl
templates/<theme>/pages/templates/achievements/TreeNode.tpl
templates/<theme>/pages/templates/achievements/ProfilePanel.tpl
```

`ThemePageTemplateRepository` exposes validated runtime descriptors through `registry=page-templates`; raw HTML and source remain admin-only. Thin `.vue` controllers own API calls, state and lifecycle, then supply a fixed context/component registry to `RuntimeTpl.vue`.

The initial build emits:

```text
assets/runtime/vue-runtime.js
assets/runtime/server/runtime-template-compiler.mjs
assets/runtime/templates/<template>.<revision>.js
```

The server compiler is bundled and does not require deployed `node_modules`. It needs a Node.js executable, configurable with `FOXESCRAFT_NODE_BINARY`. The CSP remains `script-src 'self'`; no runtime JavaScript artifact may contain `new Function` or `eval(`.

### Static project pages

Routes such as `/about`, `/rules`, `/privacy`, `/funding` and `/roadmap` use `pages/templates/StaticContent.tpl` as their runtime-editable presentation shell. Their individual sanitized content remains independent runtime data under `pages/content/<id>.html`. Editing a page body therefore does not rebuild the client or duplicate the common loading, error and rules-reward presentation.

Stable theme entry assets are emitted in HTML with a SHA-256 query revision, so a no-store shell cannot reuse an incompatible week-cached `theme.js` or `theme.css` after deployment.
