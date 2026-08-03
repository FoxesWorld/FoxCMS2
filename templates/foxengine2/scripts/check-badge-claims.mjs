import { readFile } from 'node:fs/promises'
import { fileURLToPath } from 'node:url'

const root = fileURLToPath(new URL('../../..', import.meta.url))
const failures = []

async function source(relativePath) {
  return readFile(`${root}/${relativePath}`, 'utf8')
}
function requireText(label, text, fragments) {
  for (const fragment of fragments) {
    if (!text.includes(fragment)) failures.push(`${label} is missing ${fragment}`)
  }
}
function rejectText(label, text, fragments) {
  for (const fragment of fragments) {
    if (text.includes(fragment)) failures.push(`${label} unexpectedly contains ${fragment}`)
  }
}

const service = await source('engine/classes/services/BadgeClaimService.class.php')
const userActions = await source('engine/classes/modules/UserSettings/UserActions.class.php')
const admin = await source('engine/classes/modules/AdminPanel/AdminOptions.class.php')
const adminState = await source('engine/classes/modules/AdminPanel/client/useAdminPanel.ts')
const adminUsers = await source('templates/foxengine2/src/foxEngine/admin/Users.vue')
const adminUserEditor = await source('templates/foxengine2/src/foxEngine/admin/users/UserEditor.vue')
const adminBadgeEditor = await source('templates/foxengine2/src/foxEngine/admin/users/UserBadgeEditor.vue')
const publicView = await source('engine/client/views/BadgesView.vue')
const publicTheme = await source('templates/foxengine2/src/userOptions/pages/badges/Badges.vue')
const staticView = await source('engine/client/views/StaticContentView.vue')
const staticTheme = await source('templates/foxengine2/src/userOptions/content/StaticContent.vue')
const migration = await source('database/migrations/010_badge_claim_keys.sql')
const revokeMigration = await source('database/migrations/014_rules_expert_claim_key.sql')
const safetyMigration = await source('database/migrations/016_revoke_public_badge_claim_key.sql')
const publicAccessMigration = await source('database/migrations/017_public_badge_claim_access.sql')
const badgeStorageMigration = await source('database/migrations/015_consolidate_user_badges.sql')
const schema = await source('database/schema-000.sql')
const frontend = [publicView, publicTheme, staticView, staticTheme, adminState, adminUsers, adminUserEditor, adminBadgeEditor].join('\n')

requireText('Badge claim service', service, [
  "private const TOKEN_PATTERN = '/^fcb_[A-Za-z0-9_-]{43}$/D'",
  'random_bytes(32)',
  "hash('sha256', $token)",
  'private function createKey(',
  'private function claimByHash(',
  'LIMIT 1 FOR UPDATE',
  "$usageMode === 'single' && $usesCount >= 1",
  'SET `usesCount` = `usesCount` + 1',
  "'source' => $assignmentSource",
])
requireText('Atomic administrative badge grant', service, [
  'public function grantToUser(',
  "$this->db->transactional(function () use ($badgeId, $targetUuid, $creator): array",
  "$this->createKey($badgeId, 'single', $creator)",
  "$this->claimByHash(",
  "'admin-claim-key'",
  'Пользователь уже имеет этот бейдж.',
])
rejectText('Badge claim persistence', service, [
  '`token` VARCHAR',
  '`plaintextToken`',
  'INSERT INTO `userBadges`',
])

