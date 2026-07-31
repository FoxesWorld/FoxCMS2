import { access, readFile, readdir } from 'node:fs/promises'
import { basename, extname, join, relative } from 'node:path'
import {
  engineClientRoot,
  modulesRoot,
  repositoryRoot,
  sourceRoot,
  themeManifestPath,
  themeName,
  themeRoot,
} from './theme-paths.mjs'

const failures = []
async function exists(path) { try { await access(path); return true } catch { return false } }
async function readJson(path) {
  try { return JSON.parse(await readFile(path, 'utf8')) }
  catch (error) { failures.push(`invalid JSON ${relative(repositoryRoot, path)}: ${error.message}`); return null }
}

const themeManifest = await readJson(themeManifestPath)
let frontendManifestPath = join(themeRoot, 'frontend.json')
if (themeManifest) {
  const relativeFrontend = themeManifest.frontend ?? 'frontend.json'
  if (typeof relativeFrontend !== 'string' || relativeFrontend.startsWith('/') || relativeFrontend.split(/[\\/]/).includes('..')) {
    failures.push(`unsafe theme frontend manifest path: ${String(relativeFrontend)}`)
  } else {
    frontendManifestPath = join(themeRoot, relativeFrontend)
  }
}
if (!(await exists(frontendManifestPath))) failures.push(`theme frontend manifest is missing: templates/${themeName}/frontend.json`)

const modulesManifest = await readJson(join(repositoryRoot, 'engine', 'data', 'modules.json'))
const moduleNames = new Set()
if (Array.isArray(modulesManifest)) {
  for (const module of modulesManifest) {
    if (!module || typeof module.name !== 'string') continue
    moduleNames.add(module.name)
    const legacyManifest = join(modulesRoot, module.name, 'frontend.json')
    if (await exists(legacyManifest)) failures.push(`module must not own routes: ${module.name}/frontend.json`)
  }
} else failures.push('engine/data/modules.json must be an array')
if (await exists(join(repositoryRoot, 'engine', 'data', 'frontend.json'))) {
  failures.push('engine must not own template routes: engine/data/frontend.json')
}

const viewNames = new Map()
async function collectViews(directory, owner = null) {
  if (!(await exists(directory))) return
  for (const entry of await readdir(directory, { withFileTypes: true })) {
    const path = join(directory, entry.name)
    if (entry.isDirectory()) { await collectViews(path, owner); continue }
    if (!entry.name.endsWith('View.vue')) continue
    const name = basename(entry.name, '.vue')
    const rel = relative(repositoryRoot, path).replaceAll('\\', '/')
    if (viewNames.has(name)) failures.push(`duplicate client view ${name}: ${viewNames.get(name).path} and ${rel}`)
    else viewNames.set(name, { path: rel, owner })
  }
}
await collectViews(join(engineClientRoot, 'views'))
for (const moduleName of moduleNames) {
  await collectViews(join(modulesRoot, moduleName, 'client', 'views'), moduleName)
}

