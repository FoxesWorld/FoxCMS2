import { readFile, readdir } from 'node:fs/promises'
import { extname, join, relative } from 'node:path'
import { clientSourceRoots, engineClientRoot, repositoryRoot } from './theme-paths.mjs'

const failures = []
const frontendActions = new Map([
  ['sysRequest', new Set()],
  ['user_doaction', new Set()],
  ['admPanel', new Set()],
  ['userAction', new Set()],
])
async function walk(directory, callback) {
  for (const entry of await readdir(directory, { withFileTypes: true })) {
    const path = join(directory, entry.name)
    if (entry.isDirectory()) { await walk(path, callback); continue }
    await callback(path)
  }
}
for (const root of clientSourceRoots) {
  await walk(root, async (path) => {
    if (!['.ts', '.vue'].includes(extname(path).toLowerCase())) return
    const text = await readFile(path, 'utf8')
    const rel = relative(repositoryRoot, path).replaceAll('\\', '/')
    for (const [key, actions] of frontendActions) {
      const direct = new RegExp(`${key}\\s*:\\s*['\"]([^'\"]+)['\"]`, 'g')
      const formData = new RegExp(`\\.set\\(\\s*['\"]${key}['\"]\\s*,\\s*['\"]([^'\"]+)['\"]`, 'g')
      for (const match of text.matchAll(direct)) actions.add(match[1])
      for (const match of text.matchAll(formData)) actions.add(match[1])
    }
    for (const signature of ['secureKey', 'foxesHash', "serverName = '", '/plugins/', 'LegacyRouteView']) {
      if (text.includes(signature)) failures.push(`forbidden client contract ${signature} in ${rel}`)
    }
    if (/fetch\s*\(\s*['\"]\//.test(text) && path !== join(engineClientRoot, 'api', 'FoxesApiClient.ts')) {
      failures.push(`direct backend fetch bypasses FoxesApiClient in ${rel}`)
    }
  })
}

const backendRouters = new Map([
  ['sysRequest', join(repositoryRoot, 'engine', 'SystemRequests.class.php')],
  ['user_doaction', join(repositoryRoot, 'engine', 'classes', 'modules', 'UserSettings', 'UserActions.class.php')],
  ['admPanel', join(repositoryRoot, 'engine', 'classes', 'modules', 'AdminPanel', 'AdminOptions.class.php')],
  ['userAction', join(repositoryRoot, 'engine', 'classes', 'modules', 'AuthReg', 'AuthReg.class.php')],
])
for (const [key, path] of backendRouters) {
  const text = await readFile(path, 'utf8')
  const backendActions = new Set([
    ...[...text.matchAll(/case\s+['"]([^'"]+)['"]\s*:/g)].map((match) => match[1]),
    ...[...text.matchAll(/['"]([^'"]+)['"]\s*=>\s*(?:\$this->|\(new\s|new\s)/g)].map((match) => match[1]),
    ...[...text.matchAll(/['"]([^'"]+)['"]\s*=>\s*['"][A-Za-z_][A-Za-z0-9_]*['"]/g)].map((match) => match[1]),
  ])
  for (const action of frontendActions.get(key)) {
    if (!backendActions.has(action)) failures.push(`client ${key}=${action} has no backend route`)
  }
}
const apiClient = await readFile(join(engineClientRoot, 'api', 'FoxesApiClient.ts'), 'utf8')
if (!apiClient.includes("body.set('csrf_token', csrfToken)")) failures.push('FoxesApiClient does not attach the session CSRF token')
if (!apiClient.includes("credentials: 'same-origin'")) failures.push('FoxesApiClient does not bind requests to the browser session')
if (failures.length) {
  console.error('API contract check failed:')
  for (const failure of [...new Set(failures)]) console.error(`- ${failure}`)
  process.exit(1)
}
const summary = Object.fromEntries([...frontendActions].map(([key, actions]) => [key, [...actions].sort()]))
console.log(`API contract passed: ${JSON.stringify(summary)}`)
