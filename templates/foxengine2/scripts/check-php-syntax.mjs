import { spawnSync } from 'node:child_process'
import { readFile, readdir } from 'node:fs/promises'
import { homedir } from 'node:os'
import { extname, join, relative } from 'node:path'
import { fileURLToPath } from 'node:url'
import pkg from 'php-parser'

const repositoryRoot = fileURLToPath(new URL('../../..', import.meta.url))
const ignored = new Set([
  '.git',
  'node_modules',
  'templates_c',
  'cache',
  'dist',
  'build',
  'vendor',
])
const parser = new pkg.Engine({
  parser: { extractDoc: true, suppressErrors: false },
  ast: { withPositions: false },
})
const files = []

async function isPhpSource(path, name) {
  if (extname(name).toLowerCase() === '.php') return true
  if (extname(name) !== '') return false

  try {
    const prefix = (await readFile(path, 'utf8')).slice(0, 256)
    return /^\s*<\?php\b/u.test(prefix)
  } catch {
    return false
  }
}

async function walk(directory) {
  for (const entry of await readdir(directory, { withFileTypes: true })) {
    if (entry.isDirectory() && ignored.has(entry.name)) continue

    const path = join(directory, entry.name)
    if (entry.isDirectory()) {
      await walk(path)
      continue
    }

    if (await isPhpSource(path, entry.name)) files.push(path)
  }
}

async function phpExecutableFromManifest(path) {
  try {
    const manifest = JSON.parse(await readFile(path, 'utf8'))
    const executable = manifest?.windows?.executable
    return typeof executable === 'string' && executable.trim() !== ''
      ? executable
      : null
  } catch {
    return null
  }
}

async function resolvePhpCli() {
  const documents = join(homedir(), 'Documents')
  const manifestCandidates = [
    process.env.PHP_TOOL_MANIFEST,
    join(documents, 'Repos', 'SUITE', 'SpringSuite', 'tools', 'third-party', 'php', 'tool.json'),
    join(documents, 'Repos', 'SUITE', 'SpringSuite', 'build', 'deploy4', 'tools', 'third-party', 'php', 'tool.json'),
    join(documents, 'Take Some', 'NorthStar-Suite-V3', 'tools', 'third-party', 'php', 'tool.json'),
  ].filter(Boolean)

  const executableCandidates = [
    process.env.PHP_BINARY,
    process.env.PHP_PATH,
    join(documents, 'Take Some', 'Tools', 'toolbelt', 'third_party', 'php', 'php.exe'),
  ].filter(Boolean)

  for (const manifestPath of manifestCandidates) {
    const executable = await phpExecutableFromManifest(manifestPath)
    if (executable) executableCandidates.push(executable)
  }
  executableCandidates.push('php')

  for (const executable of [...new Set(executableCandidates)]) {
    const probe = spawnSync(executable, ['--version'], {
      encoding: 'utf8',
      windowsHide: true,
    })
    if (!probe.error && probe.status === 0) {
      const version = probe.stdout.split(/\r?\n/u)[0]?.trim() || 'PHP CLI'
      return { executable, version }
    }
  }

  return null
}

function nativeLint(phpCli) {
  const failures = []
  for (const path of files) {
    const result = spawnSync(phpCli.executable, ['-l', path], {
      encoding: 'utf8',
      windowsHide: true,
    })
    if (result.error || result.status !== 0) {
      const output = [result.stdout, result.stderr, result.error?.message]
        .filter(Boolean)
        .join('\n')
        .trim()
      failures.push(`${relative(repositoryRoot, path)}: ${output || 'Unknown PHP lint failure.'}`)
    }
  }

  if (failures.length) {
    console.error(`Native PHP syntax check failed in ${failures.length} file(s):`)
    for (const failure of failures) console.error(`- ${failure}`)
    process.exit(1)
  }

  console.log(`PHP native syntax passed: ${files.length} files linted with ${phpCli.version}.`)
}

async function astLint() {
  const failures = []
  for (const path of files) {
    try {
      parser.parseCode(await readFile(path, 'utf8'), relative(repositoryRoot, path))
    } catch (error) {
      failures.push(`${relative(repositoryRoot, path)}: ${error.message}`)
    }
  }

  if (failures.length) {
    console.error(`PHP AST syntax check failed in ${failures.length} file(s):`)
    for (const failure of failures) console.error(`- ${failure}`)
    process.exit(1)
  }

  console.log(`PHP AST syntax passed: ${files.length} files parsed.`)
}

await walk(repositoryRoot)
files.sort((left, right) => left.localeCompare(right))

const phpCli = await resolvePhpCli()
if (phpCli) {
  nativeLint(phpCli)
} else {
  console.warn('PHP CLI was not found; native lint was skipped. Set PHP_BINARY or PHP_TOOL_MANIFEST to enable it.')
}
await astLint()
