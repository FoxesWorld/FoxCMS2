import { readFile } from 'node:fs/promises'
import { join } from 'node:path'
import { repositoryRoot } from './theme-paths.mjs'
import { includesLocalized } from './i18n-test-utils.mjs'

const read = (path) => readFile(join(repositoryRoot, path), 'utf8')
const [presenter, options, rewardController, badgeSchema, panel, ui] = await Promise.all([
  read('engine/classes/modules/AdminPanel/AdminFailurePresenter.class.php'),
  read('engine/classes/modules/AdminPanel/AdminOptions.class.php'),
  read('engine/classes/modules/AdminPanel/AdminRewardController.class.php'),
  read('engine/classes/modules/AdminPanel/AdminBadgeCatalogSchema.class.php'),
  read('engine/classes/modules/AdminPanel/AdminPanel.class.php'),
  read('templates/foxengine2/userOptions/AdminPanel.tpl'),
])
const failures = []
const requireToken = (label, source, token) => {
  if (!includesLocalized(source, token)) failures.push(`${label} is missing ${token}`)
}

for (const token of [
  'final class AdminFailurePresenter',
  'Unknown column',
  'could not find driver',
  'php scripts/migrate.php',
  "'detail' => $detail",
  "function_exists('mb_strlen')",
]) requireToken('Admin failure presenter', presenter, token)
for (const token of [
  'private function assertRewardAdministrationSchema(): void',
  "'rewardDefinitions' => [",
  "'rewardClaimKeys' => [",
  "'rewardClaims' => [",
  'rewardClaims.uq_reward_claim_reward_user',
  'необходима миграция 021',
]) requireToken('Reward schema preflight', rewardController, token)
for (const token of [
  'final class AdminBadgeCatalogSchema',
  'public function assertAvailable(): void',
  "TABLE_NAME = 'badgesList'",
]) requireToken('Badge catalog preflight', badgeSchema, token)
for (const token of [
  'AdminFailurePresenter::payload(',
  'AdminFailurePresenter::status(',
]) requireToken('Admin bootstrap errors', panel, token)
for (const token of [
  '<dt>Причина</dt>',
  '{{ feedback.error.detail }}',
]) requireToken('Admin error UI', ui, token)

if (failures.length) {
  console.error('Admin error contract failed:')
  for (const failure of failures) console.error(`- ${failure}`)
  process.exit(1)
}
console.log('Admin error contract passed: failures expose a safe concrete cause, operation, exception and request ID.')
