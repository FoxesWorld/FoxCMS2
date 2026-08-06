import { access, readFile, readdir, stat } from 'node:fs/promises'
import { dirname, join, resolve } from 'node:path'
import { fileURLToPath } from 'node:url'

const themeRoot = resolve(dirname(fileURLToPath(import.meta.url)), '..')
const repositoryRoot = resolve(themeRoot, '..', '..')
const read = (path) => readFile(resolve(repositoryRoot, path), 'utf8')
const failures = []
const requireText = (source, token, message) => { if (!source.includes(token)) failures.push(message) }
const forbidText = (source, token, message) => { if (source.includes(token)) failures.push(message) }

function attributes(source) {
  const result = {}
  for (const match of source.matchAll(/([A-Za-z_:][A-Za-z0-9_.:-]*)\s*=\s*("[^"]*"|'[^']*')/gu)) {
    result[match[1].toLowerCase()] = match[2].slice(1, -1)
      .replaceAll('&quot;', '"').replaceAll('&amp;', '&').replaceAll('&lt;', '<').replaceAll('&gt;', '>')
  }
  return result
}
function parseTpl(source, expectedId) {
  const root = source.match(/^\s*<fox-user-options-template\b([^>]*)>([\s\S]*)<\/fox-user-options-template>\s*$/u)
  if (!root) throw new Error(`${expectedId} lacks a single fox-user-options-template root`)
  const rootAttrs = attributes(root[1])
  if (rootAttrs.id !== expectedId || rootAttrs.schema !== '1') throw new Error(`${expectedId} has invalid id/schema`)
  const body = root[2].match(/<fox-template-body\b[^>]*>([\s\S]*?)<\/fox-template-body>/u)?.[1]?.trim()
  if (!body) throw new Error(`${expectedId} lacks fox-template-body HTML`)
  const profile = [...root[2].matchAll(/<fox-profile-option\b([^>]*)\/>/gu)].map((match) => attributes(match[1]))
  const categories = [...root[2].matchAll(/<fox-admin-category\b([^>]*)\/>/gu)].map((match) => attributes(match[1]))
  const tools = [...root[2].matchAll(/<fox-admin-tool\b([^>]*)\/>/gu)].map((match) => attributes(match[1]))
  return { root: rootAttrs, body, profile, categories, tools }
}

const profilePath = resolve(themeRoot, 'userOptions/ProfileSettings.tpl')
const adminPath = resolve(themeRoot, 'userOptions/AdminPanel.tpl')
let profile = null
let admin = null
try { profile = parseTpl(await readFile(profilePath, 'utf8'), 'profile-settings') }
catch (error) { failures.push(`ProfileSettings.tpl is invalid: ${error.message}`) }
try { admin = parseTpl(await readFile(adminPath, 'utf8'), 'admin-panel') }
catch (error) { failures.push(`AdminPanel.tpl is invalid: ${error.message}`) }
try { await access(resolve(themeRoot, 'data/user-options.json')); failures.push('obsolete data/user-options.json still exists') }
catch { /* expected */ }

const profileBindings = new Map([
  ['profile', 'ProfileOption'], ['appearance', 'AppearanceOption'], ['security', 'SecurityOption'],
])
const adminBindings = new Map([
  ['overview', ['Overview', 'overview']], ['logs', ['Logs', 'logs']], ['users', ['Users', 'users']],
  ['infobox', ['Catalogs', 'catalogs', 'infobox']], ['badges', ['Catalogs', 'catalogs', 'badges']],
  ['rewards', ['Rewards', 'rewards']], ['groups', ['Catalogs', 'catalogs', 'groups']],
  ['content', ['Content', 'content']], ['slides', ['Slides', 'slides']], ['settings', ['SiteSettings', 'settings']],
  ['runtime-options', ['RuntimeOptions', 'runtime-options']], ['servers', ['Servers', 'servers']],
  ['files', ['FileManager', 'files']], ['maintenance', ['Maintenance', 'maintenance']],
])
if (profile) {
  if (profile.profile.length !== profileBindings.size) failures.push('ProfileSettings.tpl adapter set is incomplete')
  for (const option of profile.profile) {
    if (profileBindings.get(option.id) !== option.component) failures.push(`invalid profile TPL adapter: ${String(option.id)}`)
  }
  if (!profile.profile.some((entry) => entry.enabled === 'true')) failures.push('at least one profile TPL option must be enabled')
  for (const token of ['settings-page', 'runtimeProfileOptions', '<ProfileOption', '<AppearanceOption', '<SecurityOption']) {
    requireText(profile.body, token, `ProfileSettings.tpl body is missing ${token}`)
  }
}
if (admin) {
  const categoryIds = new Set(admin.categories.map((entry) => entry.id))
  if (admin.categories.length === 0 || categoryIds.size !== admin.categories.length) failures.push('AdminPanel.tpl categories are invalid')
  if (admin.tools.length !== adminBindings.size) failures.push('AdminPanel.tpl tool adapter set is incomplete')
  for (const tool of admin.tools) {
    const expected = adminBindings.get(tool.id)
    if (!expected || tool.component !== expected[0] || tool.tab !== expected[1]) failures.push(`invalid admin TPL adapter: ${String(tool.id)}`)
    if (expected?.[2] && tool.catalog !== expected[2]) failures.push(`invalid admin TPL catalog: ${String(tool.id)}`)
    if (!categoryIds.has(tool.category)) failures.push(`admin TPL tool has unknown category: ${String(tool.id)}`)
  }
  const protectedTool = admin.tools.find((entry) => entry.id === 'runtime-options')
  if (protectedTool?.enabled !== 'true' || protectedTool?.protected !== 'true') failures.push('runtime-options TPL tool must remain enabled/protected')
  for (const token of ['class="admin-breadcrumbs"', '<AdminDashboard', '<AdminCategoryView', '<AdminRuntimeOptions']) {
    requireText(admin.body, token, `AdminPanel.tpl body is missing ${token}`)
  }
}

const profileHost = await read('templates/foxengine2/src/userOptions/userOptions/ProfileSettings.vue')
const profileController = await read('engine/classes/modules/UserSettings/client/views/ProfileSettingsView.vue')
const adminHost = await read('templates/foxengine2/src/userOptions/userOptions/AdminPanel.vue')
const adminComposable = await read('engine/classes/modules/AdminPanel/client/useAdminPanel.ts')
const runtimeStore = await read('engine/client/runtime/userOptions.ts')
const runtimeHost = await read('engine/client/runtime/RuntimeTpl.vue')
const runtimeEditor = await read('templates/foxengine2/src/foxEngine/admin/RuntimeOptions.vue')
const runtimeCompiler = await read('engine/classes/themes/ThemeRuntimeTplCompiler.class.php')
const repository = await read('engine/classes/themes/ThemeUserOptionsRepository.class.php')
const adminController = await read('engine/classes/modules/AdminPanel/AdminRuntimeOptionsController.class.php')
const adminOptions = await read('engine/classes/modules/AdminPanel/AdminOptions.class.php')
const contentApi = await read('api/src/FoxCMS/Api/Content/ContentApiApplication.php')
const vite = await read('templates/foxengine2/vite.config.ts')

requireText(runtimeStore, "loadContentRegistry<unknown>('user-options')", 'runtime store must fetch the public TPL registry')
requireText(runtimeStore, 'templates: { profileSettings:', 'runtime document must expose runtime TPL descriptors')
requireText(runtimeStore, 'runtimeModuleUrlPattern', 'runtime store must validate revisioned same-origin render modules')
requireText(runtimeHost, 'import(/* @vite-ignore */ moduleUrl)', 'RuntimeTpl must import the precompiled revision module')
requireText(runtimeHost, 'render: loaded.render', 'RuntimeTpl must mount the imported render function')
forbidText(runtimeHost, 'template: source', 'RuntimeTpl must not invoke the browser Vue compiler')
forbidText(runtimeHost, 'new Function', 'RuntimeTpl must not use eval-like execution')
requireText(vite, 'vue.runtime.esm-bundler.js', 'Vite must use the CSP-safe runtime-only Vue build')
requireText(vite, "'vue-runtime': resolve(engineClient, 'runtime', 'vueRuntimeBridge.ts')", 'Vite must emit the stable Vue runtime bridge')
requireText(vite, "preserveEntrySignatures: 'strict'", 'Vite must preserve Vue runtime bridge exports')
requireText(profileHost, '<RuntimeTpl', 'ProfileSettings.vue must be a thin RuntimeTpl host')
requireText(profileHost, 'profileTemplate.moduleUrl', 'ProfileSettings.vue must consume the runtime render module URL')
forbidText(profileHost, 'settings-page', 'ProfileSettings HTML remains compiled in the SFC')
requireText(profileController, 'runtimeProfileOptions', 'profile controller must normalize URL tabs against runtime TPL metadata')
requireText(adminHost, '<RuntimeTpl', 'AdminPanel.vue must be a thin RuntimeTpl host')
requireText(adminHost, 'adminTemplate.moduleUrl', 'AdminPanel.vue must consume the runtime render module URL')
forbidText(adminHost, 'class="admin-breadcrumbs"', 'AdminPanel HTML remains compiled in the SFC')
requireText(adminComposable, 'runtimeAdminCategories', 'admin categories must come from parsed AdminPanel.tpl metadata')
requireText(adminComposable, "JSON.stringify({ templateId, source })", 'admin composable must persist the selected raw TPL source')
requireText(runtimeEditor, '<CodeEditor', 'runtime TPL editor must use the code editor')
requireText(runtimeEditor, 'selected.value.source', 'runtime TPL editor must edit raw source')
requireText(repository, "'profile-settings' => 'ProfileSettings.tpl'", 'server repository must own ProfileSettings.tpl')
requireText(repository, "'admin-panel' => 'AdminPanel.tpl'", 'server repository must own AdminPanel.tpl')
requireText(repository, '<fox-template-body>', 'server repository must parse fox-template-body')
requireText(repository, 'saveTemplate(string $id, string $source)', 'server repository must expose an atomic TPL save boundary')
requireText(repository, '$this->compiler->publish', 'server repository must compile the new revision before publishing its TPL')
requireText(repository, '$this->compiler->ensure', 'server repository must ensure the derivative render cache exists')
requireText(repository, 'ALLOWED_COMPONENTS', 'server repository must whitelist Vue component adapters')
requireText(adminController, '$this->repository->saveTemplate', 'admin controller must persist raw TPL through the repository')
requireText(adminOptions, "'saveUserOptions' => 'saveUserOptions'", 'admin dispatcher must register saveUserOptions')
requireText(contentApi, "->read(false)", 'public content API must omit raw administrative TPL source')
for (const token of ['final class ThemeRuntimeTplCompiler', 'proc_open(', "['bypass_shell' => true]", 'moduleUrl']) {
  requireText(runtimeCompiler, token, `runtime TPL compiler is missing ${token}`)
}

const chunksDirectory = resolve(themeRoot, 'assets/runtime/chunks')
try {
  await access(chunksDirectory)
  const chunks = await readdir(chunksDirectory)
  for (const prefix of ['ProfileOption-', 'AppearanceOption-', 'SecurityOption-', 'RuntimeOptions-', 'Users-', 'Servers-', 'Content-', 'FileManager-']) {
    if (!chunks.some((name) => name.startsWith(prefix) && name.endsWith('.js'))) failures.push(`production implementation chunk is missing: ${prefix}*.js`)
  }
  const adminChunk = chunks.find((name) => name.startsWith('AdminView-') && name.endsWith('.js'))
  if (!adminChunk) failures.push('production AdminView chunk is missing')
  else if ((await stat(join(chunksDirectory, adminChunk))).size > 95 * 1024) failures.push('AdminView host is monolithic again')
  const applicationFiles = chunks.filter((name) => name.endsWith('.js')).map((name) => join(chunksDirectory, name))
  applicationFiles.push(resolve(themeRoot, 'assets/runtime/theme.js'), resolve(themeRoot, 'assets/runtime/vue-runtime.js'))
  const javascript = (await Promise.all(applicationFiles.map((path) => readFile(path, 'utf8')))).join('\n')
  if (javascript.includes('new Function') || javascript.includes('eval(')) failures.push('application JavaScript contains eval-like execution')
  for (const id of ['profile-settings', 'admin-panel']) {
    const source = await readFile(resolve(themeRoot, id === 'profile-settings' ? 'userOptions/ProfileSettings.tpl' : 'userOptions/AdminPanel.tpl'), 'utf8')
    const revision = Number(source.match(/\brevision="(\d+)"/u)?.[1] ?? 1)
    const module = await readFile(resolve(themeRoot, `assets/runtime/templates/${id}.${revision}.js`), 'utf8')
    for (const token of ['/assets/runtime/vue-runtime.js"', 'export function render(', `export const templateId = "${id}"`]) {
      if (!module.includes(token)) failures.push(`${id} CSP-safe render module is missing ${token}`)
    }
  }
  for (const token of ['admin-breadcrumbs__status', 'admin-feedback__details-reason', 'admin-workspace__content--dashboard']) {
    if (javascript.includes(token)) failures.push(`TPL HTML leaked into production JavaScript: ${token}`)
  }
} catch (error) {
  failures.push(`production runtime chunks are unavailable: ${error.message}`)
}

if (failures.length) {
  console.error('Runtime userOptions TPL contract failed:')
  for (const failure of [...new Set(failures)]) console.error(`- ${failure}`)
  process.exit(1)
}
console.log('Runtime userOptions TPL contract passed: TPL files remain runtime-editable source data, CSP-safe revision modules are derivative caches, and userOptions HTML is absent from application chunks.')
