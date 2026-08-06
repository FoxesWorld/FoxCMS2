# FoxesCraft template: foxengine2

`foxengine2` — автономный Vue 3-шаблон FoxCMS. Его файловая композиция продолжает структуру legacy FoxEngine, но не возвращает Smarty, jQuery или browser runtime старого движка. Девять проверяемых `.tpl` используются как runtime-редактируемый формат HTML для userOptions, страниц и achievements.

## Структура шаблона

```text
templates/foxengine2/
├── theme.json              технический manifest оболочки и assets
├── frontend.json           маршруты, навигация и legacy aliases
├── userOptions/
│   ├── ProfileSettings.tpl  runtime HTML настроек профиля
│   └── AdminPanel.tpl       runtime HTML административной панели
├── pages/
│   ├── content/             HTML-содержимое маршрутов
│   └── templates/           runtime TPL-представления страниц
├── index.html              PHP-rendered shell
├── src/
│   ├── Main.vue            аналог legacy main.tpl
│   ├── Header.vue          аналог header.tpl
│   ├── Logo.vue            аналог logo.tpl
│   ├── UserBlock.vue       аналог userBlock.tpl
│   ├── Slider.vue          аналог slider.tpl
│   ├── RightBlock.vue      аналог right-block.tpl
│   ├── Footer.vue          аналог footer.tpl
│   ├── CookiePopup.vue
│   ├── ButtonUp.vue
│   ├── foxEngine/          повторно используемые блоки шаблона
│   └── userOptions/
│       ├── content/        контентные и гостевые экраны
│       ├── pages/          самостоятельные страницы
│       └── userOptions/    личный кабинет и административные страницы
├── assets/
├── scripts/
├── package.json
├── vite.config.ts
└── tsconfig.json
```

## Граница ответственности

Файл маршрута в `frontend.json` указывает движковый controller view. Controller получает данные, проверяет ввод и вызывает API. HTML он делегирует соответствующему файлу текущего шаблона.

```text
engine/client + engine/classes/modules/*/client
  → API, state, validation, permissions, lifecycle

templates/foxengine2/src
  → HTML, тексты, порядок блоков, визуальная композиция
```

В теме запрещены прямые business/runtime-контракты: `foxesApi`, `userAction`, `user_doaction`, `sysRequest`, `admPanel` и `fetch()`.

## Команды

Запускаются из `templates/foxengine2`:

```bash
npm ci
npm run dev
npm run typecheck
npm run build
npm run check
```

`npm run build` записывает application bundle в `assets/runtime`, автономный server-side TPL compiler в `assets/runtime/server` и начальные revision render-cache в `assets/runtime/templates`. `npm run check` проверяет manifest, legacy-style файловую композицию, маршруты, controller/theme boundary, PHP, UUID identity, удалённый legacy runtime и bundle budget.

## Запрещённые пути

```text
/frontend
engine/data/frontend.json
engine/classes/modules/*/frontend.json
engine/client/components
templates/foxengine2/app
templates/foxengine2/src/pages
templates/foxengine2/src/components
templates/foxengine2/data/pages
```



## Единое хранилище страниц

```text
pages/
├── content/<route-id>.html
└── templates/<runtime-template>.tpl
```

`pages/content` хранит санитизированные тела страниц, а `pages/templates` — их интерактивные Vue-представления. Оба типа документов относятся к одной сущности страницы и разрешаются через `ThemePageStorage`. Старый каталог `data/pages` запрещён проверками.

## Runtime userOptions TPL

Канонические редактируемые данные находятся только в:

```text
templates/foxengine2/userOptions/ProfileSettings.tpl
templates/foxengine2/userOptions/AdminPanel.tpl
```

TPL содержит `fox-*` metadata и HTML внутри `<fox-template-body>`. Публичный Content API возвращает metadata и `moduleUrl`, но не возвращает HTML или исходник. Административный endpoint возвращает исходный TPL для CodeMirror.

При сохранении repository сначала валидирует и компилирует новую revision в:

```text
assets/runtime/templates/<template-id>.<revision>.js
```

Только после успешной компиляции `.tpl` атомарно переключается на новую revision. Это производный render-cache: он не является источником данных, не попадает в `theme.js` или `chunks/*` и пересоздаётся при каждом runtime-save без Vite rebuild.

Браузер использует runtime-only Vue и динамически импортирует same-origin render-cache. CSP не ослабляется:

```text
script-src 'self'
```

`unsafe-eval`, `new Function` и browser runtime compiler отсутствуют.

## Runtime page templates

Страницы и achievements хранятся в:

```text
pages/templates/StaticContent.tpl
pages/templates/StartGame.tpl
pages/templates/Badges.tpl
pages/templates/Badge.tpl
pages/templates/Achievements.tpl
pages/templates/achievements/StatisticsTree.tpl
pages/templates/achievements/TreeNode.tpl
pages/templates/achievements/ProfilePanel.tpl
```

Vue SFC остаются контроллерами состояния и API. HTML находится в TPL и отсутствует в application chunks. Инструмент **Runtime TPL** редактирует userOptions и pages через единый admin boundary.

Для production build создаётся автономный compiler:

```text
assets/runtime/server/runtime-template-compiler.mjs
```

Он не зависит от развернутого `node_modules`; требуется только Node.js. Путь можно задать через `FOXESCRAFT_NODE_BINARY`.

Проверки контракта:

```bash
npm run check:runtime-options
npm run check:page-templates
npm run check:csp-runtime
```

### Static project pages

Routes such as `/about`, `/rules`, `/privacy`, `/funding` and `/roadmap` use `pages/templates/StaticContent.tpl` as their runtime-editable presentation shell. Their individual sanitized content remains independent runtime data under `pages/content/<id>.html`. Editing a page body therefore does not rebuild the client or duplicate the common loading, error and rules-reward presentation.

Stable theme entry assets are emitted in HTML with a SHA-256 query revision, so a no-store shell cannot reuse an incompatible week-cached `theme.js` or `theme.css` after deployment.
