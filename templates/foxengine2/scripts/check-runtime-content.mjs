import { access, readFile, readdir } from 'node:fs/promises'
import { extname, join, parse } from 'node:path'
import { repositoryRoot, themeRoot } from './theme-paths.mjs'
import { includesLocalized } from './i18n-test-utils.mjs'

const failures = []
async function exists(path) { try { await access(path); return true } catch { return false } }

const pagesDirectory = join(themeRoot, 'data', 'pages')
const badgesDirectory = join(themeRoot, 'data', 'badges')
const frontendPath = join(themeRoot, 'frontend.json')

if (!(await exists(pagesDirectory))) failures.push('standalone project HTML directory is missing: data/pages')
if (!(await exists(badgesDirectory))) failures.push('standalone badge HTML directory is missing: data/badges')

const frontend = JSON.parse(await readFile(frontendPath, 'utf8'))
const pageEntries = (await readdir(pagesDirectory, { withFileTypes: true }))
const projectFiles = pageEntries.filter((entry) => entry.isFile() && extname(entry.name).toLowerCase() === '.html').map((entry) => entry.name)
for (const entry of pageEntries.filter((candidate) => candidate.isFile() && extname(candidate.name).toLowerCase() === '.json')) {
  failures.push(`project page must be HTML, not JSON: data/pages/${entry.name}`)
}
if (projectFiles.length === 0) failures.push('data/pages must contain standalone .html pages')
if (!projectFiles.includes('start.html')) failures.push('/start runtime page is missing: data/pages/start.html')

const pageIds = new Set()
for (const name of projectFiles) {
  const id = parse(name).name
  if (!/^[a-z][a-z0-9-]{1,63}$/.test(id)) failures.push(`project HTML filename has invalid route id: ${name}`)
  if (pageIds.has(id)) failures.push(`duplicate project HTML id: ${id}`)
  pageIds.add(id)
  const html = await readFile(join(pagesDirectory, name), 'utf8')
  if (!/^\s*<article\b/i.test(html) || !/<\/article>\s*$/i.test(html)) failures.push(`${name} must be one complete root <article> page`)
  const projectMarkerCount = (html.match(/\bdata-project-page(?:=|\s|>)/gi) ?? []).length
  if (projectMarkerCount !== 1) failures.push(`${name} must contain exactly one data-project-page marker; found ${projectMarkerCount}`)
  const declaredId = html.match(/data-page-id\s*=\s*["']([^"']+)["']/i)?.[1]
  if (declaredId !== id) failures.push(`${name} data-page-id must equal filename id ${id}`)
  const h1Matches = html.match(/<h1\b[^>]*>[\s\S]*?<\/h1>/gi) ?? []
  if (h1Matches.length !== 1 || !/<h1\b[^>]*>\s*[^<\s]/i.test(h1Matches[0] ?? '')) failures.push(`${name} must contain exactly one non-empty h1`)
  if (/<(?:script|style|iframe|object|embed|form|input|button|textarea|select|option|link|meta|base|svg|math)\b/i.test(html)) failures.push(`${name} contains a forbidden executable or document element`)
  if (/\son[a-z]+\s*=/i.test(html) || /(?:javascript|vbscript|data\s*:\s*text\/html)\s*:/i.test(html)) failures.push(`${name} contains executable HTML`)
}

const badgeFiles = (await readdir(badgesDirectory, { withFileTypes: true }))
  .filter((entry) => entry.isFile())
  .map((entry) => entry.name)
const htmlFiles = badgeFiles.filter((name) => extname(name).toLowerCase() === '.html')
for (const name of badgeFiles.filter((file) => extname(file).toLowerCase() === '.json')) {
  failures.push(`badge page must be HTML, not JSON: data/badges/${name}`)
}
if (htmlFiles.length === 0) failures.push('data/badges must contain at least one standalone .html page')

