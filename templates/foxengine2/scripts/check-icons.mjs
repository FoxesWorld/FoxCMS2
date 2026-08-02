import { readdir, readFile } from 'node:fs/promises'
import { extname, join, relative } from 'node:path'
import { modulesRoot, repositoryRoot, sourceRoot, themeRoot, engineClientRoot } from './theme-paths.mjs'

const failures = []
const sourceDirectories = [sourceRoot, engineClientRoot, modulesRoot]
const fontsPath = join(themeRoot, 'src', 'styles', 'fonts.css')
const fonts = await readFile(fontsPath, 'utf8')
const defined = new Set([...fonts.matchAll(/\.(fa-[a-z0-9-]+)::before/g)].map((match) => match[1]))
const ignored = new Set(['fa-solid', 'fa-fw'])
const used = new Map()

async function walk(directory) {
  const entries = await readdir(directory, { withFileTypes: true }).catch(() => [])
  for (const entry of entries) {
    const path = join(directory, entry.name)
    if (entry.isDirectory()) {
      await walk(path)
      continue
    }
    if (!entry.isFile() || !['.vue', '.ts', '.html', '.php'].includes(extname(entry.name))) continue
    const source = await readFile(path, 'utf8')
    for (const match of source.matchAll(/\bfa-[a-z0-9-]+\b/g)) {
      const icon = match[0]
      if (ignored.has(icon)) continue
      const references = used.get(icon) ?? []
      references.push(relative(repositoryRoot, path).replaceAll('\\', '/'))
      used.set(icon, references)
    }
  }
}

for (const directory of sourceDirectories) await walk(directory)
for (const [icon, references] of [...used].sort(([left], [right]) => left.localeCompare(right))) {
  if (!defined.has(icon)) failures.push(`${icon} has no ::before glyph mapping; used by ${[...new Set(references)].join(', ')}`)
}

for (const token of [
  '@font-face{font-family:"Font Awesome 6 Pro"',
  'fa-solid-900.woff2',
  'font-variant:normal',
  'text-rendering:auto',
  'speak:never',
]) {
  if (!fonts.includes(token)) failures.push(`Font Awesome base rule is missing ${token}`)
}

if (failures.length) {
  console.error('Icon contract failed:')
  for (const failure of failures) console.error(`- ${failure}`)
  process.exit(1)
}

console.log(`Icon contract passed: ${used.size} used Font Awesome icons have explicit glyph mappings.`)
