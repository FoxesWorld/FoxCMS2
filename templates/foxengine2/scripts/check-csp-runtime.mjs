import { spawnSync } from 'node:child_process'
import { access, readFile, readdir } from 'node:fs/promises'
import { basename, dirname, join, resolve } from 'node:path'
import { fileURLToPath, pathToFileURL } from 'node:url'
import { repositoryRoot, themeRoot } from './theme-paths.mjs'

const failures = []
const runtimeDirectory = join(themeRoot, 'assets', 'runtime')
const modulesDirectory = join(runtimeDirectory, 'templates')
const bridgePath = join(runtimeDirectory, 'vue-runtime.js')
const standaloneCompilerPath = join(runtimeDirectory, 'server', 'runtime-template-compiler.mjs')
const themeName = basename(themeRoot)
const bridgeUrl = `/templates/${themeName}/assets/runtime/vue-runtime.js`
const definitions = [
  ['profile-settings', 'userOptions/ProfileSettings.tpl'],
  ['admin-panel', 'userOptions/AdminPanel.tpl'],
  ['static-content', 'pages/templates/StaticContent.tpl'],
  ['start-game', 'pages/templates/StartGame.tpl'],
  ['badges', 'pages/templates/Badges.tpl'],
  ['badge', 'pages/templates/Badge.tpl'],
  ['achievements', 'pages/templates/Achievements.tpl'],
  ['achievement-statistics', 'pages/templates/achievements/StatisticsTree.tpl'],
  ['achievement-tree-node', 'pages/templates/achievements/TreeNode.tpl'],
  ['achievement-profile-panel', 'pages/templates/achievements/ProfilePanel.tpl'],
]

const readRepository = (path) => readFile(resolve(repositoryRoot, path), 'utf8')
const security = await readRepository('engine/classes/http/SecurityHeaders.class.php')
const runtimeHost = await readRepository('engine/client/runtime/RuntimeTpl.vue')
const pageStore = await readRepository('engine/client/runtime/pageTemplates.ts')
const optionsStore = await readRepository('engine/client/runtime/userOptions.ts')
const compiler = await readRepository('engine/classes/themes/ThemeRuntimeTplCompiler.class.php')
const pageRepository = await readRepository('engine/classes/themes/ThemePageTemplateRepository.class.php')
const optionsRepository = await readRepository('engine/classes/themes/ThemeUserOptionsRepository.class.php')
const vite = await readFile(join(themeRoot, 'vite.config.ts'), 'utf8')

for (const token of [
  "$hcaptchaSources = ['https://hcaptcha.com', 'https://*.hcaptcha.com']",
  "\"script-src 'self' \" . implode(' ', $hcaptchaSources)",
  "\"style-src 'self' \" . implode(' ', $hcaptchaSources)",
  "'frame-src ' . implode(' ', $hcaptchaSources)",
  "'connect-src ' . implode(' ', $connectSources)",
]) {
  if (!security.includes(token)) failures.push(`CSP hCaptcha allowlist is missing ${token}`)
}
if (security.includes('unsafe-eval') || security.includes('unsafe-inline')) failures.push('CSP must not be weakened for runtime templates')
if (security.includes("script-src https:") || security.includes("frame-src https:") || security.includes("connect-src https:")) {
  failures.push('CSP must not allow arbitrary HTTPS origins for hCaptcha')
}
if (!vite.includes('vue.runtime.esm-bundler.js')) failures.push('Vite must use the runtime-only Vue build')
if (vite.includes("vue.esm-bundler.js'")) failures.push('Vite still references the browser template compiler build')
for (const token of [
  'import(/* @vite-ignore */ moduleUrl)',
  'render: loaded.render',
  'loaded.templateId !== templateId',
]) {
  if (!runtimeHost.includes(token)) failures.push(`RuntimeTpl is missing ${token}`)
}
for (const token of ['template: source', 'new Function', 'eval(']) {
  if (runtimeHost.includes(token)) failures.push(`RuntimeTpl contains forbidden browser compilation token: ${token}`)
}
for (const source of [pageStore, optionsStore]) {
  if (!source.includes('/assets\\/runtime\\/templates\\/')) failures.push('runtime registry does not strictly validate module URLs')
}
for (const token of ['proc_open(', "['bypass_shell' => true]", 'TIMEOUT_SECONDS', 'validateModule(', 'publish(string $id', "'assets/runtime/server'"]) {
  if (!compiler.includes(token)) failures.push(`server runtime compiler is missing ${token}`)
}
for (const [label, source] of [['page repository', pageRepository], ['userOptions repository', optionsRepository]]) {
  const compileIndex = source.indexOf('$this->compiler->publish')
  const writeIndex = source.indexOf(label === 'page repository' ? 'ThemeRuntimeTplDocument::write' : '$this->write(')
  if (compileIndex < 0 || writeIndex < 0 || compileIndex > writeIndex) {
    failures.push(`${label} must publish the immutable render module before switching the TPL revision`)
  }
}
if (!pageRepository.includes("if (!$includeSource) unset($template['html']);")) {
  failures.push('public page registry must omit raw HTML bodies')
}
if (!optionsRepository.includes("$result['html'] = $template['html'];")
  || !optionsRepository.includes('if ($includeSource)')) {
  failures.push('userOptions HTML/source must be admin-only runtime data')
}