const badgeSlugs = new Set()
const requiredMarkers = [
  'data-badge-page',
  'data-badge-name',
  'data-badge-slug',
  'data-badge-title',
  'data-badge-description',
  'data-badge-image',
  'data-badge-history',
]
for (const name of htmlFiles) {
  const slug = parse(name).name
  if (!/^[a-z0-9][a-z0-9-]{0,79}$/.test(slug)) failures.push(`badge HTML filename has invalid route slug: ${name}`)
  if (badgeSlugs.has(slug)) failures.push(`duplicate badge HTML slug: ${slug}`)
  badgeSlugs.add(slug)
  const html = await readFile(join(badgesDirectory, name), 'utf8')
  if (!/^\s*<article\b/i.test(html) || !/<\/article>\s*$/i.test(html)) failures.push(`${name} must be one complete root <article> page`)
  for (const marker of requiredMarkers) {
    const count = (html.match(new RegExp(`\\b${marker}(?:=|\\s|>)`, 'gi')) ?? []).length
    if (count !== 1) failures.push(`${name} must contain exactly one ${marker} marker; found ${count}`)
  }
  const declaredSlug = html.match(/data-badge-slug\s*=\s*["']([^"']+)["']/i)?.[1]
  if (declaredSlug !== slug) failures.push(`${name} data-badge-slug must equal filename slug ${slug}`)
  if (!/data-badge-name\s*=\s*["'][^"']+["']/i.test(html)) failures.push(`${name} must bind to a badgesList.badgeName through data-badge-name`)
  if (/<(?:script|style|iframe|object|embed|form|input|button|textarea|select|option|link|meta|base|svg|math)\b/i.test(html)) failures.push(`${name} contains a forbidden executable or document element`)
  if (/\son[a-z]+\s*=/i.test(html) || /(?:javascript|vbscript|data\s*:\s*text\/html)\s*:/i.test(html)) failures.push(`${name} contains executable HTML`)
}

const badgesRouteIndex = (frontend.routes ?? []).findIndex((route) => route?.name === 'badges')
const badgeRouteIndex = (frontend.routes ?? []).findIndex((route) => route?.name === 'badge')
const badgesRoute = frontend.routes?.[badgesRouteIndex]
const badgeRoute = frontend.routes?.[badgeRouteIndex]
if (badgesRoute?.path !== '/badges' || badgesRoute?.view !== 'BadgesView') failures.push('/badges must use BadgesView')
if (badgeRoute?.path !== '/badges/:id' || badgeRoute?.view !== 'BadgeView' || badgeRoute?.props !== true) failures.push('/badges/:id must use BadgeView with route props')
if (badgesRouteIndex < 0 || badgeRouteIndex < 0 || badgesRouteIndex >= badgeRouteIndex) failures.push('static /badges route must precede /badges/:id')
if (!(frontend.navigation ?? []).some((item) => item?.route === 'badges')) failures.push('badge catalog is missing from navigation')

const startRoute = (frontend.routes ?? []).find((route) => route?.name === 'start')
if (startRoute?.path !== '/start' || startRoute?.view !== 'StartGameView'
  || startRoute?.module !== 'GameScanner' || startRoute?.props?.pageId !== 'start') {
  failures.push('/start must use StartGameView with module GameScanner and pageId=start')
}
const startHtml = await readFile(join(pagesDirectory, 'start.html'), 'utf8').catch(() => '')
for (const marker of [
  'data-project-page="1"',
  'data-page-id="start"',
  'data-start-account-title',
  'data-start-account-description',
  'data-start-action="register"',
  'data-start-action="download"',
  'data-start-action="vk"',
  'data-start-action="discord"',
  'data-start-windows-icon',
  'data-start-download-label',
  'data-start-download-error',
]) {
  if (!startHtml.includes(marker)) failures.push(`start.html is missing runtime marker ${marker}`)
}
if (/<button/i.test(startHtml)) failures.push('start.html must use safe action anchors, not executable button elements')

