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
  'pages/content/about.html',
  'data/badges/earlyuser.html',
  'data/slides.json',
  'userOptions/ProfileSettings.tpl',
  'userOptions/AdminPanel.tpl',
  'pages/templates/StaticContent.tpl',
  'pages/templates/StartGame.tpl',
  'pages/templates/Badges.tpl',
  'pages/templates/Badge.tpl',
  'pages/templates/Achievements.tpl',
  'pages/templates/achievements/StatisticsTree.tpl',
  'pages/templates/achievements/TreeNode.tpl',
  'pages/templates/achievements/ProfilePanel.tpl',
]) {
  if (!(await exists(join(themeRoot, required)))) failures.push(`theme runtime data is missing: ${required}`)
}

const themeResolver = await readFile(join(repositoryRoot, 'engine', 'classes', 'themes', 'ThemeResolver.class.php'), 'utf8')
for (const token of ["hash_file('sha256', $path)", "'?v=' . substr($hash, 0, 16)"]) {
  if (!themeResolver.includes(token)) failures.push(`theme asset versioning is missing ${token}`)
}

const deploymentVerifier = await readFile(join(repositoryRoot, 'scripts', 'verify-deployment.py'), 'utf8')
for (const token of ['ThemePageStorage.class.php', 'ThemePageTemplateRepository.class.php', 'theme_root / "pages" / "content"', 'theme_root / "pages" / "templates"', 'obsolete split page storage exists']) {
  if (!deploymentVerifier.includes(token)) failures.push(`deployment verifier is missing unified page requirement ${token}`)
}
const deploymentScript = await readFile(join(repositoryRoot, 'scripts', 'deploy-production.sh'), 'utf8')
for (const token of ['DEPLOY_THEME=', 'verify-deployment.py', 'Source deployment preflight passed']) {
  if (!deploymentScript.includes(token)) failures.push(`production deployment preflight is missing ${token}`)
}

for (const directory of ['pages/content', 'pages/templates']) {
  if (!(await exists(join(themeRoot, directory)))) failures.push(`unified page directory is missing: ${directory}`)
}
for (const obsolete of ['data/pages', 'pages/achievements']) {
  if (await exists(join(themeRoot, obsolete))) failures.push(`obsolete split page storage exists: ${obsolete}`)
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
