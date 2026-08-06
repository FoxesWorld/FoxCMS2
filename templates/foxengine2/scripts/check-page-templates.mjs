import { access, readFile, readdir, stat } from 'node:fs/promises'
import { basename, join, relative } from 'node:path'
import { compileRuntimeTemplateSource } from './runtime-template-compiler.mjs'
import { repositoryRoot, themeRoot } from './theme-paths.mjs'

const failures = []
const bridgeUrl = `/templates/${basename(themeRoot)}/assets/runtime/vue-runtime.js`
const definitions = [
  { id: 'static-content', file: 'StaticContent.tpl', host: 'templates/foxengine2/src/userOptions/content/StaticContent.vue', markers: ['rules-badge-claim', 'static-content-page--rules'] },
  { id: 'start-game', file: 'StartGame.tpl', host: 'templates/foxengine2/src/userOptions/pages/StartGame.vue', markers: ['start-page-runtime', 'v-html="hydratedHtml"'] },
  { id: 'badges', file: 'Badges.tpl', host: 'templates/foxengine2/src/userOptions/pages/badges/Badges.vue', markers: ['badges-directory__header', 'badges-table'] },
  { id: 'badge', file: 'Badge.tpl', host: 'templates/foxengine2/src/userOptions/pages/badges/Badge.vue', markers: ['badge-runtime-page', 'v-html="badge.html"'] },
  { id: 'achievements', file: 'Achievements.tpl', host: 'engine/client/views/AchievementsView.vue', markers: ['achievements-player-search', 'achievements-metrics', 'achievements-grid'] },
  { id: 'achievement-statistics', file: 'achievements/StatisticsTree.tpl', host: 'engine/client/achievements/AchievementStatisticsTree.vue', markers: ['achievement-statistics__metrics', 'achievement-statistics__tree'] },
  { id: 'achievement-tree-node', file: 'achievements/TreeNode.tpl', host: 'engine/client/achievements/AchievementTreeNode.vue', markers: ['achievement-tree-node__players', 'achievement-tree-node__children'] },
  { id: 'achievement-profile-panel', file: 'achievements/ProfilePanel.tpl', host: 'templates/foxengine2/src/userOptions/userOptions/profile/ProfileAchievements.vue', markers: ['profile-achievements__summary', 'profile-achievement-card__progress'] },
]
const readRepository = (path) => readFile(join(repositoryRoot, path), 'utf8')