const startController = await readFile(join(repositoryRoot, 'engine', 'classes', 'modules', 'GameScanner', 'client', 'views', 'StartGameView.vue'), 'utf8')
for (const token of ['loadStaticPages', "pageId: 'start'", 'StartGamePage', 'downloadBootstrapper', 'openExternal']) {
  if (!includesLocalized(startController, token)) failures.push(`StartGameView is missing runtime page contract ${token}`)
}
const startTemplate = await readFile(join(themeRoot, 'src', 'userOptions', 'pages', 'StartGame.vue'), 'utf8')
for (const token of ['StaticPageDefinition', 'hydratedHtml', 'DOMParser', 'data-start-action', 'v-html="hydratedHtml"']) {
  if (!includesLocalized(startTemplate, token)) failures.push(`StartGame.vue is missing runtime hydration contract ${token}`)
}
for (const forbidden of ['<h1>Начать игру</h1>', '<ol class="journey-steps">']) {
  if (startTemplate.includes(forbidden)) failures.push(`StartGame.vue still hardcodes page content: ${forbidden}`)
}

for (const route of frontend.routes ?? []) {
  if (route?.view !== 'StaticContentView') continue
  const pageId = route?.props?.pageId
  if (typeof pageId !== 'string' || !pageIds.has(pageId)) failures.push(`StaticContentView route ${route?.name} references missing runtime page ${String(pageId)}`)
}

const api = (await Promise.all([
  join(repositoryRoot, 'api', 'content.php'),
  join(repositoryRoot, 'api', 'src', 'FoxCMS', 'Api', 'Content', 'ContentApiApplication.php'),
  join(repositoryRoot, 'api', 'src', 'FoxCMS', 'Api', 'Content', 'BadgeCatalogService.php'),
].map((path) => readFile(path, 'utf8')))).join('\n')
for (const token of ['BadgeSlug::assign', 'ThemeBadgePageRepository', 'FROM `badgesList`', 'BadgeCatalogService', "'badges' => $this->badges", "'badge' => $this->badge", '$this->repository->exists', "$item['pageConfigured']"]) {
  if (!includesLocalized(api, token)) failures.push(`content API is missing contract ${token}`)
}
for (const forbidden of ['imageKey', 'pageNameKey', 'badgeNameKey === $slugKey', 'WHERE `badgeName` = :badgeName LIMIT 1']) {
  if (api.includes(forbidden)) failures.push(`badge page discovery still uses obsolete matching rule ${forbidden}`)
}
for (const forbidden of ['badge-pages.json', 'engine/data/content', 'readBadgePages']) {
  if (api.includes(forbidden)) failures.push(`content API still references obsolete badge registry ${forbidden}`)
}

const badgeSlug = await readFile(join(repositoryRoot, 'engine', 'classes', 'themes', 'BadgeSlug.class.php'), 'utf8')
for (const token of ['final class BadgeSlug', 'CYRILLIC_TRANSLITERATION', 'public static function fromName', 'public static function assign', "'ж' => 'zh'", "'я' => 'ya'", "preg_replace('/[^a-z0-9]+/'"]) {
  if (!includesLocalized(badgeSlug, token)) failures.push(`BadgeSlug is missing ${token}`)
}

const transliteration = Object.fromEntries(
  [...badgeSlug.matchAll(/'([^']+)'\s*=>\s*'([^']*)'/g)].map((match) => [match[1], match[2]]),
)
function expectedBadgeSlug(value) {
  const transliterated = [...value.toLocaleLowerCase('ru').normalize('NFD')]
    .map((character) => transliteration[character] ?? character)
    .join('')
    .replace(/\p{M}+/gu, '')
  return transliterated.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/-+/g, '-').replace(/^-|-$/g, '')
}
for (const [name, expected] of [
  ['EarlyUser', 'earlyuser'],
  ['Раннее Возрождение', 'rannee-vozrozhdenie'],
  ['Подсвинок', 'podsvinok'],
  ['LGBTQ+', 'lgbtq'],
]) {
  const actual = expectedBadgeSlug(name)
  if (actual !== expected) failures.push(`BadgeSlug transliteration mismatch: ${name} -> ${actual}, expected ${expected}`)
}

