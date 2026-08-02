import { readFile } from 'node:fs/promises'
import { join } from 'node:path'
import { repositoryRoot, themeRoot } from './theme-paths.mjs'

const [page, mods, styles, route] = await Promise.all([
  readFile(join(themeRoot, 'src', 'foxEngine', 'serverPage', 'ServerPage.vue'), 'utf8'),
  readFile(join(themeRoot, 'src', 'foxEngine', 'serverPage', 'ServerMods.vue'), 'utf8'),
  readFile(join(themeRoot, 'assets', 'css', 'legacy-continuation.css'), 'utf8'),
  readFile(join(repositoryRoot, 'engine', 'classes', 'modules', 'GameScanner', 'client', 'views', 'ServerView.vue'), 'utf8'),
])

const failures = []
for (const token of [
  'class="server-hero"',
  'class="server-hero__cover"',
  'class="server-hero__overlay"',
  'class="server-hero__content"',
  'class="server-hero__header"',
  'class="server-hero__status"',
  'class="server-panel server-page__about"',
  '<footer>',
  '<ServerMods :mods="mods" />',
]) {
  if (!page.includes(token)) failures.push(`ServerPage is missing ${token}`)
}
const coverIndex = page.indexOf('class="server-hero__cover"')
const overlayIndex = page.indexOf('class="server-hero__overlay"')
const contentIndex = page.indexOf('class="server-hero__content"')
if (!(coverIndex >= 0 && coverIndex < overlayIndex && overlayIndex < contentIndex)) {
  failures.push('Server hero layer order must be cover -> overlay -> content')
}
for (const token of [
  '.server-hero{position:relative',
  '.server-hero__cover,.server-hero__overlay{position:absolute',
  '.server-hero__overlay{background:linear-gradient',
  '.server-hero__content{position:relative',
  '.server-hero__header{display:flex',
  '.server-panel{padding:',
  '.server-page__about>header{padding-bottom:',
  '.server-page__about>p{margin:',
  '.server-page__about>footer{display:grid',
]) {
  if (!styles.includes(token)) failures.push(`Server page CSS is missing ${token}`)
}
const headerRule = styles.match(/\.server-hero__header\{([^}]*)\}/)?.[1] ?? ''
if (headerRule.includes('background:')) failures.push('Server hero header must not own the overlay background')
if (!mods.includes('Основные модификации') || !mods.includes('class="mods-grid"')) {
  failures.push('ServerMods lightweight section contract is missing')
}
for (const token of ['Некорректное имя сервера.', 'Сервер не найден.', 'Не удалось загрузить сведения о сервере.']) {
  if (!route.includes(token)) failures.push(`Server route error copy is missing: ${token}`)
}
if (failures.length) {
  console.error('Server page contract failed:')
  for (const failure of failures) console.error(`- ${failure}`)
  process.exit(1)
}
console.log('Server page contract passed: layered hero, explicit overlay and information panels are active.')
