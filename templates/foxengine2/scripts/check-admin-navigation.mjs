import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'
import { includesLocalized } from './i18n-test-utils.mjs'

const root = resolve(import.meta.dirname, '..', '..', '..')
const read = (path) => readFileSync(resolve(root, path), 'utf8')
function attributes(source) {
  const result = {}
  for (const match of source.matchAll(/([A-Za-z_:][A-Za-z0-9_.:-]*)\s*=\s*("[^"]*"|'[^']*')/gu)) result[match[1].toLowerCase()] = match[2].slice(1, -1)
  return result
}
const adminTpl = read('templates/foxengine2/userOptions/AdminPanel.tpl')
const runtimeTools = [...adminTpl.matchAll(/<fox-admin-tool\b([^>]*)\/>/gu)].map((match) => attributes(match[1]))
const runtimeCategories = [...adminTpl.matchAll(/<fox-admin-category\b([^>]*)\/>/gu)].map((match) => attributes(match[1]))
const panelBody = adminTpl.match(/<fox-template-body\b[^>]*>([\s\S]*?)<\/fox-template-body>/u)?.[1] ?? ''
const source = {
  state: read('engine/classes/modules/AdminPanel/client/useAdminPanel.ts'),
  controller: read('templates/foxengine2/src/userOptions/userOptions/AdminPanel.vue'),
  panel: panelBody,
  dashboard: read('templates/foxengine2/src/foxEngine/admin/Dashboard.vue'),
  category: read('templates/foxengine2/src/foxEngine/admin/Category.vue'),
  catalogs: read('templates/foxengine2/src/foxEngine/admin/Catalogs.vue'),
  rewards: read('templates/foxengine2/src/foxEngine/admin/Rewards.vue'),
  styles: read('templates/foxengine2/src/styles/admin-panel.css'),
  package: read('templates/foxengine2/package.json'),
}
const failures = []
const requireToken = (file, token, message) => { if (!includesLocalized(source[file], token)) failures.push(message) }
const forbidToken = (file, token, message) => { if (source[file].includes(token)) failures.push(message) }

for (const [file, token, message] of [
  ['state', "export type AdminSection = 'home' | AdminToolId", 'Admin destination type is incomplete.'],
  ['state', "const activeTab = ref<AdminView>('home')", 'Admin panel does not start from the card dashboard.'],
  ['state', 'const groupedTabs = computed', 'Semantic category grouping is missing.'],
  ['controller', "import { useRoute, useRouter } from 'vue-router'", 'Admin navigation is not synchronized with Vue Router.'],
  ['controller', 'route.query.group', 'Admin category query parameter is missing.'],
  ['controller', 'route.query.section', 'Admin tool query parameter is missing.'],
  ['controller', 'navigateCategory', 'Full category navigation is missing.'],
  ['controller', '<RuntimeTpl', 'AdminPanel.vue is not a runtime TPL host.'],
  ['panel', 'class="admin-breadcrumbs"', 'Navigation breadcrumb is missing from AdminPanel.tpl.'],
  ['panel', '<AdminDashboard', 'Admin dashboard is not mounted by AdminPanel.tpl.'],
  ['panel', '<AdminCategoryView', 'Full-width category view is not mounted by AdminPanel.tpl.'],
  ['panel', '<AdminRewards', 'Independent Rewards view is not mounted by AdminPanel.tpl.'],
  ['panel', ':name="catalogName"', 'Shared data editor is not bound to the direct route destination.'],
  ['dashboard', "@click=\"emit('selectCategory', category.id)\"", 'Dashboard category headers are not navigable.'],
  ['dashboard', 'class="admin-dashboard__groups"', 'Grouped dashboard card layout is missing.'],
  ['dashboard', 'class="admin-dashboard-card"', 'Dashboard tool cards are missing.'],
  ['category', 'class="admin-category-view"', 'Category-only workspace is missing.'],
  ['category', 'v-for="tool in category.tools"', 'Category workspace does not limit itself to the selected group.'],
  ['rewards', '<h2>Награды</h2>', 'Rewards destination has no dedicated screen.'],
  ['styles', '/* Admin category navigation v3 */', 'Category navigation styles are missing.'],
  ['styles', '.admin-category-view__grid', 'Category tool grid styles are missing.'],
  ['package', '"check:admin-navigation"', 'Admin navigation regression script is not registered.'],
]) requireToken(file, token, message)

for (const [file, token, message] of [
  ['panel', 'class="admin-sidebar"', 'Legacy permanent admin sidebar remains mounted.'],
  ['panel', 'class="admin-tabs"', 'Legacy flat tab navigation remains mounted.'],
  ['state', "{ id: 'catalogs',", 'Opaque generic Catalogs card remains in navigation.'],
  ['state', 'parentLabel', 'Legacy Catalogs parent remains in the navigation model.'],
  ['state', 'parentIcon', 'Legacy Catalogs parent icon remains in the navigation model.'],
  ['state', "id: 'catalog-", 'Legacy catalog-* route IDs remain.'],
  ['panel', 'currentTool?.parentLabel', 'Legacy Catalogs breadcrumb remains visible.'],
  ['dashboard', 'tool.parentLabel', 'Dashboard still renders a Catalogs parent.'],
  ['category', 'tool.parentLabel', 'Community category still renders a Catalogs parent.'],
  ['catalogs', 'v-model="name"', 'Shared editor still contains an internal destination selector.'],
  ['catalogs', "'update:name'", 'Shared editor can desynchronize route state through update:name.'],
]) forbidToken(file, token, message)

const expectedTools = ['overview', 'logs', 'users', 'achievements', 'infobox', 'badges', 'rewards', 'groups', 'content', 'slides', 'settings', 'runtime-options', 'servers', 'files', 'mail', 'maintenance']
for (const tool of expectedTools) if (runtimeTools.filter((entry) => entry.id === tool).length !== 1) failures.push(`Admin tool ${tool} must belong to exactly one semantic category and view.`)
const achievementsTool = runtimeTools.find((entry) => entry.id === 'achievements')
if (!achievementsTool || achievementsTool.component !== 'Achievements' || achievementsTool.tab !== 'achievements' || achievementsTool.category !== 'community') failures.push('Achievements admin tool must be a direct Community destination.')
for (const category of ['observability', 'community', 'content', 'infrastructure']) if (!runtimeCategories.some((entry) => entry.id === category)) failures.push(`Admin category ${category} is missing.`)
for (const tool of ['infobox', 'badges', 'groups', 'rewards']) {
  const definition = runtimeTools.find((entry) => entry.id === tool)
  if (!definition || definition.category !== 'community') failures.push(`${tool} is not a direct Community destination.`)
}
for (const [tool, catalog] of Object.entries({ infobox: 'infobox', badges: 'badges', groups: 'groups' })) {
  const definition = runtimeTools.find((entry) => entry.id === tool)
  if (!definition || definition.tab !== 'catalogs' || definition.catalog !== catalog) failures.push(`${tool} must directly map to the shared editor for ${catalog}.`)
}
const mailTool = runtimeTools.find((entry) => entry.id === 'mail')
if (!mailTool || mailTool.component !== 'Mail' || mailTool.tab !== 'mail' || mailTool.category !== 'infrastructure') failures.push('Mail admin tool must be a direct Infrastructure destination.')
const protectedRuntimeTool = runtimeTools.find((entry) => entry.id === 'runtime-options')
if (protectedRuntimeTool?.enabled !== 'true' || protectedRuntimeTool?.protected !== 'true') failures.push('Runtime options editor must remain a protected direct destination.')

if (failures.length) {
  console.error(`Admin navigation contract failed (${failures.length}):`)
  for (const failure of failures) console.error(`- ${failure}`)
  process.exit(1)
}
console.log('Admin navigation contract passed: AdminPanel.tpl owns the HTML and directly exposes Community destinations without a legacy Catalogs parent.')