const frontendManifest = await readJson(frontendManifestPath)
const routeNames = new Map()
const routePaths = new Map()
const referencedViews = new Set()
const navigation = []
const legacy = []
if (frontendManifest) {
  if (frontendManifest.schema !== 1) failures.push(`theme frontend schema must be 1: ${themeName}`)
  for (const route of frontendManifest.routes ?? []) {
    if (!route || typeof route !== 'object') { failures.push(`non-object route in ${themeName}`); continue }
    if (typeof route.name !== 'string' || !/^[A-Za-z][A-Za-z0-9._-]{0,63}$/.test(route.name)) failures.push(`invalid route name in ${themeName}`)
    if (typeof route.path !== 'string' || !route.path.startsWith('/')) failures.push(`invalid route path for ${route.name ?? '?'} in ${themeName}`)
    if (routeNames.has(route.name)) failures.push(`duplicate route name ${route.name}`)
    else routeNames.set(route.name, themeName)
    if (routePaths.has(route.path)) failures.push(`duplicate route path ${route.path}`)
    else routePaths.set(route.path, themeName)

    const routeModule = route.module
    if (routeModule !== undefined && (typeof routeModule !== 'string' || !moduleNames.has(routeModule))) {
      failures.push(`route ${route.name ?? '?'} references unknown module: ${String(routeModule)}`)
    }

    if (!route.redirect) {
      const view = typeof route.view === 'string' ? viewNames.get(route.view) : undefined
      if (!view) failures.push(`route ${route.name} references missing engine view ${String(route.view)}`)
      else {
        referencedViews.add(route.view)
        if (view.owner && routeModule !== view.owner) {
          failures.push(`route ${route.name} must declare module ${view.owner} for view ${route.view}`)
        }
        if (!view.owner && routeModule !== undefined) {
          failures.push(`route ${route.name} declares module ${routeModule} for core view ${route.view}`)
        }
      }
    }
  }
  navigation.push(...(frontendManifest.navigation ?? []))
  legacy.push(...(frontendManifest.legacy ?? []))
  for (const capability of frontendManifest.capabilities ?? []) {
    if (typeof capability === 'string') continue
    if (!capability || typeof capability !== 'object' || typeof capability.name !== 'string') {
      failures.push('invalid theme capability entry')
      continue
    }
    if (typeof capability.module !== 'string' || !moduleNames.has(capability.module)) {
      failures.push(`capability ${capability.name} references unknown module: ${String(capability.module)}`)
    }
  }
}

for (const item of navigation) {
  if (!item || typeof item !== 'object') { failures.push('non-object navigation item'); continue }
  if (item.module !== undefined && (typeof item.module !== 'string' || !moduleNames.has(item.module))) {
    failures.push(`navigation references unknown module: ${String(item.module)}`)
  }
  if (!item.action && !routeNames.has(item.route)) failures.push(`navigation references unknown route: ${String(item.route)}`)
}
for (const alias of legacy) {
  if (!alias || typeof alias !== 'object' || !routeNames.has(alias.route)) failures.push(`legacy alias references unknown route: ${String(alias?.route)}`)
  if (alias?.module !== undefined && (typeof alias.module !== 'string' || !moduleNames.has(alias.module))) {
    failures.push(`legacy alias references unknown module: ${String(alias.module)}`)
  }
}
for (const [view, descriptor] of viewNames) {
  if (!referencedViews.has(view)) failures.push(`engine client view is not registered by the selected theme: ${descriptor.path}`)
}

