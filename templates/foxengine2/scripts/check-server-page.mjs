import { readFile } from 'node:fs/promises'
import { join } from 'node:path'
import { repositoryRoot, themeRoot } from './theme-paths.mjs'

const [page, mods, styles, route, admin, adminState, systemRequests, monitor, monitorService, serverParser, uploadInspector, serverEditor, tagifyInput, adminSettingsStyles, imageMigration, localeSource] = await Promise.all([
  readFile(join(themeRoot, 'src', 'foxEngine', 'serverPage', 'ServerPage.vue'), 'utf8'),
  readFile(join(themeRoot, 'src', 'foxEngine', 'serverPage', 'ServerMods.vue'), 'utf8'),
  readFile(join(themeRoot, 'assets', 'css', 'legacy-continuation.css'), 'utf8'),
  readFile(join(repositoryRoot, 'engine', 'classes', 'modules', 'GameScanner', 'client', 'views', 'ServerView.vue'), 'utf8'),
  readFile(join(repositoryRoot, 'engine', 'classes', 'modules', 'AdminPanel', 'AdminOptions.class.php'), 'utf8'),
  readFile(join(repositoryRoot, 'engine', 'classes', 'modules', 'AdminPanel', 'client', 'useAdminPanel.ts'), 'utf8'),
  readFile(join(repositoryRoot, 'engine', 'SystemRequests.class.php'), 'utf8'),
  readFile(join(themeRoot, 'src', 'foxEngine', 'monitor', 'Monitoring.vue'), 'utf8'),
  readFile(join(repositoryRoot, 'engine', 'classes', 'modules', 'Monitoring', 'Monitoring.class.php'), 'utf8'),
  readFile(join(repositoryRoot, 'engine', 'classes', 'utils', 'ServerParser', '1.0.0', 'ServerParser.class.php'), 'utf8'),
  readFile(join(repositoryRoot, 'engine', 'classes', 'uploads', 'UploadFileInspector.class.php'), 'utf8'),
  readFile(join(themeRoot, 'src', 'foxEngine', 'admin', 'servers', 'ServerEditor.vue'), 'utf8'),
  readFile(join(themeRoot, 'src', 'foxEngine', 'admin', 'SeoTagifyInput.vue'), 'utf8'),
  readFile(join(themeRoot, 'src', 'styles', 'admin-site-settings.css'), 'utf8'),
  readFile(join(repositoryRoot, 'database', 'migrations', '018_expand_server_image_column.sql'), 'utf8'),
  readFile(join(repositoryRoot, 'engine', 'client', 'i18n', 'locales', 'ru-RU.json'), 'utf8'),
])
const locale = JSON.parse(localeSource)
const includesTranslation = (source, message) => Object.entries(locale).some(
  ([key, value]) => value === message && source.includes(`t('${key}'`),
)

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
if (!includesTranslation(mods, 'Основные модификации') || !mods.includes('class="mods-grid"')) {
  failures.push('ServerMods lightweight section contract is missing')
}
for (const token of ['Некорректное имя сервера.', 'Сервер не найден.', 'Не удалось загрузить сведения о сервере.']) {
  if (!includesTranslation(route, token)) failures.push(`Server route error copy is missing from i18n: ${token}`)
}

for (const token of [
  'normalizeServerIgnoreDirectories',
  'normalizeServerMods',
  'serverBooleanStorageModes',
  'catch (DatabaseException $error)',
  "$this->db->run('UPDATE `servers` SET ",
  'Структурированные данные сервера содержат некорректный JSON.',
]) {
  if (!admin.includes(token)) failures.push(`Server save backend is missing ${token}`)
}
for (const token of [
  'normalizeServerArray(server.ignoreDirs, true)',
  'normalizeServerArray(serverDraft.ignoreDirs, true)',
  'normalizeServerArray(server.modsInfo)',
  'Number(server.enabled) === 1',
]) {
  if (!adminState.includes(token)) failures.push(`Server save client is missing ${token}`)
}
for (const token of [
  "'emptyReason' => 'no_accessible_servers'",
  "'message' => 'Для вашей группы сейчас нет доступных серверов.'",
  "($servers['error'] ?? null) === 'ServerNotFound'",
]) {
  if (!systemRequests.includes(token)) failures.push(`Empty monitoring response is missing ${token}`)
}
if (!includesTranslation(monitor, 'Нет доступных серверов') || !monitor.includes('{{ emptyMessage }}')) {
  failures.push('Monitoring empty state must explain that the user has no accessible servers')
}
if (!monitorService.includes('!array_is_list($decoded)') || !monitorService.includes("$server['serverName'] ?? ''")) {
  failures.push('Monitoring service must ignore malformed or non-list server payloads')
}
if (!serverParser.includes("filter_var($server['enabled'] ?? false, FILTER_VALIDATE_BOOLEAN)")) {
  failures.push('Server parser must support string and numeric legacy enabled flags')
}
if (!uploadInspector.includes('!is_file($path) || !is_readable($path)')) {
  failures.push('Existing upload references must fail with an explicit file-not-found response')
}
if (!serverEditor.includes("import SeoTagifyInput from '../SeoTagifyInput.vue'")
  || !serverEditor.includes('v-model="ignoreDirectories"')
  || !includesTranslation(serverEditor, 'Добавьте каталог и нажмите Enter')) {
  failures.push('Server ignoreDirs must use the shared administrative Tagify input')
}
if (serverEditor.includes(':model-value="draft.ignoreDirs"') || serverEditor.includes("samplesFor('ignoreDirs')")) {
  failures.push('Server ignoreDirs must not fall back to the generic JSON editor')
}
if (!tagifyInput.includes('class="admin-tagify-field"')
  || !tagifyInput.includes("import Tagify from '@yaireo/tagify'")) {
  failures.push('Shared administrative Tagify wrapper is missing')
}
if (!adminSettingsStyles.includes('.admin-tagify-field .tagify')
  || !adminSettingsStyles.includes('.admin-tagify-field__hint')) {
  failures.push('Shared administrative Tagify styling is missing')
}
if (!imageMigration.includes('018: widen legacy server image references')
  || !imageMigration.includes('`serverImage` VARCHAR(512)')) {
  failures.push('Migration 018 must widen legacy serverImage columns')
}
if (!admin.includes("$field === 'serverImage'")
  || !admin.includes('Примените миграцию 018')) {
  failures.push('Server save errors must identify legacy serverImage overflow')
}
if (failures.length) {
  console.error('Server page contract failed:')
  for (const failure of failures) console.error(`- ${failure}`)
  process.exit(1)
}
console.log('Server contract passed: page layers, Tagify ignore directories, normalized persistence, schema width, and non-error empty monitoring are enforced.')