function parseTemplate(source, expectedId) {
  const root = source.match(/^\s*<fox-page-template\b([^>]*)>([\s\S]*)<\/fox-page-template>\s*$/u)
  if (!root) throw new Error('missing single fox-page-template root')
  const attributes = Object.fromEntries([...root[1].matchAll(/([A-Za-z_:][A-Za-z0-9_.:-]*)\s*=\s*"([^"]*)"/gu)].map((match) => [match[1], match[2]]))
  if (attributes.id !== expectedId || attributes.schema !== '1') throw new Error('invalid id/schema')
  const bodyStart = root[2].indexOf('<fox-template-body>')
  const bodyEnd = root[2].lastIndexOf('</fox-template-body>')
  if (bodyStart < 0 || bodyEnd <= bodyStart) throw new Error('missing fox-template-body')
  return root[2].slice(bodyStart + '<fox-template-body>'.length, bodyEnd).trim()
}

const parsed = new Map()
for (const definition of definitions) {
  const path = join(themeRoot, 'pages', 'templates', definition.file)
  try {
    await access(path)
    const source = await readFile(path, 'utf8')
    const body = parseTemplate(source, definition.id)
    const compiled = compileRuntimeTemplateSource(source, definition.id, bridgeUrl)
    if (compiled.body !== body || !compiled.module.includes('export function render(')
      || !compiled.module.includes(`export const templateId = "${definition.id}"`)) {
      failures.push(`${definition.file} does not produce a stable CSP-safe render module`)
    }
    for (const marker of definition.markers) if (!body.includes(marker)) failures.push(`${definition.file} is missing ${marker}`)
    parsed.set(definition.id, body)
  } catch (error) {
    failures.push(`${definition.file} is invalid: ${error.message}`)
  }

  const host = await readRepository(definition.host)
  for (const token of ['<RuntimeTpl', ':module-url=', `runtimePageTemplate('${definition.id}')`, 'loadRuntimePageTemplates']) {
    if (!host.includes(token)) failures.push(`${relative(repositoryRoot, join(repositoryRoot, definition.host))} is missing ${token}`)
  }
  for (const marker of definition.markers) {
    if (host.includes(marker)) failures.push(`${definition.host} still compiles page DOM marker ${marker}`)
  }
  if (host.includes('<style')) failures.push(`${definition.host} still owns page CSS`)
}

const runtimeStore = await readRepository('engine/client/runtime/pageTemplates.ts')
const runtimeHost = await readRepository('engine/client/runtime/RuntimeTpl.vue')
const helper = await readRepository('engine/classes/themes/ThemeRuntimeTplDocument.class.php')
const compiler = await readRepository('engine/classes/themes/ThemeRuntimeTplCompiler.class.php')
const pageStorage = await readRepository('engine/classes/themes/ThemePageStorage.class.php')
const repository = await readRepository('engine/classes/themes/ThemePageTemplateRepository.class.php')
const contentApi = await readRepository('api/src/FoxCMS/Api/Content/ContentApiApplication.php')
const adminContentController = await readRepository('engine/classes/modules/AdminPanel/AdminContentController.class.php')
const adminRuntimeController = await readRepository('engine/classes/modules/AdminPanel/AdminRuntimeOptionsController.class.php')
const adminEditor = await readRepository('templates/foxengine2/src/foxEngine/admin/Content.vue')
const runtimeEditor = await readRepository('templates/foxengine2/src/foxEngine/admin/RuntimeOptions.vue')
const adminTpl = await readRepository('templates/foxengine2/userOptions/AdminPanel.tpl')
const main = await readRepository('templates/foxengine2/src/main.ts')

for (const token of ["loadContentRegistry<unknown>('page-templates')", 'installRuntimePageTemplates', 'runtimePageTemplate(']) {
  if (!runtimeStore.includes(token)) failures.push(`page template runtime store is missing ${token}`)
}
for (const token of ['import(/* @vite-ignore */ moduleUrl)', 'render: loaded.render', '<component :is="compiled"']) {
  if (!runtimeHost.includes(token)) failures.push(`RuntimeTpl is missing ${token}`)
}
for (const token of ['final class ThemeRuntimeTplDocument', 'validateBody(', 'replaceRootAttribute(', 'public static function write(']) {
  if (!helper.includes(token)) failures.push(`shared TPL boundary is missing ${token}`)
}

for (const token of ['final class ThemeRuntimeTplCompiler', 'proc_open(', "['bypass_shell' => true]", 'publish(string $id', 'moduleUrl']) {
  if (!compiler.includes(token)) failures.push(`CSP-safe TPL compiler is missing ${token}`)
}
if (runtimeHost.includes('template: source') || runtimeHost.includes('new Function') || runtimeHost.includes('eval(')) {
  failures.push('RuntimeTpl still performs browser-side template evaluation')
}
for (const token of ['final class ThemePageStorage', "DIRECTORY_SEPARATOR . 'pages'", "DIRECTORY_SEPARATOR . 'content'", "DIRECTORY_SEPARATOR . 'templates'"]) {
  if (!pageStorage.includes(token)) failures.push(`unified page storage is missing ${token}`)
}
for (const token of ["require_once __DIR__ . '/ThemePageStorage.class.php';", "require_once __DIR__ . '/ThemeRuntimeTplDocument.class.php';", "require_once __DIR__ . '/ThemeRuntimeTplCompiler.class.php';", 'final class ThemePageTemplateRepository', "'static-content' =>", "'achievements' =>", "'achievement-statistics' =>", "'achievement-tree-node' =>", 'saveTemplate(string $id, string $source)', '$this->compiler->publish', '$this->compiler->ensure']) {
  if (!repository.includes(token)) failures.push(`page TPL repository is missing ${token}`)
}
for (const token of ["'page-templates' =>", 'ThemePageTemplateRepository']) {
  if (!contentApi.includes(token)) failures.push(`content API is missing ${token}`)
}
for (const token of ['private ThemePageTemplateRepository $pageTemplateRepository', "'pageTemplates' => $this->pageTemplateRepository->read(true)", 'public function savePageTemplate()', '$this->pageTemplateRepository->saveTemplate', 'pageTemplatesStorageReady']) {
  if (!adminContentController.includes(token)) failures.push(`admin Pages boundary is missing ${token}`)
}
for (const token of ['RuntimePageTemplatesDocument', 'props.pageTemplates?.templates', 'saveSelectedPageTemplate', 'pages/templates/']) {
  if (!adminEditor.includes(token)) failures.push(`Pages editor is missing ${token}`)
}
for (const token of [':page-templates="runtimePageTemplatesDraft"', ':page-templates-storage-ready="runtimePageTemplatesStorageReady"', '@save-page-template="savePageTemplate"']) {
  if (!adminTpl.includes(token)) failures.push(`AdminPanel.tpl is missing ${token}`)
}
for (const forbidden of ['ThemePageTemplateRepository', 'pageTemplates']) {
  if (adminRuntimeController.includes(forbidden)) failures.push(`Runtime options controller still owns page templates through ${forbidden}`)
}
if (runtimeEditor.includes('RuntimePageTemplatesDocument') || runtimeEditor.includes('props.pageTemplates')) {
  failures.push('Runtime Options editor still duplicates the Pages template editor')
}
if (!main.includes("import './styles/pages-achievements.css'")) failures.push('page/achievement runtime stylesheet is not registered')

const runtimeDirectory = join(themeRoot, 'assets', 'runtime')
const chunksDirectory = join(runtimeDirectory, 'chunks')
const modulesDirectory = join(runtimeDirectory, 'templates')
try {
  const chunks = (await readdir(chunksDirectory)).filter((name) => name.endsWith('.js'))
  const applicationFiles = [join(runtimeDirectory, 'theme.js'), join(runtimeDirectory, 'vue-runtime.js'),
    ...chunks.map((name) => join(chunksDirectory, name))]
  const javascript = (await Promise.all(applicationFiles.map((path) => readFile(path, 'utf8')))).join('\n')
  for (const definition of definitions) {
    for (const marker of definition.markers) if (javascript.includes(marker)) failures.push(`page TPL DOM leaked into application JavaScript: ${marker}`)
    const source = await readFile(join(themeRoot, 'pages', 'templates', definition.file), 'utf8')
    const revision = Number(source.match(/\brevision="(\d+)"/u)?.[1] ?? 1)
    const moduleFile = join(modulesDirectory, `${definition.id}.${revision}.js`)
    const module = await readFile(moduleFile, 'utf8')
    for (const token of [`from "${bridgeUrl}"`, 'export function render(', `export const templateId = "${definition.id}"`]) {
      if (!module.includes(token)) failures.push(`${definition.id} render cache is missing ${token}`)
    }
    if (module.includes('new Function') || module.includes('eval(')) failures.push(`${definition.id} render cache violates CSP`)
  }
  if (javascript.includes('new Function') || javascript.includes('eval(')) failures.push('application JavaScript contains eval-like execution')
  for (const [prefix, maximum] of [['AchievementsView-', 8 * 1024], ['AchievementStatisticsTree-', 8 * 1024], ['BadgesView-', 6 * 1024]]) {
    const chunk = chunks.find((name) => name.startsWith(prefix))
    if (!chunk) failures.push(`production host chunk is missing: ${prefix}*.js`)
    else if ((await stat(join(chunksDirectory, chunk))).size > maximum) failures.push(`${prefix} host is monolithic again`)
  }
} catch (error) {
  failures.push(`production runtime artifacts unavailable: ${error.message}`)
}

if (failures.length) {
  console.error('Page TPL contract failed:')
  for (const failure of [...new Set(failures)]) console.error(`- ${failure}`)
  process.exit(1)
}
console.log('Page TPL contract passed: TPL files remain runtime-editable source data, revisioned render modules are CSP-safe derivatives, and page HTML is absent from application chunks.')