requireText('Authenticated generic badge claim', userActions, [
  "'claimBadge' => $this->claimBadge()",
  'CsrfToken::requireValid($this->request->csrfToken())',
  "$claimCode = trim($this->request->string('claimCode'))",
  "$badgeName = trim($this->request->string('badgeName'))",
  '$service->claim($claimCode, $this->session->uuid())',
  '$service->claimPublic($badgeName, $this->session->uuid())',
])
requireText('Generic public badge key flow', service, [
  'public function claimPublic(string $badgeName, string $userUuid): array',
  'private function lockedPublicKeyByBadgeName(string $badgeName): array',
  "`key`.`accessMode` = 'public'",
  "$this->createKey((int)$publicKey['badgeId'], 'single', null)",
  "'public-claim-key'",
  "'badge.public_claim.completed'",
])
rejectText('Rules-specific badge backend', `${userActions}\n${service}`, [
  'claimRulesBadge',
  'RULES_BADGE_NAME',
  'rules-page-claim-key',
  'badge.rules_claim.completed',
])
requireText('Administrative badge grant endpoint', admin, [
  "'grantBadgeToUser' => 'grantBadgeToUser'",
  'private function grantBadgeToUser(): void',
  '$this->badgeClaims->grantToUser(',
  "'key' => $result['key']",
])
requireText('Direct badge mutation guard', admin, [
  "if (array_key_exists('badges', $payload))",
  'Прямое назначение бейджей запрещено.',
])
rejectText('Administrative user update whitelist', admin.match(/private const USER_FIELDS = \[(.*?)\];/s)?.[1] ?? '', [
  "'badges'",
])
requireText('Administrative grant client', `${adminState}\n${adminUsers}\n${adminUserEditor}\n${adminBadgeEditor}`, [
  'grantBadgeToSelectedUser',
  "admPanel: 'grantBadgeToUser'",
  "emit('grantBadge', $event)",
  "emit('grant', badgeId)",
  'Выдать разовым кодом',
])
rejectText('Administrative direct badge payload', adminState, [
  'badges: userDraft.badges',
])
rejectText('Administrative direct badge toggling', adminBadgeEditor, [
  'toggleUserBadgeAssignment',
  "emit('update:modelValue'",
])

for (const [label, sql] of [['Migration 010', migration], ['Fresh schema', schema]]) {
  requireText(label, sql, [
    '`badgeClaimKeys`',
    '`badgeKeyClaims`',
    '`tokenHash` CHAR(64)',
    '`usageMode` VARCHAR(16)',
    '`usesCount` BIGINT UNSIGNED',
    'UNIQUE KEY `uq_badge_claim_key_hash` (`tokenHash`)',
  ])
}
requireText('Canonical badge storage migration', badgeStorageMigration, [
  'UPDATE `users` AS `user`',
  'DROP TABLE IF EXISTS `userBadges`',
])

const leakedHash = '4d7b99804414da0ffa5f5e435ff04f2a43bf3669e78d7c61dceb1fc667cfa26c'
rejectText('Fresh schema public token seed', schema, [leakedHash])
requireText('Immutable legacy migration 014', revokeMigration, [
  'INSERT INTO `badgeClaimKeys`',
  leakedHash,
])
requireText('Migration 016 public-key revocation', safetyMigration, [
  'UPDATE `badgeClaimKeys`',
  '`enabled` = 0',
  leakedHash,
  'activeLegacyPublicBadgeKeys',
])
rejectText('Migration 016 public token creation', safetyMigration, [
  'INSERT INTO `badgeClaimKeys`',
  "'reusable'",
])
requireText('Migration 017 generic public badge access', publicAccessMigration, [
  'ADD COLUMN `accessMode`',
  "'public'",
  'Знаток правил',
  'e959a5af7ab0d7307f3881221b2e4fb96a236509df791073c8b7a5fddcb15e57',
])
requireText('Fresh schema generic public badge access', schema, [
  '`accessMode` VARCHAR(16)',
  "'public'",
  'e959a5af7ab0d7307f3881221b2e4fb96a236509df791073c8b7a5fddcb15e57',
])

requireText('Public generic claim UI', `${publicView}\n${publicTheme}`, [
  "user_doaction: 'claimBadge'",
  'claimCode: code',
  'Получить бейдж',
])
requireText('Rules page generic badge button', `${staticView}\n${staticTheme}`, [
  "user_doaction: 'claimBadge'",
  'badgeName,',
  'claimBadge(badgeName: string)',
  'to=".static-content-page--rules"',
  '@claim-badge="claimBadge"',
  "emit('claimBadge', rulesBadgeName)",
  'Получить бейдж',
  'Знаток правил',
])
rejectText('Rules page specialized badge action', `${staticView}\n${staticTheme}`, [
  'claimRulesBadge',
  "user_doaction: 'claimRulesBadge'",
  'tokenHash',
])
const exposedTokens = frontend.match(/fcb_[A-Za-z0-9_-]{43}/g) ?? []
if (exposedTokens.length > 0) {
  failures.push(`Frontend exposes ${exposedTokens.length} complete badge claim token(s)`)
}
if (frontend.includes('tokenHash')) {
  failures.push('Frontend exposes tokenHash')
}

if (failures.length) {
  console.error('Badge claim contract failed:')
  for (const failure of failures) console.error(`- ${failure}`)
  process.exit(1)
}

console.log('Badge claim contract passed: rules uses the shared claimBadge action, public eligibility is key-driven, and successful grants consume generated one-time hashes.')
