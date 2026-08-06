import { readFile } from 'node:fs/promises'
import { join } from 'node:path'
import { repositoryRoot } from './theme-paths.mjs'
import { includesLocalized } from './i18n-test-utils.mjs'

const failures = []
const read = (path) => readFile(join(repositoryRoot, path), 'utf8')
const requireText = (label, text, tokens) => {
  for (const token of tokens) if (!includesLocalized(text, token)) failures.push(`${label} is missing ${token}`)
}
const rejectText = (label, text, tokens) => {
  for (const token of tokens) if (text.includes(token)) failures.push(`${label} must not contain ${token}`)
}

const [service, admin, adminRewards, adminCatalog, adminUsers, userActions, userRewardController, runtimeOptions, runtimeOptionsSource, state, rewardsUi, catalogs, contentUi, panel,
  homeView, welcome, staticView, staticContent, offerDomain, offerComposable, migration, schema] = await Promise.all([
  read('engine/classes/services/RewardClaimService.class.php'),
  read('engine/classes/modules/AdminPanel/AdminOptions.class.php'),
  read('engine/classes/modules/AdminPanel/AdminRewardController.class.php'),
  read('engine/classes/modules/AdminPanel/AdminCatalogController.class.php'),
  read('engine/classes/modules/AdminPanel/AdminUserController.class.php'),
  read('engine/classes/modules/UserSettings/UserActions.class.php'),
  read('engine/src/FoxCMS/Engine/User/UserRewardController.php'),
  read('engine/client/runtime/userOptions.ts'),
  read('templates/foxengine2/userOptions/AdminPanel.tpl'),
  read('engine/classes/modules/AdminPanel/client/useAdminPanel.ts'),
  read('templates/foxengine2/src/foxEngine/admin/Rewards.vue'),
  read('templates/foxengine2/src/foxEngine/admin/Catalogs.vue'),
  read('templates/foxengine2/src/foxEngine/admin/Content.vue'),
  read('templates/foxengine2/userOptions/AdminPanel.tpl'),
  read('engine/client/views/HomeView.vue'),
  read('templates/foxengine2/src/userOptions/content/Welcome.vue'),
  read('engine/client/views/StaticContentView.vue'),
  read('templates/foxengine2/src/userOptions/content/StaticContent.vue'),
  read('engine/client/domain/publicRewardOffers.ts'),
  read('engine/client/rewards/usePublicRewardOffer.ts'),
  read('database/migrations/021_reward_definitions.sql'),
  read('database/schema-000.sql'),
])

requireText('Reward service cryptography', service, [
  "private const TOKEN_PREFIX = 'fcr_'",
  "private const TOKEN_PATTERN = '/^(?:fcr|fcb)_[A-Za-z0-9_-]{43}$/D'",
  'random_bytes(32)',
  "hash('sha256', $token)",
  'private function claimByHash(',
  'LIMIT 1 FOR UPDATE',
  "'UPDATE `rewardClaimKeys` SET `usesCount` = `usesCount` + 1",
])
requireText('Independent reward composition', service, [
  'public function saveDefinition(',
  "$badgeId === 0 && $amount === 0",
  'Награда должна содержать бейдж, валюту или оба компонента.',
  'BalanceMatrix::currencyDefinition($currencyCode)',
  'private function badgeResponse(',
  'private function currencyResponse(',
])
requireText('Atomic reward redemption', service, [
  "'SELECT `id`, `claimedAt` FROM `rewardClaims`",
  "'INSERT INTO `rewardClaims`",
  "'UPDATE `users` SET `badges` = :badges, `balance` = :balance WHERE `uuid` = :uuid'",
  'BalanceMatrix::increment(',
  "'badgeApplied' => $badgeApplied",
  "'currencyApplied' => $currencyApplied",
  "'alreadyClaimed' => false",
  "'reward.claim.completed'",
  'private function logClaimResult(',
])
requireText('Reward mutation key invalidation', service, [
  '$payloadChanged || $disabledNow',
  '`publicPlacement` = NULL',
  "WHERE `rewardId` = :rewardId AND `enabled` = 1",
  "':badgeId' => $badge !== null ? (int)$badge['id'] : null",
  "':currencyAmount' => $currency !== null ? (int)$currency['amount'] : 0",
])
rejectText('Reward definition immutability after claims', service.slice(
  service.indexOf('public function saveDefinition('),
  service.indexOf('public function deleteDefinition('),
), [
  'SELECT `id` FROM `rewardClaims` WHERE `rewardId` = :rewardId LIMIT 1 FOR UPDATE',
])
requireText('Reward claim idempotency', `${service}\n${schema}`, [
  'WHERE `rewardId` = :rewardId AND `userUuid` = :userUuid',
  'UNIQUE KEY `uq_reward_claim_reward_user` (`rewardId`, `userUuid`)',
  '`badgeId` BIGINT UNSIGNED NULL',
  '`badgeName` VARCHAR(120) NULL',
  'ON DELETE RESTRICT',
])
rejectText('Direct reward grant service', service, [
  'grantToUser', 'admin_grant', 'badge.admin_grant', 'currency.admin_grant',
])

