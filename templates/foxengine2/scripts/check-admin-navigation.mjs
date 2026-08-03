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
  ['state', "export type AdminSection = 'home' | AdminToolId", 'Admin destination type does not include virtual catalog tools.'],
  ['state', "const activeTab = ref<AdminView>('home')", 'Admin panel does not start from the card dashboard.'],
  ['state', 'const groupedTabs = computed', 'Semantic category grouping is missing.'],
  ['state', "id: 'catalog-infobox'", 'InfoBox catalog destination is missing.'],
  ['state', "id: 'catalog-badges'", 'Badges catalog destination is missing.'],
  ['state', "id: 'catalog-groups'", 'Groups catalog destination is missing.'],
  ['state', "tab: 'catalogs'", 'Catalog destinations are not mapped to the shared catalog editor.'],
  ['panel', "import { useRoute, useRouter } from 'vue-router'", 'Admin navigation is not synchronized with Vue Router.'],
  ['panel', 'route.query.group', 'Admin category query parameter is missing.'],
  ['panel', 'route.query.section', 'Admin tool query parameter is missing.'],
  ['panel', 'navigateCategory', 'Full category navigation is missing.'],
  ['panel', 'class="admin-breadcrumbs"', 'Navigation depth breadcrumb is missing.'],
  ['panel', 'currentTool?.parentLabel', 'Catalog parent level is missing from breadcrumbs.'],
  ['panel', '<AdminDashboard', 'Admin dashboard is not mounted.'],
  ['panel', '<AdminCategoryView', 'Full-width category view is not mounted.'],
  ['panel', ':name="catalogName"', 'Catalog editor is not bound to the route-owned catalog name.'],
  ['dashboard', "@click=\"emit('selectCategory', category.id)\"", 'Dashboard category headers are not navigable.'],
  ['dashboard', 'class="admin-dashboard__groups"', 'Grouped dashboard card layout is missing.'],
  ['dashboard', 'class="admin-dashboard-card"', 'Dashboard tool cards are missing.'],
  ['category', 'class="admin-category-view"', 'Category-only workspace is missing.'],
  ['category', 'v-for="tool in category.tools"', 'Category workspace does not limit itself to the selected group.'],
  ['category', 'class="admin-category-tool__parent"', 'Catalog options do not expose their parent level.'],
  ['styles', '/* Admin category navigation v3 */', 'Category navigation styles are missing.'],
  ['styles', '.admin-category-view__grid', 'Category tool grid styles are missing.'],
  ['styles', '.admin-dashboard-group__header:hover', 'Interactive group-header feedback is missing.'],
  ['package', '"check:admin-navigation"', 'Admin navigation regression script is not registered.'],
]) requireToken(file, token, message)

forbidToken('panel', 'class="admin-sidebar"', 'Legacy permanent admin sidebar remains mounted.')
forbidToken('panel', 'class="admin-tabs"', 'Legacy flat tab navigation remains mounted.')
forbidToken('state', "{ id: 'catalogs',", 'Opaque generic Catalogs card remains in navigation.')
forbidToken('catalogs', '<select', 'Catalog editor still contains an internal catalog selector.')
forbidToken('catalogs', "'update:name'", 'Catalog editor can still desynchronize route state through update:name.')
forbidToken('panel', 'v-model:name="catalogName"', 'Catalog selection can still be changed outside route navigation.')

const expectedTools = [
  'overview', 'logs', 'users',
  'catalog-infobox', 'catalog-badges', 'catalog-groups',
  'content', 'slides', 'settings', 'servers', 'files', 'maintenance',
]
for (const tool of expectedTools) {
  const pattern = new RegExp(`\{ id: '${tool}', tab: '([a-z]+)', category: '([a-z]+)'`, 'g')
  const matches = [...source.state.matchAll(pattern)]
  if (matches.length !== 1) failures.push(`Admin tool ${tool} must belong to exactly one semantic category and view.`)
}
for (const category of ['observability', 'community', 'content', 'infrastructure']) {
  if (!source.state.includes(`id: '${category}'`)) failures.push(`Admin category ${category} is missing.`)
}

const catalogMappings = {
  'catalog-infobox': 'infobox',
  'catalog-badges': 'badges',
  'catalog-groups': 'groups',
}
for (const [tool, catalog] of Object.entries(catalogMappings)) {
  const definition = source.state.match(new RegExp(`\{ id: '${tool}'[^\n]+`))?.[0] ?? ''
  if (!definition.includes("tab: 'catalogs'") || !definition.includes(`catalog: '${catalog}'`)) {
    failures.push(`${tool} must map to catalogs/${catalog}.`)
  }
}

const suspicious = [
  String.fromCodePoint(0x0420, 0x0452),
  String.fromCodePoint(0x0420, 0x2018),
  String.fromCodePoint(0x0421, 0x0453),
  '\uFFFD',
]
for (const [name, text] of Object.entries({
  panel: source.panel,
  dashboard: source.dashboard,
  category: source.category,
  catalogs: source.catalogs,
})) {
  for (const marker of suspicious) {
    if (text.includes(marker)) failures.push(`${name} contains a UTF-8 mojibake marker.`)
  }
}

if (failures.length > 0) {
  console.error(`Admin navigation contract failed (${failures.length}):`)
  for (const failure of failures) console.error(`- ${failure}`)
  process.exit(1)
}
console.log('Admin navigation contract passed: root, category, catalog-parent and concrete-tool levels are route-safe and independently navigable.')
