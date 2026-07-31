import { readFile } from 'node:fs/promises'
import { join } from 'node:path'
import { repositoryRoot, themeRoot } from './theme-paths.mjs'

const contracts = [
  ['engine/Application.class.php', [
    "loadPriority(MODULES_DIR, 'preInit', ['AuthReg'])",
    'enforceMaintenance($maintenance)',
    'new MaintenanceRenderer(',
    'http_response_code(503)',
  ]],
  ['engine/classes/services/MaintenanceModePolicy.class.php', [
    '$session->isAdmin()',
    "['', 'auth', 'logout', 'lastUser']",
  ]],
  ['engine/classes/repositories/MaintenanceModeRepository.class.php', [
    'site_maintenance',
    "'allowedGroups' => [1]",
    'public function save(',
  ]],
  ['engine/classes/modules/AdminPanel/AdminOptions.class.php', [
    "case 'maintenance':",
    "case 'saveMaintenance':",
    'MaintenanceModeRepository',
  ]],
  ['engine/classes/modules/AuthReg/AuthReg.class.php', [
    'MaintenanceModePolicy::authActionAllowed($action)',
    "'code' => 'maintenance_mode'",
  ]],
  ['templates/foxengine2/src/foxEngine/admin/Maintenance.vue', [
    'Администраторы допускаются всегда.',
    "group.groupNum === 1",
    "emit('save')",
  ]],
  ['database/migrations/005_site_maintenance.sql', [
    'CREATE TABLE IF NOT EXISTS `site_maintenance`',
    "(1, 0, '[1]'",
  ]],
]

const failures = []
for (const [relativePath, signatures] of contracts) {
  const root = relativePath.startsWith('templates/') ? repositoryRoot : repositoryRoot
  const text = await readFile(join(root, relativePath), 'utf8')
  for (const signature of signatures) {
    if (!text.includes(signature)) failures.push(`${relativePath} missing maintenance contract: ${signature}`)
  }
}

const adminCss = await readFile(join(themeRoot, 'assets/css/admin-maintenance.css'), 'utf8')
const publicCss = await readFile(join(themeRoot, 'assets/css/maintenance.css'), 'utf8')
const publicJs = await readFile(join(themeRoot, 'assets/maintenance.js'), 'utf8')
if (!adminCss.includes('.maintenance-admin')) failures.push('maintenance admin stylesheet is missing')
if (!publicCss.includes('.maintenance-shell')) failures.push('maintenance placeholder stylesheet is missing')
if (!publicJs.includes('new URLSearchParams(new FormData(form))') || !publicJs.includes('window.location.reload()')) failures.push('maintenance access script is missing form submission or access refresh')

if (failures.length) {
  console.error('Maintenance mode check failed:')
  for (const failure of failures) console.error(`- ${failure}`)
  process.exit(1)
}
console.log('Maintenance mode passed: server gate, admin controls, group policy and standalone placeholder are present.')
