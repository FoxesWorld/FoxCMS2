import { readFile } from 'node:fs/promises'
import { join } from 'node:path'
import { repositoryRoot, themeRoot } from './theme-paths.mjs'
import { includesLocalized } from './i18n-test-utils.mjs'

const contracts = [
  ['engine/src/FoxCMS/Engine/Application/ApplicationKernel.php', [
    "loadPriority(MODULES_DIR, 'preInit', ['AuthReg'])",
    '(new MaintenanceGate())->enforce($maintenance, $this->context)',
  ]],
  ['engine/src/FoxCMS/Engine/Application/MaintenanceGate.php', [
    'new \\MaintenanceRenderer(',
    '], 503',
    '\MaintenanceModePolicy::allows($settings, $context->session)',
  ]],
  ['engine/classes/services/MaintenanceModePolicy.class.php', [
    '$session->isAdmin()',
    "$settings['allowedGroups']",
    'in_array($session->group(), $groups, true)',
    "['', 'auth', 'logout', 'lastUser']",
  ]],
  ['engine/classes/repositories/MaintenanceModeRepository.class.php', [
    'site_maintenance',
    "'allowedGroups' => ['admin']",
    'public function save(',
  ]],
  ['engine/classes/modules/AdminPanel/AdminOptions.class.php', [
    "'maintenance' => 'maintenance'",
    "'saveMaintenance' => 'saveMaintenance'",
    'MaintenanceModeRepository',
  ]],
  ['engine/classes/modules/AuthReg/AuthReg.class.php', [
    'MaintenanceModePolicy::authActionAllowed($action)',
    "'code' => 'maintenance_mode'",
    "$this->request->boolean('maintenanceAccess')",
    'MaintenanceModePolicy::allows($maintenance, $this->session)',
    'maintenance_group_required',
    '$this->sessionLifecycle->invalidateCurrent()',
  ]],
  ['engine/src/FoxCMS/Engine/Auth/AuthSessionLifecycle.php', [
    'public function invalidateCurrent(): void',
    '$this->session->clear()',
    '$this->rememberCookie->clear()',
  ]],
  ['engine/src/FoxCMS/Engine/Auth/RememberCookie.php', [
    'public function clear(): void',
    "'secure' => $this->request->isSecure()",
    "'httponly' => true",
    "'samesite' => 'Lax'",
  ]],
  ['engine/classes/modules/AuthReg/actions/authorise.class.php', [
    "!$this->request->boolean('maintenanceAccess')",
  ]],
  ['engine/classes/themes/MaintenanceRenderer.class.php', [
    'name=\"maintenanceAccess\" value=\"1\"',
    'У вас есть доступ во время техработ?',
    'Проверить доступ и войти',
    'private function currentSeason()',
    'data-season=\"',
    "$logoAsset = $assetBase . 'img/logo.png'",
    'maintenance-brand__logo',
    '$month >= 3 && $month <= 5',
    '$month >= 6 && $month <= 8',
    '$month >= 9 && $month <= 11',
  ]],
  ['templates/foxengine2/src/foxEngine/admin/Maintenance.vue', [
    'Администраторы и отмеченные ниже группы могут войти через форму на заглушке.',
    "group.groupTag === 'admin'",
    "emit('save')",
  ]],
  ['database/migrations/005_site_maintenance.sql', [
    'CREATE TABLE IF NOT EXISTS `site_maintenance`',
    "(1, 0, '[1]'",
  ]],
  ['database/migrations/006_group_tag_identity.sql', [
    "SET `allowedGroups` = '[\"admin\"]'",
    '`groupTag` VARCHAR(64)',
  ]],
]

const failures = []
for (const [relativePath, signatures] of contracts) {
  const root = relativePath.startsWith('templates/') ? repositoryRoot : repositoryRoot
  const text = await readFile(join(root, relativePath), 'utf8')
  for (const signature of signatures) {
    if (!includesLocalized(text, signature)) failures.push(`${relativePath} missing maintenance contract: ${signature}`)
  }
}

const adminCss = await readFile(join(themeRoot, 'assets/css/admin-maintenance.css'), 'utf8')
const publicCss = await readFile(join(themeRoot, 'assets/css/maintenance.css'), 'utf8')
const publicJs = await readFile(join(themeRoot, 'assets/maintenance.js'), 'utf8')
if (!adminCss.includes('.maintenance-admin')) failures.push('maintenance admin stylesheet is missing')
if (!publicCss.includes('.maintenance-shell') || !publicCss.includes('.maintenance-admin-access') || !publicCss.includes('.maintenance-brand__logo')) failures.push('maintenance placeholder, authorized-group access or logo stylesheet is missing')
const seasonalBackgrounds = {
  spring: '../img/season/spring.png',
  summer: '../img/season/summer.png',
  autumn: '../img/season/autumn.png',
  winter: '../img/season/winter.jpg',
}
for (const [season, asset] of Object.entries(seasonalBackgrounds)) {
  if (!publicCss.includes(`body[data-season=${season}]`) || !publicCss.includes(asset)) {
    failures.push(`maintenance seasonal background is missing for ${season}`)
  }
}
if (!publicCss.includes('var(--maintenance-season-image') || !publicCss.includes('backdrop-filter:blur(')) failures.push('maintenance seasonal overlay or glass surface is missing')
if (!publicJs.includes('new URLSearchParams(new FormData(form))') || !publicJs.includes('window.location.reload()')) failures.push('maintenance access script is missing form submission or access refresh')

const authManager = await readFile(join(repositoryRoot, 'engine/classes/modules/AuthReg/AuthReg.class.php'), 'utf8')
const renderer = await readFile(join(repositoryRoot, 'engine/classes/themes/MaintenanceRenderer.class.php'), 'utf8')
for (const legacy of ["'code' => 'administrator_required'", 'Эта форма предназначена только для администраторов.', 'Войти как администратор', 'Вы администратор?']) {
  if (authManager.includes(legacy) || renderer.includes(legacy)) failures.push(`legacy administrator-only maintenance access remains: ${legacy}`)
}
if (!authManager.includes("'auth' => $this->authenticate($maintenance)")) failures.push('maintenance settings are not passed into authentication')
if (!authManager.includes("'Группа этой учётной записи не допущена во время технических работ.'")) failures.push('disallowed maintenance group response is missing')

if (failures.length) {
  console.error('Maintenance mode check failed:')
  for (const failure of failures) console.error(`- ${failure}`)
  process.exit(1)
}
console.log('Maintenance mode passed: server gate, configured-group access, admin controls and synchronized seasonal background are present.')
