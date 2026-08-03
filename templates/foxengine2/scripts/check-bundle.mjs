import { readFile, readdir, stat } from 'node:fs/promises'
import { join, relative, resolve } from 'node:path'
import { gzipSync } from 'node:zlib'
import { runtimeRoot, themeManifestPath, themeRoot } from './theme-paths.mjs'

const paths = new Set()
async function walk(directory) {
  for (const entry of await readdir(directory, { withFileTypes: true })) {
    const path = join(directory, entry.name)
    if (entry.isDirectory()) { await walk(path); continue }
    if (/\.(?:js|css)$/.test(entry.name)) paths.add(resolve(path))
  }
}
await walk(runtimeRoot)

const manifest = JSON.parse(await readFile(themeManifestPath, 'utf8'))
for (const relativePath of manifest?.assets?.styles ?? []) {
  if (typeof relativePath === 'string' && relativePath.endsWith('.css')) {
    paths.add(resolve(themeRoot, relativePath))
  }
}

const entries = []
for (const path of paths) {
  const raw = (await stat(path)).size
  const gzip = gzipSync(await readFile(path)).length
  entries.push({ name: relative(themeRoot, path).replaceAll('\\', '/'), raw, gzip })
}
entries.sort((left, right) => left.name.localeCompare(right.name))

const mainJs = entries.find((entry) => entry.name === 'assets/runtime/theme.js')
const codeEditor = entries.find((entry) => /assets\/runtime\/chunks\/CodeEditor-[^/]+\.js$/.test(entry.name))
const pinturaEditor = entries.find((entry) => /assets\/runtime\/chunks\/pintura-[^/]+\.js$/.test(entry.name))
const pinturaLocale = entries.find((entry) => /assets\/runtime\/chunks\/ru_RU-[^/]+\.js$/.test(entry.name))
const themeCss = entries.find((entry) => entry.name === 'assets/runtime/theme.css')
const mainSource = await readFile(join(runtimeRoot, 'theme.js'), 'utf8')
const totalGzip = entries.reduce((sum, entry) => sum + entry.gzip, 0)
const budgets = {
  mainJs: 55 * 1024,
  codeEditorChunk: 130 * 1024,
  pinturaChunk: 225 * 1024,
  pinturaLocaleChunk: 10 * 1024,
  themeCss: 55 * 1024,
}
const failures = []
if (!mainJs || mainJs.gzip > budgets.mainJs) failures.push(`theme.js gzip budget exceeded: ${mainJs?.gzip ?? 0} bytes`)
if (codeEditor && codeEditor.gzip > budgets.codeEditorChunk) failures.push(`CodeEditor lazy chunk gzip budget exceeded: ${codeEditor.gzip} bytes`)
if (!pinturaEditor) failures.push('Pintura must be emitted as a dedicated lazy chunk')
else if (pinturaEditor.gzip > budgets.pinturaChunk) failures.push(`Pintura lazy chunk gzip budget exceeded: ${pinturaEditor.gzip} bytes`)
if (!pinturaLocale) failures.push('Pintura Russian locale must be emitted as a dedicated lazy chunk')
else if (pinturaLocale.gzip > budgets.pinturaLocaleChunk) failures.push(`Pintura locale chunk gzip budget exceeded: ${pinturaLocale.gzip} bytes`)
if (themeCss && themeCss.gzip > budgets.themeCss) console.warn(`theme.css gzip exceeds the advisory budget: ${themeCss.gzip} bytes`)
if (mainSource.includes('PinturaRoot') || mainSource.includes('openDefaultEditor=function')) {
  failures.push('Pintura implementation leaked into eager theme.js instead of its lazy chunk')
}
console.table(entries.map((entry) => ({ file: entry.name, rawKb: (entry.raw / 1024).toFixed(2), gzipKb: (entry.gzip / 1024).toFixed(2) })))
console.log(`Complete theme client gzip: ${(totalGzip / 1024).toFixed(2)} kB`)
if (failures.length) { for (const failure of failures) console.error(failure); process.exit(1) }
console.log('Configured JavaScript bundle budgets passed; total client size is informational only.')
