import { readFile } from 'node:fs/promises'
import { join } from 'node:path'
import { repositoryRoot, themeRoot } from './theme-paths.mjs'

const failures = []
const files = {
  php: join(repositoryRoot, 'engine', 'classes', 'domain', 'BalanceMatrix.class.php'),
  renderer: join(repositoryRoot, 'engine', 'classes', 'themes', 'ThemeRenderer.class.php'),
  auth: join(repositoryRoot, 'engine', 'classes', 'modules', 'AuthReg', 'AuthReg.class.php'),
  profileApi: join(repositoryRoot, 'engine', 'classes', 'modules', 'UserSettings', 'UserActions.class.php'),
  adminApi: join(repositoryRoot, 'engine', 'classes', 'modules', 'AdminPanel', 'AdminOptions.class.php'),
  normalizer: join(repositoryRoot, 'engine', 'client', 'domain', 'userBalance.ts'),
  shell: join(repositoryRoot, 'engine', 'client', 'shell', 'useEngineShell.ts'),
  userBlock: join(themeRoot, 'src', 'UserBlock.vue'),
  profileView: join(repositoryRoot, 'engine', 'classes', 'modules', 'UserSettings', 'client', 'views', 'ProfileView.vue'),
  profileShell: join(themeRoot, 'src', 'userOptions', 'userOptions', 'Profile.vue'),
  profileSection: join(themeRoot, 'src', 'userOptions', 'userOptions', 'profile', 'ProfileDataSection.vue'),
  profileStyles: join(themeRoot, 'src', 'styles', 'profile.css'),
  adminEditor: join(themeRoot, 'src', 'foxEngine', 'admin', 'users', 'UserEditor.vue'),
  migration: join(repositoryRoot, 'database', 'migrations', '016_balance_matrix.sql'),
}

const source = Object.fromEntries(await Promise.all(
  Object.entries(files).map(async ([name, path]) => [name, await readFile(path, 'utf8')]),
))

for (const token of [
  "'units' => [",
  "'crystals' => [",
  'public static function normalize(',
  'public static function encode(',
  'Balance matrix must be a JSON object or array.',
  'foreach ($entry as $legacyCode => $legacyAmount)',
]) {
  if (!source.php.includes(token)) failures.push(`BalanceMatrix PHP contract is missing ${token}`)
}

for (const [name, tokens] of Object.entries({
  renderer: ["'balance'", 'BalanceMatrix::normalize'],
  auth: ['BalanceMatrix::normalize'],
  profileApi: ['BalanceMatrix::normalize'],
  adminApi: ['BalanceMatrix::normalize', 'BalanceMatrix::encode'],
  normalizer: ["code: 'units' | 'crystals'", 'normalizeBalanceMatrix', 'balanceCurrencyIconPath', 'formatBalanceAmount', 'Object.entries(entry)'],
  shell: ['normalizeBalanceMatrix(appBootstrap.user.balance)', 'balance,'],
  userBlock: ['profile-dropdown__item--balance', 'balanceCurrencies', 'themeAsset(appBootstrap, balanceCurrencyIconPath(currency.code))', '<img :src="currency.icon"'],
  profileView: ['themeAsset(appBootstrap, balanceCurrencyIconPath(currency.code))', 'kind: currency.code'],
  profileShell: ['variant="balance"', ':entries="balances"'],
  profileSection: ['profile-data-section--balance', 'profile-balance-grid', 'profile-balance-footer', 'profile-data-grid__entry--icon', 'entry.kind'],
  profileStyles: ['.profile-data-section--balance', '.profile-balance-grid', '.profile-data-grid__entry--units', '.profile-data-grid__entry--crystals', '.profile-balance-footer'],
  adminEditor: ['admin-balance-matrix', 'updateBalanceAmount', 'balanceMatrix.currencies', 'balanceIcons', '<img :src="balanceIcons[currency.code]"'],
  migration: ['upgrade legacy user balances', '$[0].crystals', '$[1].units', 'fox_balance_upgrade_016'],
})) {
  for (const token of tokens) {
    if (!source[name].includes(token)) failures.push(`${name} balance contract is missing ${token}`)
  }
}


for (const [name, relativePath] of Object.entries({
  units: join(themeRoot, 'assets', 'icons', 'units.png'),
  crystals: join(themeRoot, 'assets', 'icons', 'crystals.png'),
})) {
  const image = await readFile(relativePath)
  const pngSignature = Buffer.from([0x89, 0x50, 0x4e, 0x47, 0x0d, 0x0a, 0x1a, 0x0a])
  if (image.length < 32 || !image.subarray(0, 8).equals(pngSignature)) {
    failures.push(`${name} currency icon must be a valid PNG asset`)
  }
}

if (source.userBlock.includes('fa-gem') || source.userBlock.includes('fa-coins')
    || source.adminEditor.includes('fa-gem') || source.adminEditor.includes('fa-coins')) {
  failures.push('Currency UI must use project PNG icons instead of Font Awesome placeholders')
}

if (/TRIM\s*\(\s*`balance`\s*\)\s+IN/iu.test(source.migration)) {
  failures.push('Migration 016 must not compare JSON text through IN because MariaDB can coerce it to DOUBLE')
}
if (!source.migration.includes('`is_canonical` TINYINT(1)')
    || !source.migration.includes("JSON_TYPE(JSON_EXTRACT(`balance_text`, '$.currencies')) = 'ARRAY'")
    || !source.migration.includes('AND `upgrade`.`is_canonical` = 0')) {
  failures.push('Migration 016 must leave already canonical balance matrices unchanged')
}

if (source.shell.includes("bootstrapNumber(appBootstrap, 'units'")) {
  failures.push('Header balance must not read the legacy users.units column')
}
if (source.migration.includes("GREATEST(COALESCE(`units`")) {
  failures.push('Balance migration must not perform arithmetic on the raw legacy users.units column')
}
if (source.migration.includes("TRIM(`balance`) IN")) {
  failures.push('Balance migration must not compare raw JSON through IN because MariaDB may coerce it to DOUBLE')
}
for (const token of ['units_fallback_text', 'JSON_VALID(`balance_text`)', '$[0].crystals', '$[1].units']) {
  if (!source.migration.includes(token)) failures.push(`Legacy balance staging contract is missing ${token}`)
}
if (!source.userBlock.includes('role="menuitem"') || !source.userBlock.includes('aria-disabled="true"')) {
  failures.push('Static balance panel must preserve menu accessibility semantics')
}


const expectedProfileCopy = [
  'валюта',
  'валюты',
  'валют',
  'Данные пока не опубликованы.',
  'Баланс закреплён за профилем игрока и хранится в единой матрице валют.',
]
for (const copy of expectedProfileCopy) {
  if (!source.profileSection.includes(copy)) failures.push(`Profile balance copy is missing or has invalid encoding: ${copy}`)
}
for (const marker of ['Р‘Р°', 'РІР°', 'Р”Р°']) {
  if (source.profileSection.includes(marker)) failures.push('Profile balance copy contains UTF-8/Windows-1251 mojibake')
}

if (failures.length) {
  console.error('Balance matrix contract failed:')
  for (const failure of failures) console.error(`- ${failure}`)
  process.exit(1)
}

console.log('Balance matrix contract passed: Units and Crystals are UUID-bound through users.balance and rendered in the profile dropdown.')
