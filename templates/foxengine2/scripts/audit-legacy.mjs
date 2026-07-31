import { existsSync } from 'node:fs'
import { readFile, readdir, writeFile } from 'node:fs/promises'
import { extname, join, relative } from 'node:path'
import { fileURLToPath } from 'node:url'

const themeRoot = fileURLToPath(new URL('..', import.meta.url))
const repositoryRoot = fileURLToPath(new URL('../../..', import.meta.url))
const baselinePath = join(themeRoot, 'legacy-baseline.json')
const writeBaseline = process.argv.includes('--write-baseline')
const textExtensions = new Set(['.php', '.tpl', '.ftpl', '.js', '.ts', '.vue', '.css', '.html'])
const excludedDirectories = new Set(['.git', '.vite', 'node_modules', 'app', 'runtime'])
const patterns = {
  inlineHandlers: /\son[a-z]+\s*=/gi,
  inlineStyles: /\sstyle\s*=/gi,
  jqueryCalls: /\b(?:jQuery|\$)\s*\(/g,
  bootstrapHooks: /\b(?:data-bs-|navbar-|container-fluid|col-(?:sm-|md-|lg-|xl-)?\d)/gi,
}
const metrics = { inlineHandlers: 0, inlineStyles: 0, jqueryCalls: 0, bootstrapHooks: 0, backupFiles: 0 }

async function walk(directory) {
  for (const entry of await readdir(directory, { withFileTypes: true })) {
    if (entry.isDirectory() && excludedDirectories.has(entry.name)) continue
    const path = join(directory, entry.name)
    if (entry.isDirectory()) { await walk(path); continue }
    if (/\.(?:old|bak|php1|php-old|js--|png--)$/i.test(entry.name)) metrics.backupFiles += 1
    if (!textExtensions.has(extname(entry.name).toLowerCase())) continue
    const text = await readFile(path, 'utf8').catch(() => '')
    for (const [key, pattern] of Object.entries(patterns)) metrics[key] += text.match(pattern)?.length ?? 0
  }
}

await walk(repositoryRoot)
if (writeBaseline || !existsSync(baselinePath)) {
  await writeFile(baselinePath, `${JSON.stringify(metrics, null, 2)}\n`)
  console.log('Legacy baseline written:', relative(repositoryRoot, baselinePath))
  console.table(metrics)
  process.exit(0)
}
const baseline = JSON.parse(await readFile(baselinePath, 'utf8'))
const regressions = Object.entries(metrics).filter(([key, value]) => value > (baseline[key] ?? 0))
console.table(metrics)
if (metrics.backupFiles > 0) { console.error(`Legacy audit failed: ${metrics.backupFiles} backup artifacts remain.`); process.exit(1) }
if (regressions.length > 0) { console.error('Legacy audit failed: metrics increased:', regressions); process.exit(1) }
console.log('Legacy audit passed: no metric increased and no backup artifacts remain.')