const requiredHelpers = new Set()
try {
  const standaloneCompiler = await readFile(standaloneCompilerPath, 'utf8')
  if (standaloneCompiler.includes('from"@vue/') || standaloneCompiler.includes("from '@vue/")
    || standaloneCompiler.includes('node_modules')) {
    failures.push('standalone runtime TPL compiler still depends on deployed node_modules')
  }
  const compilerProbe = spawnSync(
    process.execPath,
    [standaloneCompilerPath, '--id', 'contract-probe', '--bridge-url', bridgeUrl],
    { input: '<div>{{ value }}</div>', encoding: 'utf8', cwd: dirname(standaloneCompilerPath) },
  )
  if (compilerProbe.status !== 0 || !compilerProbe.stdout.includes('export function render(')
    || !compilerProbe.stdout.includes('export const templateId = "contract-probe"')) {
    failures.push(`standalone runtime TPL compiler probe failed: ${compilerProbe.stderr.trim()}`)
  }

  await access(bridgePath)
  const bridgeModule = await import(`${pathToFileURL(bridgePath).href}?csp-check=${Date.now()}`)
  const runtimeEntries = [join(runtimeDirectory, 'theme.js'), join(runtimeDirectory, 'vue-runtime.js')]
  const chunksDirectory = join(runtimeDirectory, 'chunks')
  const chunks = (await readdir(chunksDirectory)).filter((name) => name.endsWith('.js'))
  runtimeEntries.push(...chunks.map((name) => join(chunksDirectory, name)))

  for (const [id, relativeFile] of definitions) {
    const source = await readFile(join(themeRoot, relativeFile), 'utf8')
    const revision = Number(source.match(/\brevision="(\d+)"/u)?.[1] ?? 1)
    const modulePath = join(modulesDirectory, `${id}.${revision}.js`)
    const module = await readFile(modulePath, 'utf8')
    const importMatch = module.match(new RegExp(`import \\{([^}]*)\\} from "${bridgeUrl.replaceAll('/', '\\/')}"`, 'u'))
    if (!importMatch) failures.push(`${id} module does not import the stable same-origin Vue bridge`)
    for (const binding of importMatch?.[1]?.split(',') ?? []) {
      const imported = binding.trim().split(/\s+as\s+/u)[0]
      if (imported) requiredHelpers.add(imported)
    }
    for (const token of ['export function render(', `export const templateId = "${id}"`, 'export const sourceHash = ']) {
      if (!module.includes(token)) failures.push(`${id} module is missing ${token}`)
    }
    if (module.includes('new Function') || module.includes('eval(') || module.includes('<fox-template-body')) {
      failures.push(`${id} derivative render module violates the CSP/cache boundary`)
    }
  }

  for (const helper of requiredHelpers) {
    if (!(helper in bridgeModule)) failures.push(`vue-runtime.js does not export required helper ${helper}`)
  }

  const allRuntimeFiles = [...runtimeEntries, ...(await readdir(modulesDirectory))
    .filter((name) => name.endsWith('.js')).map((name) => join(modulesDirectory, name))]
  for (const path of allRuntimeFiles) {
    const source = await readFile(path, 'utf8')
    if (source.includes('new Function') || source.includes('eval(')) {
      failures.push(`${path.slice(runtimeDirectory.length + 1)} contains eval-like execution`)
    }
  }
} catch (error) {
  failures.push(`CSP runtime artifacts are unavailable: ${error.message}`)
}

if (failures.length) {
  console.error('CSP runtime contract failed:')
  for (const failure of [...new Set(failures)]) console.error(`- ${failure}`)
  process.exit(1)
}
console.log(`CSP runtime contract passed: ${definitions.length} runtime-editable TPL sources use revisioned same-origin render caches, ${requiredHelpers.size} Vue helpers are exported, and no production JavaScript uses eval.`)
