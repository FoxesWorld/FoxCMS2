import { spawnSync } from 'node:child_process'
import { readFile } from 'node:fs/promises'
import { homedir } from 'node:os'
import { join } from 'node:path'
import { fileURLToPath } from 'node:url'

const repositoryRoot = fileURLToPath(new URL('../../..', import.meta.url))

const files = {
  manifest: `${await readFile(join(repositoryRoot, 'api/src/FoxCMS/Api/Bootstrap/ManifestController.php'), 'utf8')}\n${await readFile(join(repositoryRoot, 'api/src/FoxCMS/Api/Bootstrap/HardwareInventoryRegistrar.php'), 'utf8')}`,
  report: await readFile(join(repositoryRoot, 'api/src/FoxCMS/Api/Bootstrap/HardwareReportValidator.php'), 'utf8'),
  repository: await readFile(join(repositoryRoot, 'api/src/FoxCMS/Api/Bootstrap/HardwareInventoryRepository.php'), 'utf8'),
  migration: await readFile(join(repositoryRoot, 'database/migrations/011_system_hardware_inventory.sql'), 'utf8'),
  updaterHardware: await readFile(
    join(repositoryRoot, '../UpdaterNorth/src/domain/hardware.rs'),
    'utf8',
  ),
  updaterFetch: await readFile(
    join(repositoryRoot, '../UpdaterNorth/src/domain/manifest/fetch.rs'),
    'utf8',
  ),
}

const requirements = [
  [files.manifest, "X-FoxesCraft-Hardware-Inventory: ", 'manifest inventory result header'],
  [files.manifest, "$this->request->method() === 'POST'", 'POST hardware registration boundary'],
  [files.report, "preg_match('/^[a-f0-9]{64}$/D'", 'SHA-256-only systemHWID validation'],
  [files.report, 'hardware_report_platform_mismatch', 'platform consistency validation'],
  [files.repository, 'INSERT IGNORE INTO `system_hardware_inventory`', 'insert-once repository SQL'],
  [files.migration, 'PRIMARY KEY (`systemHWID`)', 'systemHWID uniqueness boundary'],
  [files.updaterHardware, 'FoxesCraft/UpdaterNorth/systemHWID/v1', 'domain-separated client HWID'],
  [files.updaterHardware, '#[serde(rename = "systemHWID")]', 'public systemHWID field contract'],
  [files.updaterFetch, '.post(request_url.clone())', 'UpdaterNorth manifest POST'],
  [files.updaterFetch, '.json(hardware)', 'UpdaterNorth hardware JSON body'],
]

const failures = requirements
  .filter(([source, needle]) => !source.includes(needle))
  .map(([, , description]) => description)

if (files.repository.toUpperCase().includes('ON DUPLICATE KEY UPDATE')) {
  failures.push('existing first-seen hardware records must not be updated')
}

if (failures.length > 0) {
  console.error('Bootstrap hardware contract failures:')
  for (const failure of failures) console.error(`- ${failure}`)
  process.exit(1)
}

const phpCandidates = [
  process.env.PHP_BINARY,
  join(homedir(), 'Documents', 'Take Some', 'Tools', 'toolbelt', 'third_party', 'php', 'php.exe'),
  'php',
].filter(Boolean)

let php = null
for (const candidate of [...new Set(phpCandidates)]) {
  const probe = spawnSync(candidate, ['--version'], { encoding: 'utf8', windowsHide: true })
  if (!probe.error && probe.status === 0) {
    php = candidate
    break
  }
}

if (php) {
  const contract = spawnSync(php, [join(repositoryRoot, 'scripts/check-bootstrap-hardware.php')], {
    cwd: repositoryRoot,
    encoding: 'utf8',
    windowsHide: true,
  })
  if (contract.stdout) process.stdout.write(contract.stdout)
  if (contract.stderr) process.stderr.write(contract.stderr)
  if (contract.error || contract.status !== 0) process.exit(contract.status ?? 1)
} else {
  console.warn('PHP CLI was not found; executable bootstrap hardware validation was skipped.')
}

console.log('Bootstrap hardware integration passed: privacy-safe POST report and insert-once API contract are present.')
