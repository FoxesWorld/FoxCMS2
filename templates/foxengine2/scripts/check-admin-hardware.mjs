import { spawnSync } from 'node:child_process'
import { homedir } from 'node:os'
import { join } from 'node:path'
import { fileURLToPath } from 'node:url'

const repositoryRoot = fileURLToPath(new URL('../../..', import.meta.url))
const checker = join(repositoryRoot, 'scripts/check-admin-hardware.php')
const candidates = [
  process.env.PHP_BINARY,
  process.env.PHP_PATH,
  join(homedir(), 'Documents', 'Take Some', 'Tools', 'toolbelt', 'third_party', 'php', 'php.exe'),
  'php',
].filter(Boolean)

let php = null
for (const candidate of [...new Set(candidates)]) {
  const probe = spawnSync(candidate, ['--version'], { encoding: 'utf8', windowsHide: true })
  if (!probe.error && probe.status === 0) {
    php = candidate
    break
  }
}

if (!php) {
  console.error('PHP CLI was not found; admin hardware contract cannot be verified.')
  process.exit(1)
}

const result = spawnSync(php, [checker], {
  cwd: repositoryRoot,
  encoding: 'utf8',
  windowsHide: true,
})
if (result.stdout) process.stdout.write(result.stdout)
if (result.stderr) process.stderr.write(result.stderr)
if (result.error || result.status !== 0) process.exit(result.status ?? 1)
