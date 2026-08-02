import { readFile } from 'node:fs/promises'
import { join } from 'node:path'
import { repositoryRoot, themeRoot } from './theme-paths.mjs'

const failures = []
const files = {
  editor: join(themeRoot, 'src', 'foxEngine', 'admin', 'users', 'UserEditor.vue'),
  badgeEditor: join(themeRoot, 'src', 'foxEngine', 'admin', 'users', 'UserBadgeEditor.vue'),
  table: join(themeRoot, 'src', 'foxEngine', 'admin', 'users', 'UserTable.vue'),
  client: join(repositoryRoot, 'engine', 'classes', 'modules', 'AdminPanel', 'client', 'useAdminPanel.ts'),
  backend: join(repositoryRoot, 'engine', 'classes', 'modules', 'AdminPanel', 'AdminOptions.class.php'),
  badges: join(repositoryRoot, 'engine', 'client', 'domain', 'userBadges.ts'),
  styles: join(themeRoot, 'src', 'styles', 'admin-users.css'),
}

const [editor, badgeEditor, table, client, backend, badges, styles] = await Promise.all(
  Object.values(files).map((path) => readFile(path, 'utf8')),
)

for (const token of [
  "import UserBadgeEditor from './UserBadgeEditor.vue'",
  '<h3>Персональные бейджи</h3>',
  ':model-value="draft.badges"',
  ':options="badgeOptions"',
  '@update:model-value="draft.badges = $event"',
]) {
  if (!editor.includes(token)) failures.push(`UserEditor visual badge section is missing ${token}`)
}
if (/JsonFormEditor[\s\S]{0,300}:model-value="draft\.badges"/.test(editor)) {
  failures.push('UserEditor still renders user badges through JsonFormEditor')
}

for (const token of [
  'admin-badge-editor__tabs',
  'Бейджи пользователя',
  'Все бейджи',
  ':class="{ active: !showAll }"',
  ':class="{ active: showAll }"',
  'showAll.value || badge.selected',
  'admin-badge-editor__search',
  'admin-badge-grid',
  ':aria-pressed="badge.selected"',
  'toggleUserBadgeAssignment',
  'userBadgeAssignments',
  'badge.description',
  'badge.image',
  "badge.selected ? 'Снять' : 'Назначить'",
]) {
  if (!badgeEditor.includes(token)) failures.push(`Visual badge editor is missing ${token}`)
}

if (!badgeEditor.includes('<img v-if="imageUrl(badge.image)"')
    || !badgeEditor.includes('<span v-else>{{ initial(badge.title) }}</span>')) {
  failures.push('Badge initial must render only when no image is available')
}
if (/<span>\{\{ initial\(badge\.title\) \}\}<\/span>\s*<img v-if=/.test(badgeEditor)) {
  failures.push('Badge initial is still rendered underneath an existing image')
}

for (const token of [
  'export function userBadgeAssignments',
  'export function toggleUserBadgeAssignment',
  'record.badgeName ?? record.name ?? record.title ?? record.id',
  'Object.keys(record)',
  'seen.has(key)',
]) {
  if (!badges.includes(token)) failures.push(`Legacy badge display normalizer is missing ${token}`)
}

for (const token of [
  'window.setTimeout(() => emit(\'search\'), 320)',
  'userBadgeAssignments(user.badges).length',
  'Логин, email, имя, UUID или бейдж',
  'admin-user-search__status',
]) {
  if (!table.includes(token)) failures.push(`User search display is missing ${token}`)
}
for (const token of [
  "CONCAT_WS(' ',",
  "$searchSql = $this->db->safesql('%' . $search . '%')",
  '$statement = $this->db->query($sql)',
  'users.directory query failed:',
  'users.groups query failed:',
  'users.badges query failed:',
  "'backendVersion' => 'users-directory-v4-direct-query'",
  'private function userBadgeReadSource(): array',
  "LEFT JOIN `userBadges` AS `legacyBadges`",
  "NULLIF(NULLIF(TRIM(`user`.`badges`), ''), '[]')",
  "$row['badges'] = $this->decodeAdminJsonField",
  'private function syncLegacyUserBadges(',
  "SELECT `badgeName`, `description`, `img` FROM `badgesList`",
  "'badgeName' => $badgeName",
  "'description' => trim((string)($row['description'] ?? ''))",
  "'image' => $image !== '' ? $image : null",
]) {
  if (!backend.includes(token)) failures.push(`Admin users backend contract is missing ${token}`)
}
const usersMethod = backend.slice(backend.indexOf('private function users(): void'), backend.indexOf('private function updateUser(): void'))
for (const forbidden of ['->prepare(', '->execute(', ':search', ' LIKE ?']) {
  if (usersMethod.includes(forbidden)) failures.push(`users() read path must not contain ${forbidden}`)
}
if (!usersMethod.includes('->query($sql)')) failures.push('users() must execute its directory SQL through query()')
if (!usersMethod.includes("'badgeOptions' => $badgeOptions")) failures.push('users() must return badgeOptions')

for (const token of [
  'export interface AdminBadgeOption',
  'badgeOptions: AdminBadgeOption[]',
  'badgeOptions.value = response.badgeOptions',
  'async function searchUsers()',
  'await loadUsers({ autoSelect: false })',
  'if (options.autoSelect === false) return',
]) {
  if (!client.includes(token)) failures.push(`Admin users client display flow is missing ${token}`)
}

for (const token of [
  '.admin-badge-editor{',
  '.admin-badge-editor__tabs{',
  '.admin-badge-editor__tabs .active{',
  '.admin-badge-card.is-assigned',
  '.admin-badge-card.is-legacy',
]) {
  if (!styles.includes(token)) failures.push(`Admin users visual styling is missing ${token}`)
}

if (failures.length) {
  console.error('Admin users display contract failed:')
  for (const failure of failures) console.error(`- ${failure}`)
  process.exit(1)
}

console.log('Admin users display contract passed: live search, stable editor and visual badge catalog are active.')
