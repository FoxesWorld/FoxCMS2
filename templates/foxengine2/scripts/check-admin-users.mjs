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
  '@grant="emit(\'grantBadge\', $event)"',
]) {
  if (!editor.includes(token)) failures.push(`UserEditor visual badge section is missing ${token}`)
}
if (/JsonFormEditor[\s\S]{0,300}:model-value="draft\.badges"/.test(editor)) {
  failures.push('UserEditor still renders user badges through JsonFormEditor')
}

for (const token of [
  'admin-badge-editor__tabs',
  'Полученные',
  'Каталог',
  ':class="{ active: !showAll }"',
  ':class="{ active: showAll }"',
  'showAll.value || badge.selected',
  'admin-badge-editor__search',
  'admin-badge-grid',
  ':aria-pressed="badge.selected"',
  "emit('grant', badgeId)",
  'userBadgeAssignments',
  'badge.description',
  'badge.image',
  'Выдать разовым кодом',
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
  if (!badges.includes(token)) failures.push(`Badge display normalizer is missing ${token}`)
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
  "$badgeExpression = '`user`.`badges`';",
  "$row['badges'] = $this->decodeAdminJsonField",
  "SELECT `id`, `badgeName`, `description`, `img` FROM `badgesList`",
  "'badgeName' => $badgeName",
  "'description' => trim((string)($row['description'] ?? ''))",
  "'image' => $image !== '' ? $image : null",
]) {
  if (!backend.includes(token)) failures.push(`Admin users backend contract is missing ${token}`)
}
for (const legacyIdentity of [
  'legacyBadges',
  'syncLegacyUserBadges',
  'userBadgeReadSource',
  'syncUserBadgesProjection',
  'JOIN `userBadges`',
  '`userLogin`',
  '`userMd5`',
]) {
  if (backend.includes(legacyIdentity)) failures.push(`Admin users backend still contains legacy identity contract ${legacyIdentity}`)
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
  'badgeOptions.value = response.badgeOptions.map',
  'async function searchUsers()',
  'await loadUsers({ autoSelect: false })',
  'if (options.autoSelect === false) return',
]) {
  if (!client.includes(token)) failures.push(`Admin users client display flow is missing ${token}`)
}

for (const token of [
  'id: number',
  'async function grantBadgeToSelectedUser',
  "admPanel: 'grantBadgeToUser'",
  'badgeClaimKeys.value = [',
]) {
  if (!client.includes(token)) failures.push(`Admin badge grant client is missing ${token}`)
}
for (const token of [
  "'grantBadgeToUser' => 'grantBadgeToUser'",
  'private function grantBadgeToUser(): void',
  '$this->badgeClaims->grantToUser(',
  "if (array_key_exists('badges', $payload))",
]) {
  if (!backend.includes(token)) failures.push(`Admin badge grant backend is missing ${token}`)
}
if (client.includes('badges: userDraft.badges')) failures.push('Admin user save still directly submits badges')
if (badgeEditor.includes('toggleUserBadgeAssignment')) failures.push('Visual badge editor still directly toggles badge assignments')

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

console.log('Admin users display contract passed: badge grants use one-time claim keys and direct badge mutation is blocked.')
