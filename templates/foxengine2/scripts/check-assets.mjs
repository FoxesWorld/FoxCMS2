import { access, readFile } from 'node:fs/promises'
import { join } from 'node:path'
import { repositoryRoot, runtimeRoot, themeManifestPath, themeRoot, themeShellPath, themeName } from './theme-paths.mjs'

const failures = []
async function exists(path) { try { await access(path); return true } catch { return false } }

let manifest = null
try { manifest = JSON.parse(await readFile(themeManifestPath, 'utf8')) }
catch (error) { failures.push(`invalid theme manifest for ${themeName}: ${error.message}`) }

if (!(await exists(themeShellPath))) failures.push(`theme shell is missing: templates/${themeName}/index.html`)
else {
  const html = await readFile(themeShellPath, 'utf8')
  for (const marker of ['id="foxescraft-bootstrap"', '<!-- foxescraft:styles -->', '<!-- foxescraft:scripts -->']) {
    if (!html.includes(marker)) failures.push(`theme shell lacks marker: ${marker}`)
  }
}

if (manifest && manifest.schema !== 1) failures.push('theme manifest schema must be 1')
const frontendRelative = manifest?.frontend ?? 'frontend.json'
if (typeof frontendRelative !== 'string' || frontendRelative.startsWith('/') || frontendRelative.split(/[\\/]/).includes('..')) {
  failures.push(`unsafe theme frontend manifest path: ${String(frontendRelative)}`)
} else if (!(await exists(join(themeRoot, frontendRelative)))) {
  failures.push(`theme frontend manifest is missing: ${frontendRelative}`)
}
for (const kind of ['styles', 'scripts']) {
  for (const relative of manifest?.assets?.[kind] ?? []) {
    if (!(await exists(join(themeRoot, relative)))) failures.push(`theme ${kind} asset is missing: ${relative}`)
  }
}
for (const required of [
  'data/pages.json',
  'data/badges/earlyuser.html',
  'data/slides.json',
]) {
  if (!(await exists(join(themeRoot, required)))) failures.push(`theme runtime data is missing: ${required}`)
}
if (!(await exists(join(repositoryRoot, 'api', 'content.php')))) failures.push('public content API is missing: api/content.php')
if (await exists(join(repositoryRoot, 'engine', 'data', 'frontend.json'))) failures.push('engine/data/frontend.json must not exist')
if (!(await exists(join(runtimeRoot, 'theme.js')))) failures.push('theme runtime entry is missing: assets/runtime/theme.js')
if (!(await exists(join(runtimeRoot, 'theme.css')))) failures.push('theme stylesheet is missing: assets/runtime/theme.css')
if (await exists(join(themeRoot, 'app'))) failures.push(`forbidden one-off app directory exists: templates/${themeName}/app`)

if (failures.length) {
  console.error('Theme asset contract failed:')
  for (const failure of [...new Set(failures)]) console.error(`- ${failure}`)
  process.exit(1)
}
console.log(`Theme asset contract passed: ${themeName} owns its shell, routes and runtime assets under /templates.`)
