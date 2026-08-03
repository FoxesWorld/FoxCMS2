import { readdir, readFile } from 'node:fs/promises'
import { extname, relative } from 'node:path'
import { TextDecoder } from 'node:util'
import { repositoryRoot } from './theme-paths.mjs'

const ignoredDirectories = new Set(['.git', 'node_modules', '.vite'])
const textExtensions = new Set([
  '.php', '.js', '.mjs', '.cjs', '.ts', '.tsx', '.vue', '.css', '.scss', '.less',
  '.html', '.htm', '.sql', '.json', '.xml', '.yml', '.yaml', '.md', '.txt', '.ini',
  '.conf', '.env', '.properties', '.toml', '.sh', '.bat', '.cmd', '.ps1', '.csv',
])
const textNames = new Set(['.editorconfig', '.gitattributes', '.gitignore'])
const utf8 = new TextDecoder('utf-8', { fatal: true })
const forbiddenEncodingNames = [
  Buffer.from('637031323531', 'hex').toString('ascii'),
  Buffer.from('77696e646f77732d31323531', 'hex').toString('ascii'),
  Buffer.from('77696e31323531', 'hex').toString('ascii'),
]
const suspiciousMojibake = /(?:(?:\u0420|\u0421)[\u0401-\u040f\u0451-\u045f\u0080-\u00ff\u2010-\u203a]){2,}|\u0432\u0402[\u0080-\u203a]/u
const failures = []
let checked = 0

async function walk(directory) {
  const entries = await readdir(directory, { withFileTypes: true })
  for (const entry of entries) {
    if (entry.isDirectory() && ignoredDirectories.has(entry.name)) continue
    const path = `${directory}/${entry.name}`
    if (entry.isDirectory()) {
      await walk(path)
      continue
    }
    if (!entry.isFile()) continue
    if (!textExtensions.has(extname(entry.name).toLowerCase()) && !textNames.has(entry.name)) continue
    await checkFile(path)
  }
}

async function checkFile(path) {
  const bytes = await readFile(path)
  const name = relative(repositoryRoot, path).replaceAll('\\', '/')
  checked += 1
  if (bytes.length >= 3 && bytes[0] === 0xef && bytes[1] === 0xbb && bytes[2] === 0xbf) {
    failures.push(`${name}: UTF-8 BOM is forbidden`)
  }
  let text
  try {
    text = utf8.decode(bytes)
  } catch {
    failures.push(`${name}: file is not valid UTF-8`)
    return
  }
  if (text.includes('\uFFFD')) failures.push(`${name}: contains Unicode replacement characters`)
  if (text.includes('\0')) failures.push(`${name}: contains NUL characters`)
  const lower = text.toLowerCase()
  if (forbiddenEncodingNames.some((encoding) => lower.includes(encoding))) {
    failures.push(`${name}: contains a forbidden legacy Cyrillic encoding reference`)
  }
  const lines = text.split(/\r?\n/u)
  for (let index = 0; index < lines.length; index += 1) {
    if (suspiciousMojibake.test(lines[index])) {
      failures.push(`${name}:${index + 1}: contains probable double-decoded Cyrillic text`)
      if (failures.length >= 100) return
    }
  }
}

await walk(repositoryRoot.replaceAll('\\', '/'))
if (failures.length) {
  console.error('UTF-8 contract failed:')
  for (const failure of failures) console.error(`- ${failure}`)
  process.exit(1)
}
console.log(`UTF-8 contract passed: ${checked} text files are valid UTF-8 without BOM or mojibake.`)
