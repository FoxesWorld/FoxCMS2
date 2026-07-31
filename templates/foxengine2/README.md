# FoxesCraft template: foxengine2

`foxengine2` — автономный Vue 3-шаблон FoxCMS. Его файловая композиция продолжает структуру legacy FoxEngine, но не возвращает Smarty, `.tpl`, `.ftpl`, jQuery или browser runtime старого движка.

## Структура шаблона

```text
templates/foxengine2/
├── theme.json              технический manifest оболочки и assets
├── frontend.json           маршруты, навигация и legacy aliases
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

`npm run build` записывает production bundle только в `assets/runtime`. `npm run check` проверяет manifest, legacy-style файловую композицию, маршруты, controller/theme boundary, PHP, UUID identity, удалённый legacy runtime и bundle budget.

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