const projectRepository = await readFile(join(repositoryRoot, 'engine', 'classes', 'themes', 'ThemeContentRepository.class.php'), 'utf8')
for (const token of ['readProjectPages', 'saveProjectPages', "DIRECTORY_SEPARATOR . 'data'", "DIRECTORY_SEPARATOR . 'pages'", "'.html'", 'private function sanitize(', 'DOMDocument', 'LIBXML_NONET', 'rename($temporary, $path)']) {
  if (!includesLocalized(projectRepository, token)) failures.push(`ThemeContentRepository is missing ${token}`)
}
for (const forbidden of ['readBadgePage', 'saveBadgePage', 'badgePagesDirectory']) {
  if (projectRepository.includes(forbidden)) failures.push(`ThemeContentRepository still owns badge pages through ${forbidden}`)
}

const badgeRepository = await readFile(join(repositoryRoot, 'engine', 'classes', 'themes', 'ThemeBadgePageRepository.class.php'), 'utf8')
for (const token of ['final class ThemeBadgePageRepository', "'q'", 'public function exists(', 'public function read(', 'public function save(', 'public function move(', 'public function render(', 'private function sanitize(', "'.html'", 'DOMDocument', 'LIBXML_NONET', 'rename($temporary, $path)']) {
  if (!includesLocalized(badgeRepository, token)) failures.push(`ThemeBadgePageRepository is missing ${token}`)
}
const engineAutoload = await readFile(join(repositoryRoot, 'engine', 'autoload.php'), 'utf8')
for (const repository of ['ThemeContentRepository.class.php', 'BadgeSlug.class.php', 'ThemeBadgePageRepository.class.php']) {
  await readFile(join(repositoryRoot, 'engine', 'classes', 'themes', repository), 'utf8').catch(() => {
    failures.push(`Engine content core is missing ${repository}`)
  })
}
for (const token of ['RecursiveDirectoryIterator', "str_ends_with($entry->getFilename(), '.class.php')", '$legacyClassMap[strtolower($name)]']) {
  if (!engineAutoload.includes(token)) failures.push(`Engine autoload does not expose content classes through ${token}`)
}

