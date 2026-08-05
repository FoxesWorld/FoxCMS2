import { readFile } from 'node:fs/promises'
import { join } from 'node:path'
import { repositoryRoot, themeRoot } from './theme-paths.mjs'
import { includesLocalized } from './i18n-test-utils.mjs'

const failures = []
const files = {
  editor: join(themeRoot, 'src', 'foxEngine', 'admin', 'users', 'UserEditor.vue'),
  badgeEditor: join(themeRoot, 'src', 'foxEngine', 'admin', 'users', 'UserBadgeEditor.vue'),
  users: join(themeRoot, 'src', 'foxEngine', 'admin', 'Users.vue'),
  panel: join(themeRoot, 'src', 'userOptions', 'userOptions', 'AdminPanel.vue'),
  table: join(themeRoot, 'src', 'foxEngine', 'admin', 'users', 'UserTable.vue'),
  client: join(repositoryRoot, 'engine', 'classes', 'modules', 'AdminPanel', 'client', 'useAdminPanel.ts'),
  backend: join(repositoryRoot, 'engine', 'classes', 'modules', 'AdminPanel', 'AdminOptions.class.php'),
  notifications: join(repositoryRoot, 'engine', 'classes', 'services', 'NotificationService.class.php'),
  badges: join(repositoryRoot, 'engine', 'client', 'domain', 'userBadges.ts'),
  styles: join(themeRoot, 'src', 'styles', 'admin-users.css'),
}
const [editor, badgeEditor, users, panel, table, client, backend, notifications, badges, styles] = await Promise.all(Object.values(files).map((path) => readFile(path, 'utf8')))
const requireText = (label, text, tokens) => { for (const token of tokens) if (!includesLocalized(text, token)) failures.push(`${label} is missing ${token}`) }
const rejectText = (label, text, tokens) => { for (const token of tokens) if (text.includes(token)) failures.push(`${label} must not contain ${token}`) }

requireText('User editor badge operations', editor, [
  "import UserBadgeEditor from './UserBadgeEditor.vue'",
  '<h3>Персональные бейджи</h3>',
  'Администратор может выдать или отозвать знак профиля напрямую.',
  '@grant="emit(\'grantBadge\', $event)"',
  '@revoke="emit(\'revokeBadge\', $event)"',
])
requireText('Badge management UI', badgeEditor, [
  'Административное управление бейджами',
  'Причина операции',
  "const tab = ref<'assigned' | 'available'>('assigned')",
  'Полученные <b>{{ assignedBadges.length }}</b>',
  'Доступные <b>{{ availableBadges.length }}</b>',
  "emit('grant', { badgeId: badge.id, reason: reasonValue.value })",
  "emit('revoke', { badgeName: badge.badgeName, reason: reasonValue.value })",
  'Валютные начисления и история наград останутся без изменений.',
  'Причина обязательна и попадёт в административный журнал и уведомление пользователя.',
  "assignment.source === 'admin'",
])
rejectText('Badge UI reward coupling', badgeEditor, ['currencyAmount', 'currencyCode', 'rewardAmount', 'rewardCurrency', 'issueRewardClaimKey'])
requireText('Badge action validation', badgeEditor, [
  'function validateReason()',
  'reasonField.value?.focus()',
  'Укажите причину операции: минимум 3 символа.',
  ':disabled="disabled"',
  ':disabled="disabled || badge.id <= 0"',
])
rejectText('Badge buttons blocked by empty reason', badgeEditor, [
  ':disabled="disabled || !canAct"',
  ':disabled="disabled || !canAct || badge.id <= 0"',
])
requireText('Badge operation wiring', `${users}\n${panel}`, [
  'grantBadge: [badgeId: number, reason: string]',
  'revokeBadge: [badgeName: string, reason: string]',
  '@grant-badge="grantUserBadge"',
  '@revoke-badge="revokeUserBadge"',
])
requireText('Administrative badge backend', backend, [
  "'grantUserBadge' => 'grantUserBadge'",
  "'revokeUserBadge' => 'revokeUserBadge'",
  'private function mutateUserBadge(bool $grant): void',
  "'SELECT `uuid`, `login`, `badges` FROM `users` WHERE `uuid` = :uuid LIMIT 1 FOR UPDATE'",
  "'UPDATE `users` SET `badges` = :badges WHERE `uuid` = :uuid'",
  "'source' => 'admin'",
  "'admin.user_badge.' . $operation",
  "'reason' => $reason",
  'notifyBadgeRevoked(',
  "'rewardClaimChanged' => false",
  "'balanceChanged' => false",
])
const mutationBackend = backend.slice(backend.indexOf('private function grantUserBadge'), backend.indexOf('private function servers'))
rejectText('Administrative badge mutation side effects', mutationBackend, ['RewardClaimService', 'rewardClaims', 'BalanceMatrix::increment', '`balance` =', 'currencyAmount'])
requireText('Badge revocation notification', notifications, [
  'public function notifyBadgeRevoked(',
  "'achievement.badge_revoked'",
  "'warning'",
  "'Бейдж отозван'",
  "Причина: ' . $reason",
  "'reason' => $reason",
])
requireText('Administrative badge client', client, [
  'async function grantUserBadge(badgeId: number, reason: string)',
  "admPanel: 'grantUserBadge'",
  'async function revokeUserBadge(badgeName: string, reason: string)',
  "admPanel: 'revokeUserBadge'",
  'applySelectedUserBadges(userUuid, response.badges)',
])
if (client.includes('badges: userDraft.badges')) failures.push('General user save directly submits badges')
requireText('Badge assignment metadata', badges, ['acquiredAt?: number', 'source?: string', 'record.acquiredAt', 'record.source'])
requireText('User search UI', table, ['userBadgeAssignments(user.badges).length', 'admin-user-search__status'])
requireText('Badge management styles', styles, ['/* Administrative badge assignment */', '.admin-badge-editor__reason', '.admin-badge-card__operation--revoke'])

if (failures.length) {
  console.error('Admin users badge management contract failed:')
  for (const failure of failures) console.error(`- ${failure}`)
  process.exit(1)
}
console.log('Admin users badge management passed: administrators can grant and revoke profile badges with a reason, audit logging and no reward or balance side effects.')