const badgeTable = schema.slice(
  schema.indexOf('CREATE TABLE `badgesList`'),
  schema.indexOf('CREATE TABLE `rewardDefinitions`'),
)
requireText('Pure badge catalog schema', badgeTable, [
  '`badgeName` VARCHAR', '`description` VARCHAR', '`img` VARCHAR',
])
rejectText('Badge catalog reward coupling', badgeTable, [
  'rewardCurrency', 'rewardAmount', 'currencyCode', 'currencyAmount', 'tokenHash',
])
requireText('Reward definition schema', schema, [
  'CREATE TABLE `rewardDefinitions`',
  '`badgeId` BIGINT UNSIGNED NULL',
  '`currencyCode` VARCHAR(16)',
  '`currencyAmount` BIGINT UNSIGNED NOT NULL DEFAULT 0',
  'CHECK (`badgeId` IS NOT NULL OR (`currencyCode` IS NOT NULL AND `currencyAmount` > 0))',
])
requireText('Reward key schema', schema, [
  'CREATE TABLE `rewardClaimKeys`',
  '`rewardId` BIGINT UNSIGNED NOT NULL',
  'FOREIGN KEY (`rewardId`) REFERENCES `rewardDefinitions` (`id`)',
  'UNIQUE KEY `uq_reward_claim_public_placement` (`publicPlacement`)',
])
rejectText('Reward keys bound directly to badges', schema.slice(
  schema.indexOf('CREATE TABLE `rewardClaimKeys`'),
  schema.indexOf('CREATE TABLE `rewardClaims`'),
), ['`badgeId`'])
requireText('Reward migration', migration, [
  "TABLE_NAME = 'badgesList'",
  "COLUMN_NAME = 'id'",
  'SELECT `COLUMN_TYPE`',
  '@badge_id_type',
  "`reward`.`rewardName` COLLATE utf8mb4_unicode_ci",
  "CONVERT(CONCAT('Выдача: ', `badge`.`badgeName`) USING utf8mb4) COLLATE utf8mb4_unicode_ci",
  'ALTER TABLE `badgesList` ENGINE=InnoDB',
  '@create_reward_definitions',
  'CREATE TABLE IF NOT EXISTS `rewardDefinitions`',
  'CREATE TABLE IF NOT EXISTS `rewardClaimKeys`',
  'CREATE TABLE IF NOT EXISTS `rewardClaims`',
  'DROP TABLE IF EXISTS `badgeKeyClaims`',
  'DROP TABLE IF EXISTS `badgeClaimKeys`',
  'ALTER TABLE `badgesList` DROP COLUMN `rewardCurrency`',
  'ALTER TABLE `badgesList` DROP COLUMN `rewardAmount`',
])

requireText('Reward administration endpoints', `${admin}\n${adminRewards}`, [
  "'rewards' => 'rewards'",
  "'saveReward' => 'saveReward'",
  "'deleteReward' => 'deleteReward'",
  "'issueRewardClaimKey' => 'issueRewardClaimKey'",
  "'revokeRewardClaimKey' => 'revokeRewardClaimKey'",
  '$this->rewardClaims->saveDefinition(',
  '$this->rewardClaims->issue(',
  '$this->rewardClaims->revoke(',
])
requireText('Badge administration is catalog-only', `${admin}\n${adminCatalog}`, [
  "'fields' => ['badgeName', 'description', 'img']",
  'SELECT `id`, `badgeName`, `description`, `img` FROM `badgesList`',
  'SELECT COUNT(*) FROM `rewardDefinitions` WHERE `badgeId` = :badgeId',
])
rejectText('Badge administration reward fields', admin, [
  '$rewardCurrency', '$rewardAmount', '`rewardCurrency` =', '`rewardAmount` =',
])
rejectText('Direct admin reward grant', admin, [
  "'grantBadgeToUser'", "'grantRewardToUser'", 'private function grantReward',
])
const adminBadgeMutation = adminUsers
requireText('Independent administrative badge operations', adminBadgeMutation, [
  "'UPDATE `users` SET `badges` = :badges WHERE `uuid` = :uuid'",
  "'rewardClaimChanged' => false",
  "'balanceChanged' => false",
])
rejectText('Administrative badge operation reward coupling', adminBadgeMutation, [
  'rewardClaimKeys', 'BalanceMatrix::increment', '`balance` =', 'currencyAmount',
])