const adminFacade = await readFile(join(repositoryRoot, 'engine', 'classes', 'modules', 'AdminPanel', 'AdminOptions.class.php'), 'utf8')
const adminContent = await readFile(join(repositoryRoot, 'engine', 'classes', 'modules', 'AdminPanel', 'AdminContentController.class.php'), 'utf8')
const adminCatalog = await readFile(join(repositoryRoot, 'engine', 'classes', 'modules', 'AdminPanel', 'AdminCatalogController.class.php'), 'utf8')
const adminBackend = `${adminFacade}
${adminContent}
${adminCatalog}`
for (const method of ['$this->logger->event(', '$this->logger->deviation(', '$this->logger->exception(']) {
  if (!adminBackend.includes(method)) failures.push(`Admin content structured logging is missing ${method}`)
}
for (const token of ["'content' => 'content'", "'saveProjectPages' => 'saveProjectPages'", "'saveBadgePage' => 'saveBadgePage'", "'deleteBadgePage' => 'deleteBadgePage'", 'ThemeBadgePageRepository', 'BadgeSlug::assign', '$this->badgePageRepository->exists($slug)', "'.html'", 'SELECT `id`, `badgeName`, `description`, `img` FROM `badgesList`']) {
  if (!includesLocalized(adminBackend, token)) failures.push(`Admin content backend is missing ${token}`)
}
for (const token of ["mb_strlen($badgeName) > 120", "preg_match('/[\\x00-\\x1F\\x7F]/u', $badgeName)", 'renameBadgeAssignments', '$this->badgePageRepository->move($oldSlug, $newSlug, $badgeName)']) {
  if (!includesLocalized(adminBackend, token)) failures.push(`Unicode badge settings contract is missing ${token}`)
}
const badgeSettingsStart = adminBackend.indexOf('private function saveBadgeCatalogEntry')
const badgeSettingsEnd = adminBackend.indexOf('private function saveGroupCatalogEntry', badgeSettingsStart)
const badgeSettingsMethod = adminBackend.slice(badgeSettingsStart, badgeSettingsEnd)
if (/preg_match\('\/\^\[A-Za-z/.test(badgeSettingsMethod) || /preg_match\('\/\^\[a-z/.test(badgeSettingsMethod)) {
  failures.push('badgeName must not be constrained to ASCII')
}

const adminClient = await readFile(join(repositoryRoot, 'engine', 'classes', 'modules', 'AdminPanel', 'client', 'useAdminPanel.ts'), 'utf8')
for (const token of ["'content'", "admPanel: 'content'", "admPanel: 'saveProjectPages'", "admPanel: 'saveBadgePage'", "admPanel: 'deleteBadgePage'", 'html: string', 'schema: 2']) {
  if (!includesLocalized(adminClient, token)) failures.push(`Admin content client is missing ${token}`)
}
const editor = await readFile(join(themeRoot, 'src', 'foxEngine', 'admin', 'Content.vue'), 'utf8')
for (const token of ['HTML-страницы бейджей', 'Полная HTML-разметка страницы проекта', 'data/pages/', 'CodeEditor', 'projectWorkspaceTab', 'badgeWorkspaceTab', 'StaticPage', 'BadgePage', 'Прямое превью', 'sanitizePreviewHtml', 'data-badge-history']) {
  if (!includesLocalized(editor, token)) failures.push(`Admin HTML editor is missing ${token}`)
}
for (const forbidden of ['projectPreviewDocument', 'badgePreviewDocument', 'srcdoc=', 'sandbox=""', '<iframe']) {
  if (editor.includes(forbidden)) failures.push(`Admin HTML preview must be direct, not standalone: ${forbidden}`)
}

const catalogView = await readFile(join(repositoryRoot, 'engine', 'client', 'views', 'BadgesView.vue'), 'utf8')
for (const token of ['loadBadges', 'Бейджи — {0}', '@theme/userOptions/pages/badges/Badges.vue']) {
  if (!includesLocalized(catalogView, token)) failures.push(`BadgesView is missing ${token}`)
}
const catalogTemplate = await readFile(join(themeRoot, 'src', 'userOptions', 'pages', 'badges', 'Badges.vue'), 'utf8')
for (const token of ['badges-table', 'badge.image', 'badge.title', 'badge.description', "name: 'badge'", 'badge.pageConfigured']) {
  if (!includesLocalized(catalogTemplate, token)) failures.push(`Badge catalog presentation is missing ${token}`)
}
const badgeTemplate = await readFile(join(themeRoot, 'src', 'userOptions', 'pages', 'badges', 'Badge.vue'), 'utf8')
if (!badgeTemplate.includes('v-html="badge.html"')) failures.push('Badge.vue must render the server-sanitized standalone HTML page')
const staticTemplate = await readFile(join(themeRoot, 'src', 'userOptions', 'content', 'StaticPage.vue'), 'utf8')
if (!staticTemplate.includes('v-html="page.html"')) failures.push('StaticPage.vue must render the server-sanitized standalone project HTML page')

const obsoletePaths = [
  'templates/foxengine2/datas',
  'templates/foxengine2/data/pages.json',
  'templates/foxengine2/data/badge-pages.json',
  'engine/data/content/badges.json',
  'engine/data/content/static-pages.json',
]
for (const relative of obsoletePaths) if (await exists(join(repositoryRoot, relative))) failures.push(`obsolete content source still exists: ${relative}`)

if (failures.length) {
  console.error('Runtime content contract failed:')
  for (const failure of [...new Set(failures)]) console.error(`- ${failure}`)
  process.exit(1)
}
console.log(`Runtime content passed: ${pageIds.size} standalone project HTML pages, ${badgeSlugs.size} standalone badge HTML pages, DB-backed /badges index.`)