for (const forbidden of ['api', 'app', 'content', 'router', 'views']) {
  if (await exists(join(sourceRoot, forbidden))) failures.push(`theme contains engine functionality directory: src/${forbidden}`)
}
async function scanTheme(directory) {
  for (const entry of await readdir(directory, { withFileTypes: true })) {
    const path = join(directory, entry.name)
    if (entry.isDirectory()) { await scanTheme(path); continue }
    if (!['.ts', '.vue'].includes(extname(entry.name))) continue
    const text = await readFile(path, 'utf8')
    for (const signature of ['foxesApi', 'userAction:', 'sysRequest:', 'admPanel:', 'user_doaction:', 'fetch(']) {
      if (text.includes(signature)) failures.push(`theme contains business/runtime contract ${signature}: ${relative(repositoryRoot, path).replaceAll('\\', '/')}`)
    }
  }
}
await scanTheme(sourceRoot)
const requiredLegacyTemplates = [
  'src/Main.vue',
  'src/Header.vue',
  'src/Logo.vue',
  'src/UserBlock.vue',
  'src/Footer.vue',
  'src/Slider.vue',
  'src/RightBlock.vue',
  'src/CookiePopup.vue',
  'src/ButtonUp.vue',
  'src/userOptions/Article.vue',
  'src/userOptions/PlayerTop.vue',
  'src/userOptions/content/Welcome.vue',
  'src/userOptions/content/Rules.vue',
  'src/userOptions/content/StaticContent.vue',
  'src/userOptions/content/StaticPage.vue',
  'src/userOptions/content/PrivacyPolicy.vue',
  'src/userOptions/content/Cookies.vue',
  'src/userOptions/content/VerifiedLibs.vue',
  'src/userOptions/content/UnVerifiedLibs.vue',
  'src/userOptions/content/NewAge.vue',
  'src/userOptions/content/guest/Auth.vue',
  'src/userOptions/content/guest/Reg.vue',
  'src/userOptions/content/guest/LostPassword.vue',
  'src/userOptions/content/guest/PassReset.vue',
  'src/userOptions/pages/Info.vue',
  'src/userOptions/pages/SaveDiscord.vue',
  'src/userOptions/pages/StartGame.vue',
  'src/userOptions/pages/UpcomingUpdates.vue',
  'src/userOptions/pages/badges/Badge.vue',
  'src/userOptions/userOptions/Profile.vue',
  'src/userOptions/userOptions/ProfileSettings.vue',
  'src/userOptions/userOptions/SkinSettings.vue',
  'src/userOptions/userOptions/AdminPanel.vue',
  'src/userOptions/userOptions/profile/ProfileHeader.vue',
  'src/userOptions/userOptions/profile/ProfileFacts.vue',
  'src/userOptions/userOptions/profile/ProfileInfo.vue',
  'src/userOptions/userOptions/profile/ProfileBadges.vue',
  'src/userOptions/userOptions/profile/ProfileDataSection.vue',
  'src/userOptions/userOptions/profile/options/ProfileOption.vue',
  'src/userOptions/userOptions/profile/options/AppearanceOption.vue',
  'src/userOptions/userOptions/profile/options/SecurityOption.vue',
  'src/userOptions/userOptions/profile/options/SkinOption.vue',
  'src/userOptions/userOptions/profile/options/CloakOption.vue',
  'src/userOptions/userOptions/profile/options/SkinPreview.vue',
  'src/foxEngine/admin/Overview.vue',
  'src/foxEngine/admin/Users.vue',
  'src/foxEngine/admin/users/UserTable.vue',
  'src/foxEngine/admin/users/UserEditor.vue',
  'src/foxEngine/admin/Servers.vue',
  'src/foxEngine/admin/servers/ServerTable.vue',
  'src/foxEngine/admin/servers/ServerEditor.vue',
  'src/foxEngine/admin/Logs.vue',
  'src/foxEngine/admin/Catalogs.vue',
  'src/foxEngine/ArtworkShowcase.vue',
  'src/foxEngine/Payment.vue',
  'src/foxEngine/LastUser.vue',
  'src/foxEngine/monitor/Monitoring.vue',
  'src/foxEngine/monitor/ServerEntry.vue',
  'src/foxEngine/monitor/TotalOnline.vue',
  'src/foxEngine/serverPage/ServerPage.vue',
  'src/foxEngine/serverPage/ServerMods.vue',
  'src/foxEngine/userTop/playTime/PlayerCell.vue',
]
for (const relativePath of requiredLegacyTemplates) {
  if (!(await exists(join(themeRoot, relativePath)))) failures.push(`legacy-style theme template is missing: ${relativePath}`)
}
if (themeManifest?.configuration !== undefined) {
  failures.push('theme page content must not be stored in theme.json configuration files')
}
if (await exists(join(themeRoot, 'data', 'pages'))) {
  failures.push('theme page content JSON directory must not exist: data/pages')
}
for (const forbiddenPath of ['src/pages', 'src/components']) {
  if (await exists(join(themeRoot, forbiddenPath))) {
    failures.push(`generic presentation bucket must not exist; use legacy-style template paths: ${forbiddenPath}`)
  }
}