requireText('Separate reward screen', `${runtimeOptions}\n${state}\n${rewardsUi}\n${panel}`, [
  "['rewards', { component: 'Rewards', tab: 'rewards' }]",
  'export interface RewardDefinitionRow',
  'export interface RewardDraft',
  'async function loadRewards()',
  "admPanel: 'saveReward'",
  "admPanel: 'issueRewardClaimKey'",
  '<AdminRewards',
  '<h2>Награды</h2>',
  'Награда — отдельная конфигурация выдачи.',
  '<option :value="0">Без бейджа</option>',
  '<option value="">Без валюты</option>',
  'Выберите хотя бы один компонент: бейдж или положительное количество валюты.',
  'Эту награду уже получили {0} раз.',
  'const hasExistingClaims = computed(',
  '<label v-if="draft.currencyCode">',
])
const rewardToolMatch = runtimeOptionsSource.match(/<fox-admin-tool\b([^>]*\bid="rewards"[^>]*)\/>/u)
const rewardAttributes = {}
for (const match of (rewardToolMatch?.[1] ?? '').matchAll(/([A-Za-z_:][A-Za-z0-9_.:-]*)\s*=\s*("[^"]*"|'[^']*')/gu)) {
  rewardAttributes[match[1].toLowerCase()] = match[2].slice(1, -1)
}
if (rewardAttributes.component !== 'Rewards'
    || rewardAttributes.tab !== 'rewards'
    || rewardAttributes.category !== 'community'
    || rewardAttributes.enabled !== 'true') {
  failures.push('AdminPanel.tpl must expose the enabled rewards tool in the community category')
}

rejectText('Locked reward composition editor', rewardsUi, [
  'payloadLocked', ':disabled="payloadLocked"',
])
rejectText('Badge catalog reward editor', catalogs, [
  'admin-badge-reward-editor', 'rewardCurrency', 'rewardAmount', 'currencyAmount',
])
rejectText('Content editor claim controls', contentUi, [
  'issueClaimKey', 'claimKeys:', 'issuedCode:', 'Коды получения', 'admin-claim-issuer',
])

requireText('Authenticated reward facade routes', userActions, [
  "'getRewardOffer' => $this->rewards->getRewardOffer()",
  "'getBadgeOffer' => $this->rewards->getRewardOffer()",
  "'claimReward' => $this->rewards->claimReward()",
  "'claimBadge' => $this->rewards->claimReward()",
])
requireText('Authenticated reward use-cases', userRewardController, [
  'new RewardClaimService(',
  '$service->claim($claimCode, $userUuid)',
  '$service->claimPublicOffer($offerPlacement, $userUuid)',
  "'alreadyClaimed' => $alreadyClaimed",
  "'badgeApplied' => ($result['badgeApplied'] ?? false) === true",
  "'currencyApplied' => ($result['currencyApplied'] ?? false) === true",
  "'offer' => $result['offer'] ?? null",
  '$this->guard->requireRewardAccess()',
])
rejectText('Name-only reward claim', `${userActions}
${userRewardController}`, [
  "$this->request->string('badgeName')", "$this->request->string('rewardName')",
])

requireText('Generic public reward client', `${homeView}\n${welcome}\n${staticView}\n${staticContent}\n${offerDomain}\n${offerComposable}`, [
  "usePublicRewardOffer('welcome-native', !isGuest)",
  "usePublicRewardOffer('rules', rulesOfferEnabled)",
  "user_doaction: 'getRewardOffer'",
  "user_doaction: 'claimReward'",
  'badge: PublicRewardBadge | null',
  'currency: PublicRewardCurrency | null',
  'rewardOffer.reward.currency.amount',
  'rewardOffer.reward.currency.currencyName',
])
rejectText('Public reward client assumes a required badge', offerDomain, [
  'if (!source || !badge) return null',
  'badge: PublicRewardBadge\n',
])

const frontend = `${state}\n${rewardsUi}\n${panel}\n${homeView}\n${welcome}\n${staticView}\n${staticContent}\n${offerDomain}\n${offerComposable}`
if ((frontend.match(/(?:fcr|fcb)_[A-Za-z0-9_-]{43}/g) ?? []).length) failures.push('Frontend exposes a complete reward claim token')
if (frontend.includes('tokenHash')) failures.push('Frontend exposes tokenHash')

if (failures.length) {
  console.error('Reward contract failed:')
  for (const failure of failures) console.error(`- ${failure}`)
  process.exit(1)
}
console.log('Reward contract passed: rewards independently compose badge and/or currency and require cryptographic redemption; direct administrative badge management remains a separate profile-only operation.')
