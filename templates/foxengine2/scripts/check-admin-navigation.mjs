import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'

const root = resolve(import.meta.dirname, '..', '..', '..')
const read = (path) => readFileSync(resolve(root, path), 'utf8')
const source = {
  state: read('engine/classes/modules/AdminPanel/client/useAdminPanel.ts'),
  panel: read('templates/foxengine2/src/userOptions/userOptions/AdminPanel.vue'),
  dashboard: read('templates/foxengine2/src/foxEngine/admin/Dashboard.vue'),
  category: read('templates/foxengine2/src/foxEngine/admin/Category.vue'),
  catalogs: read('templates/foxengine2/src/foxEngine/admin/Catalogs.vue'),
  rewards: read('templates/foxengine2/src/foxEngine/admin/Rewards.vue'),
  styles: read('templates/foxengine2/src/styles/admin-panel.css'),
  package: read('templates/foxengine2/package.json'),
}
const failures = []
const requireToken = (file, token, message) => {
  if (!source[file].includes(token)) failures.push(message)
}
const forbidToken = (file, token, message) => {
  if (source[file].includes(token)) failures.push(message)
}

for (const [file, token, message] of [
  ['state', "export type AdminSection = 'home' | AdminToolId", 'Admin destination type is incomplete.'],
  ['state', "const activeTab = ref<AdminView>('home')", 'Admin panel does not start from the card dashboard.'],
  ['state', 'const groupedTabs = computed', 'Semantic category grouping is missing.'],
  ['state', "{ id: 'infobox', tab: 'catalogs', category: 'community', catalog: 'infobox'", 'InfoBox is not a direct Community destination.'],
  ['state', "{ id: 'badges', tab: 'catalogs', category: 'community', catalog: 'badges'", 'Badges are not a direct Community destination.'],
  ['state', "{ id: 'groups', tab: 'catalogs', category: 'community', catalog: 'groups'", 'Groups are not a direct Community destination.'],
  ['state', "{ id: 'rewards', tab: 'rewards', category: 'community'", 'Rewards are not a direct Community destination.'],
  ['panel', "import { useRoute, useRouter } from 'vue-router'", 'Admin navigation is not synchronized with Vue Router.'],
  ['panel', 'route.query.group', 'Admin category query parameter is missing.'],
  ['panel', 'route.query.section', 'Admin tool query parameter is missing.'],
  ['panel', 'navigateCategory', 'Full category navigation is missing.'],
  ['panel', 'class="admin-breadcrumbs"', 'Navigation breadcrumb is missing.'],
  ['panel', '<AdminDashboard', 'Admin dashboard is not mounted.'],
  ['panel', '<AdminCategoryView', 'Full-width category view is not mounted.'],
  ['panel', '<AdminRewards', 'Independent Rewards view is not mounted.'],
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

const expectedTools = [
  'overview', 'logs', 'users', 'infobox', 'badges', 'rewards', 'groups',
  'content', 'slides', 'settings', 'servers', 'files', 'maintenance',
]
for (const tool of expectedTools) {
  const pattern = new RegExp(`\\{ id: '${tool}', tab: '([a-z]+)', category: '([a-z]+)'`, 'g')
  const matches = [...source.state.matchAll(pattern)]
  if (matches.length !== 1) failures.push(`Admin tool ${tool} must belong to exactly one semantic category and view.`)
}
for (const category of ['observability', 'community', 'content', 'infrastructure']) {
  if (!source.state.includes(`id: '${category}'`)) failures.push(`Admin category ${category} is missing.`)
}

const directMappings = {
  infobox: 'infobox',
  badges: 'badges',
  groups: 'groups',
}
for (const [tool, catalog] of Object.entries(directMappings)) {
  const definition = source.state.match(new RegExp(`\\{ id: '${tool}'[^\\n]+`))?.[0] ?? ''
  if (!definition.includes("tab: 'catalogs'") || !definition.includes(`catalog: '${catalog}'`)) {
    failures.push(`${tool} must directly map to the shared editor for ${catalog}.`)
  }
}

const suspicious = [
  String.fromCodePoint(0x0420, 0x0452),
  String.fromCodePoint(0x0420, 0x2018),
  String.fromCodePoint(0x0421, 0x0453),
  '\uFFFD',
]
for (const [name, text] of Object.entries(source)) {
  if (name === 'package' || name === 'styles') continue
  for (const marker of suspicious) {
    if (text.includes(marker)) failures.push(`${name} contains a UTF-8 mojibake marker.`)
  }
}

if (failures.length > 0) {
  console.error(`Admin navigation contract failed (${failures.length}):`)
  for (const failure of failures) console.error(`- ${failure}`)
  process.exit(1)
}
console.log('Admin navigation contract passed: Community exposes InfoBox, Badges, Rewards and Groups directly, with no legacy Catalogs parent.')
