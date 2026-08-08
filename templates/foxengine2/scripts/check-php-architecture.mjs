import { access, readFile, readdir } from 'node:fs/promises'
import { extname, join, relative } from 'node:path'
import { fileURLToPath } from 'node:url'
import pkg from 'php-parser'

const repositoryRoot = fileURLToPath(new URL('../../..', import.meta.url))
const engineRoot = join(repositoryRoot, 'engine')
const utilsRoot = join(engineRoot, 'classes', 'utils')
const modulesRoot = join(engineRoot, 'classes', 'modules')
const manifestPath = join(engineRoot, 'data', 'modules.json')
const ignored = new Set(['.git', 'node_modules', 'templates_c', 'cache'])
const parser = new pkg.Engine({ parser: { suppressErrors: false }, ast: { withPositions: false } })
const phpFiles = []
const declarations = new Map()
const failures = []

async function walk(directory) {
  for (const entry of await readdir(directory, { withFileTypes: true })) {
    if (entry.isDirectory() && ignored.has(entry.name)) continue
    const path = join(directory, entry.name)
    if (entry.isDirectory()) {
      await walk(path)
      continue
    }
    if (extname(entry.name).toLowerCase() === '.php') phpFiles.push(path)
  }
}

function collectDeclarations(node, namespace, file) {
  if (!node || typeof node !== 'object') return
  let nextNamespace = namespace
  if (node.kind === 'namespace') nextNamespace = node.name || ''

  if (['class', 'interface', 'trait', 'enum'].includes(node.kind) && node.name) {
    const rawName = typeof node.name === 'string' ? node.name : node.name.name
    const fqcn = `${nextNamespace ? `${nextNamespace}\\` : ''}${rawName}`.toLowerCase()
    const existing = declarations.get(fqcn)
    if (existing && existing !== file) failures.push(`duplicate PHP declaration ${fqcn}: ${existing} and ${file}`)
    else declarations.set(fqcn, file)
  }

  for (const value of Object.values(node)) {
    if (Array.isArray(value)) {
      for (const child of value) collectDeclarations(child, nextNamespace, file)
    } else if (value && typeof value === 'object') {
      collectDeclarations(value, nextNamespace, file)
    }
  }
}

await walk(repositoryRoot)
for (const path of phpFiles) {
  const rel = relative(repositoryRoot, path).replaceAll('\\', '/')
  collectDeclarations(parser.parseCode(await readFile(path, 'utf8'), rel), '', rel)
}

