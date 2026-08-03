import { access, readFile, readdir } from 'node:fs/promises'
import { dirname, join, resolve } from 'node:path'
import { fileURLToPath } from 'node:url'

const themeRoot = resolve(dirname(fileURLToPath(import.meta.url)), '..')
const repositoryRoot = resolve(themeRoot, '..', '..')
const read = (path) => readFile(resolve(repositoryRoot, path), 'utf8')
const failures = []
const requireText = (source, token, message) => {
  if (!source.includes(token)) failures.push(message)
}
const forbidText = (source, token, message) => {
  if (source.includes(token)) failures.push(message)
}

const profileTemplate = await read('templates/foxengine2/src/userOptions/userOptions/ProfileSettings.vue')
const profileController = await read('engine/classes/modules/UserSettings/client/views/ProfileSettingsView.vue')
const adminTemplate = await read('templates/foxengine2/src/userOptions/userOptions/AdminPanel.vue')
const main = await read('templates/foxengine2/src/Main.vue')
const router = await read('engine/client/router/index.ts')
const bootstrap = await read('engine/client/domain/bootstrap.ts')
const registry = await read('engine/classes/frontend/FrontendRegistry.class.php')
const manifest = JSON.parse(await read('templates/foxengine2/frontend.json'))

for (const option of ['ProfileOption', 'AppearanceOption', 'SecurityOption']) {
  requireText(profileTemplate, `import('./profile/options/${option}.vue')`, `${option} must be loaded with dynamic import()`)
  forbidText(profileTemplate, `import ${option} from`, `${option} must not be a static import`)
  forbidText(profileTemplate, `<${option} v-show=`, `${option} must not remain mounted through v-show`)
}
requireText(profileTemplate, 'defineAsyncComponent', 'profile settings must use async Vue components')
requireText(profileTemplate, '<Suspense timeout="0">', 'profile settings must expose a runtime loading state')
requireText(profileTemplate, 'runtime-panel-skeleton', 'profile settings must render a runtime loading skeleton')
requireText(profileController, 'function selectTab(tab: SettingsTab)', 'profile settings tab selection must have a dedicated URL-aware handler')
requireText(profileController, 'query: { ...route.value.query, tab }', 'profile settings must persist the selected tab in the URL')

for (const component of ['Overview', 'SiteSettings', 'Slides', 'Content', 'Rewards', 'Maintenance', 'Users', 'Servers', 'FileManager', 'Logs', 'Catalogs']) {
  requireText(adminTemplate, `import('@theme/foxEngine/admin/${component}.vue')`, `admin ${component} must be loaded with dynamic import()`)
  forbidText(adminTemplate, `import Admin${component} from`, `admin ${component} must not be a static import`)
}
requireText(adminTemplate, 'adminToolLoaders', 'admin panel must have an explicit runtime loader registry')
requireText(adminTemplate, '<Suspense v-else timeout="0">', 'admin tools must expose a runtime loading state')
requireText(adminTemplate, 'preloadAdminTool(tool.id)', 'admin navigation must preload the selected tool before activation')

requireText(bootstrap, "export type FrontendLayout = 'standard' | 'wide' | 'workspace'", 'bootstrap must define supported runtime layouts')
requireText(registry, "['standard', 'wide', 'workspace']", 'server registry must validate runtime layouts')
requireText(registry, "'layout' => $layout", 'server registry must include layout in the runtime manifest')
requireText(router, "layout: definition.layout ?? 'standard'", 'router must transfer runtime layout to route meta')
requireText(main, 'const layoutMode = computed<FrontendLayout>', 'site shell must derive composition from route layout')
requireText(main, "const showSidebar = computed(() => layoutMode.value === 'standard')", 'sidebar visibility must be layout-driven')
requireText(main, '<RightBlock v-if="showSidebar" />', 'site shell must not hardcode sidebar visibility by route name')

const routeLayouts = new Map((manifest.routes ?? []).map((route) => [route.name, route.layout]))
for (const name of ['admin', 'profile-settings']) {
  if (routeLayouts.get(name) !== 'workspace') failures.push(`${name} must use workspace layout`)
}
for (const name of ['profile', 'news-list', 'news', 'players']) {
  if (routeLayouts.get(name) !== 'wide') failures.push(`${name} must use wide layout`)
}

const chunksDirectory = resolve(themeRoot, 'assets/runtime/chunks')
try {
  await access(chunksDirectory)
  const chunks = await readdir(chunksDirectory)
  for (const prefix of ['ProfileOption-', 'AppearanceOption-', 'SecurityOption-', 'Users-', 'Servers-', 'Content-', 'FileManager-']) {
    if (!chunks.some((name) => name.startsWith(prefix) && name.endsWith('.js'))) {
      failures.push(`production runtime chunk is missing: ${prefix}*.js`)
    }
  }
  const adminChunk = chunks.find((name) => name.startsWith('AdminView-') && name.endsWith('.js'))
  if (!adminChunk) failures.push('production AdminView chunk is missing')
  else {
    const stat = await import('node:fs/promises').then(({ stat }) => stat(join(chunksDirectory, adminChunk)))
    if (stat.size > 90 * 1024) failures.push(`AdminView shell is monolithic again: ${(stat.size / 1024).toFixed(2)} KiB`)
  }
} catch (error) {
  failures.push(`production runtime chunks are unavailable: ${error.message}`)
}

if (failures.length) {
  console.error('Runtime options contract failed:')
  for (const failure of failures) console.error(`- ${failure}`)
  process.exit(1)
}

console.log('Runtime options contract passed: route layouts drive composition and user/admin options load as independent runtime chunks.')
