import { readFile, readdir } from 'node:fs/promises'
import { extname, join, relative } from 'node:path'
import { fileURLToPath } from 'node:url'
import pkg from 'php-parser'

const repositoryRoot = fileURLToPath(new URL('../../..', import.meta.url))
const ignored = new Set(['.git', 'node_modules', 'templates_c', 'cache'])
const parser = new pkg.Engine({
  parser: { extractDoc: true, suppressErrors: false },
  ast: { withPositions: false },
})
const files = []
const failures = []

async function walk(directory) {
  for (const entry of await readdir(directory, { withFileTypes: true })) {
    if (entry.isDirectory() && ignored.has(entry.name)) continue
    const path = join(directory, entry.name)
    if (entry.isDirectory()) { await walk(path); continue }
    if (extname(entry.name).toLowerCase() === '.php') files.push(path)
  }
}

await walk(repositoryRoot)
for (const path of files) {
  try {
    parser.parseCode(await readFile(path, 'utf8'), relative(repositoryRoot, path))
  } catch (error) {
    failures.push(`${relative(repositoryRoot, path)}: ${error.message}`)
  }
}

if (failures.length) {
  console.error(`PHP syntax check failed in ${failures.length} file(s):`)
  for (const failure of failures) console.error(`- ${failure}`)
  process.exit(1)
}
console.log(`PHP syntax passed: ${files.length} files parsed.`)