const utilityFamilies = await readdir(utilsRoot, { withFileTypes: true })
const utilityCall = /UtilityLoader::load\(\s*['"]([^'"]+)['"]\s*,\s*['"]([^'"]+)['"]/g
for (const path of phpFiles) {
  const text = await readFile(path, 'utf8')
  for (const match of text.matchAll(utilityCall)) {
    const family = utilityFamilies.find((entry) => entry.isDirectory() && entry.name.toLowerCase() === match[1].toLowerCase())
    if (!family) {
      failures.push(`missing utility family ${match[1]} referenced by ${relative(repositoryRoot, path)}`)
      continue
    }
    try {
      await access(join(utilsRoot, family.name, match[2], `${family.name}.class.php`))
    } catch {
      failures.push(`missing utility ${family.name}@${match[2]} referenced by ${relative(repositoryRoot, path)}`)
    }
  }
}

let manifest = []
try {
  manifest = JSON.parse(await readFile(manifestPath, 'utf8'))
} catch (error) {
  failures.push(`module manifest is invalid: ${error.message}`)
}

if (!Array.isArray(manifest)) {
  failures.push('module manifest must be a JSON array')
  manifest = []
}

const knownPriorities = new Set(['preInit', 'primary', 'secondary'])
const manifestNames = new Set()
for (const module of manifest) {
  if (!module || typeof module !== 'object') {
    failures.push('module manifest contains a non-object entry')
    continue
  }

  const { name, main, class: moduleClass, priority, groups } = module
  if (typeof name !== 'string' || !/^[A-Za-z][A-Za-z0-9]*$/.test(name)) {
    failures.push(`invalid module name: ${String(name)}`)
    continue
  }
  if (manifestNames.has(name)) failures.push(`duplicate module manifest entry: ${name}`)
  manifestNames.add(name)

  if (typeof main !== 'string' || !/^[A-Za-z0-9_.-]+\.php$/.test(main)) {
    failures.push(`invalid module entrypoint for ${name}: ${String(main)}`)
  }
  if (moduleClass !== null && (typeof moduleClass !== 'string' || !/^[A-Za-z_][A-Za-z0-9_\\]*$/.test(moduleClass))) {
    failures.push(`invalid module class for ${name}: ${String(moduleClass)}`)
  }
  if (!knownPriorities.has(priority)) failures.push(`invalid module priority for ${name}: ${String(priority)}`)
  if (groups !== null && groups !== '*' && (!Array.isArray(groups) || groups.some((group) => typeof group !== 'string' || !/^[a-z][a-z0-9_-]{0,63}$/.test(group)))) {
    failures.push(`invalid groups contract for ${name}`)
  }

  const entrypoint = join(modulesRoot, name, main)
  const entrypointRel = relative(repositoryRoot, entrypoint).replaceAll('\\', '/')
  try {
    await access(entrypoint)
  } catch {
    failures.push(`module entrypoint is missing: ${name}/${main}`)
  }

  if (typeof moduleClass === 'string') {
    const declarationFile = declarations.get(moduleClass.toLowerCase())
    if (!declarationFile) failures.push(`module class is not declared: ${name} -> ${moduleClass}`)
    else if (declarationFile !== entrypointRel) failures.push(`module class ${moduleClass} must be declared by ${entrypointRel}, found in ${declarationFile}`)
  }
}

for (const entry of await readdir(modulesRoot, { withFileTypes: true })) {
  if (!entry.isDirectory()) continue
  if (!manifestNames.has(entry.name)) failures.push(`module directory is absent from manifest: ${entry.name}`)
  try {
    await access(join(modulesRoot, entry.name, 'incOptions.json'))
    failures.push(`legacy incOptions.json remains in module: ${entry.name}`)
  } catch {
    // Expected: JSON manifest is the only source of truth.
  }
}

const architectureFiles = {
  systemFacade: await readFile(join(repositoryRoot, 'engine', 'SystemRequests.class.php'), 'utf8'),
  adminFacade: await readFile(join(repositoryRoot, 'engine', 'classes', 'modules', 'AdminPanel', 'AdminOptions.class.php'), 'utf8'),
  actionDispatcher: await readFile(join(repositoryRoot, 'src', 'FoxCMS', 'Shared', 'Routing', 'ActionDispatcher.php'), 'utf8'),
  apiExecutionBoundary: await readFile(join(repositoryRoot, 'api', 'src', 'FoxCMS', 'Api', 'Core', 'ApiExecutionBoundary.php'), 'utf8'),
}
const sourceLines = (source) => source.split(/\r?\n/).length
if (sourceLines(architectureFiles.systemFacade) > 180) {
  failures.push('SystemRequests compatibility facade exceeded 180 lines; move domain behavior into FoxCMS\\Engine\\System handlers')
}
if (sourceLines(architectureFiles.adminFacade) > 120) {
  failures.push('AdminOptions compatibility facade exceeded 120 lines; move use-cases into FoxCMS\\Engine\\Admin handlers')
}
for (const token of ['SystemRequestRouterFactory', 'ActionDispatcher', '$this->router->dispatch($action)']) {
  if (!architectureFiles.systemFacade.includes(token)) failures.push(`SystemRequests facade is missing ${token}`)
}
for (const token of ['new UploadService(', 'new \\ServerParser(', 'new \\GetJre(']) {
  if (architectureFiles.systemFacade.includes(token)) failures.push(`SystemRequests facade regained domain responsibility: ${token}`)
}
for (const token of ['AdminActionRouterFactory', 'ActionDispatcher', '$this->router->dispatch($action)']) {
  if (!architectureFiles.adminFacade.includes(token)) failures.push(`AdminOptions facade is missing ${token}`)
}
for (const token of ['ACTION_HANDLERS', 'new UploadService(', 'ThemeSlidesRepository', 'MaintenanceModeRepository']) {
  if (architectureFiles.adminFacade.includes(token)) failures.push(`AdminOptions facade regained composition/domain responsibility: ${token}`)
}
for (const token of ['final class ActionDispatcher', 'Closure::fromCallable(', 'Duplicate action registration:', 'public function dispatch(']) {
  if (!architectureFiles.actionDispatcher.includes(token)) failures.push(`Shared action dispatcher is missing ${token}`)
}
for (const token of ['final class ApiExecutionBoundary', 'catch (HttpException $error)', 'catch (Throwable $error)', 'FatalResponse::send(', 'RequestId::create()']) {
  if (!architectureFiles.apiExecutionBoundary.includes(token)) failures.push(`Shared API execution boundary is missing ${token}`)
}

if (failures.length) {
  console.error('PHP architecture check failed:')
  for (const failure of [...new Set(failures)]) console.error(`- ${failure}`)
  process.exit(1)
}

console.log(`PHP architecture passed: ${declarations.size} declarations, ${manifest.length} manifest modules, all utility and entrypoint contracts resolved.`)