const delegatedControllers = new Map([
  [join(engineClientRoot, 'views', 'HomeView.vue'), "@theme/userOptions/content/Welcome.vue"],
  [join(engineClientRoot, 'views', 'AboutView.vue'), "@theme/userOptions/pages/Info.vue"],
  [join(engineClientRoot, 'views', 'BadgeView.vue'), "@theme/userOptions/pages/badges/Badge.vue"],
  [join(engineClientRoot, 'views', 'RulesView.vue'), "@theme/userOptions/content/Rules.vue"],
  [join(engineClientRoot, 'views', 'StaticContentView.vue'), "@theme/userOptions/content/StaticContent.vue"],
  [join(engineClientRoot, 'views', 'DiscordAccessView.vue'), "@theme/userOptions/pages/SaveDiscord.vue"],
  [join(engineClientRoot, 'views', 'FundingView.vue'), "@theme/foxEngine/Payment.vue"],
  [join(engineClientRoot, 'views', 'GuideDraftView.vue'), "@theme/userOptions/Article.vue"],
  [join(modulesRoot, 'AuthReg', 'client', 'views', 'AuthView.vue'), "@theme/userOptions/content/guest/Auth.vue"],
  [join(modulesRoot, 'AuthReg', 'client', 'views', 'RegisterView.vue'), "@theme/userOptions/content/guest/Reg.vue"],
  [join(modulesRoot, 'AuthReg', 'client', 'views', 'LostPasswordView.vue'), "@theme/userOptions/content/guest/LostPassword.vue"],
  [join(modulesRoot, 'AuthReg', 'client', 'views', 'ResetPasswordView.vue'), "@theme/userOptions/content/guest/PassReset.vue"],
  [join(modulesRoot, 'GameScanner', 'client', 'views', 'StartGameView.vue'), "@theme/userOptions/pages/StartGame.vue"],
  [join(modulesRoot, 'GameScanner', 'client', 'views', 'ServerView.vue'), "@theme/foxEngine/serverPage/ServerPage.vue"],
  [join(modulesRoot, 'UserTop', 'client', 'views', 'PlayersView.vue'), "@theme/userOptions/PlayerTop.vue"],
  [join(modulesRoot, 'UserSettings', 'client', 'views', 'ProfileView.vue'), "@theme/userOptions/userOptions/Profile.vue"],
  [join(modulesRoot, 'UserSettings', 'client', 'views', 'ProfileSettingsView.vue'), "@theme/userOptions/userOptions/ProfileSettings.vue"],
  [join(modulesRoot, 'UserSettings', 'client', 'views', 'SkinSettingsView.vue'), "@theme/userOptions/userOptions/SkinSettings.vue"],
  [join(modulesRoot, 'AdminPanel', 'client', 'views', 'AdminView.vue'), "@theme/userOptions/userOptions/AdminPanel.vue"],
])
for (const [controllerPath, templateImport] of delegatedControllers) {
  const controller = await readFile(controllerPath, 'utf8')
  if (!controller.includes(templateImport)) {
    failures.push(`engine controller does not delegate HTML to ${templateImport}: ${relative(repositoryRoot, controllerPath).replaceAll('\\', '/')}`)
  }
}

for (const removedPresentation of [
  join(engineClientRoot, 'components', 'PageLayout.vue'),
  join(engineClientRoot, 'components', 'ArtworkShowcase.vue'),
  join(engineClientRoot, 'components', 'HeroSection.vue'),
  join(engineClientRoot, 'components', 'SiteSidebar.vue'),
  join(engineClientRoot, 'components', 'ServerMonitor.vue'),
  join(engineClientRoot, 'components', 'LastUserCard.vue'),
]) {
  if (await exists(removedPresentation)) {
    failures.push(`engine presentation component must remain theme-owned: ${relative(repositoryRoot, removedPresentation).replaceAll('\\', '/')}`)
  }
}

const routerText = await readFile(join(engineClientRoot, 'router', 'index.ts'), 'utf8')
if (!routerText.includes('import.meta.glob')) failures.push('engine router does not discover views dynamically')

if (failures.length) {
  console.error('Frontend registry architecture failed:')
  for (const failure of [...new Set(failures)]) console.error(`- ${failure}`)
  process.exit(1)
}
console.log(`Frontend registry passed: theme ${themeName} owns ${routeNames.size} routes for ${viewNames.size} engine/module views; backend modules own no route manifests.`)
