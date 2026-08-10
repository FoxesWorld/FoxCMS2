import { readdir, readFile } from 'node:fs/promises'
import { extname, join, relative } from 'node:path'
import { modulesRoot, repositoryRoot, sourceRoot, themeRoot, engineClientRoot } from './theme-paths.mjs'

const failures = []
const sourceDirectories = [sourceRoot, engineClientRoot, modulesRoot, join(themeRoot, 'userOptions')]
const fontsPath = join(themeRoot, 'src', 'styles', 'fonts.css')
const fontAwesomePath = join(themeRoot, 'src', 'styles', 'font-awesome-pro.css')
const fonts = await readFile(fontsPath, 'utf8')
const fontAwesome = await readFile(fontAwesomePath, 'utf8')
const mainSource = await readFile(join(themeRoot, 'src', 'main.ts'), 'utf8')
const iconStyles = `${fonts}\n${fontAwesome}`
const defined = new Set([...iconStyles.matchAll(/\.(fa-[a-z0-9-]+):{1,2}before\b/g)].map((match) => match[1]))
const ignored = new Set(['fa-solid', 'fa-regular', 'fa-brands', 'fa-fw'])
const used = new Map()

async function walk(directory) {
  const entries = await readdir(directory, { withFileTypes: true }).catch(() => [])
  for (const entry of entries) {
    const path = join(directory, entry.name)
    if (entry.isDirectory()) {
      await walk(path)
      continue
    }
    if (!entry.isFile() || !['.vue', '.ts', '.html', '.php', '.tpl'].includes(extname(entry.name))) continue
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
  'Font Awesome Pro 6.3.0',
  'fa-solid-900.woff2',
  'fa-regular-400.woff2',
  'fa-brands-400.woff2',
  '.fa-solid',
  '.fa-regular',
  '.fa-brands',
]) {
  if (!fontAwesome.includes(token)) failures.push(`Font Awesome Pro stylesheet is missing ${token}`)
}
if (defined.size < 2_000) failures.push(`Font Awesome Pro mapping is incomplete: only ${defined.size} icon classes found`)
if (!mainSource.includes("import './styles/font-awesome-pro.css'")) failures.push('Font Awesome Pro stylesheet is not imported by src/main.ts')

if (failures.length) {
  console.error('Icon contract failed:')
  for (const failure of failures) console.error(`- ${failure}`)
  process.exit(1)
}

console.log(`Icon contract passed: ${used.size} used Font Awesome icons have explicit glyph mappings.`)
