import { readFile, readdir } from 'node:fs/promises'
import { extname, relative } from 'node:path'
import { clientSourceRoots, repositoryRoot } from './theme-paths.mjs'

const extensions = new Set(['.ts', '.vue', '.css'])
const violations = []
async function walk(directory) {
  for (const entry of await readdir(directory, { withFileTypes: true })) {
    const path = `${directory}/${entry.name}`
    if (entry.isDirectory()) { await walk(path); continue }
    if (!extensions.has(extname(entry.name))) continue
    const rel = relative(repositoryRoot, path).replaceAll('\\', '/')
    const text = await readFile(path, 'utf8')
    for (const [pattern, label] of [
      [/\b(?:jQuery|\$)\s*\(/g, 'jQuery call'],
      [/from\s+['"](?:bootstrap|jquery|jquery-ui)/g, 'legacy package import'],
      [/new\s+Vue\s*\(/g, 'Vue 2 constructor'],
      [/<[A-Za-z][^>]*\son[a-z]+\s*=/gi, 'inline DOM event handler'],
    ]) if (pattern.test(text)) violations.push(`${rel}: ${label}`)
    if (text.includes('/plugins/')) violations.push(`${rel}: direct /plugins/ access`)
  }
}
for (const root of clientSourceRoots) await walk(root)
if (violations.length) {
  console.error('Modern client boundary violations:')
  for (const violation of violations) console.error(`- ${violation}`)
  process.exit(1)
}
console.log('Modern client boundary passed: engine, modules and selected theme are free of legacy browser runtimes.')
