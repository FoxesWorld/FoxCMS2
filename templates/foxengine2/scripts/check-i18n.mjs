import { readFile, readdir } from 'node:fs/promises'
import { dirname, extname, relative, resolve, sep } from 'node:path'
import { fileURLToPath } from 'node:url'
import ts from 'typescript'
import { parse as parseSfc } from '@vue/compiler-sfc'
import { baseParse, NodeTypes, parserOptions } from '@vue/compiler-dom'

const themeRoot = resolve(dirname(fileURLToPath(import.meta.url)), '..')
const repositoryRoot = resolve(themeRoot, '..', '..')
const localePath = resolve(repositoryRoot, 'engine', 'client', 'i18n', 'locales', 'ru-RU.json')
const scanRoots = [
  resolve(themeRoot, 'src'),
  resolve(repositoryRoot, 'engine', 'client'),
  resolve(repositoryRoot, 'engine', 'classes', 'modules'),
]
const ignoredDirectories = new Set(['node_modules', '.git', '.vite', 'assets', '_migration_backups', 'i18n'])
const userFacingAttributes = new Set([
  'alt', 'aria-label', 'caption', 'choose-label', 'clear-label', 'data-label', 'description',
  'drop-label', 'editor-label', 'empty-text', 'eyebrow', 'hint', 'label', 'placeholder',
  'preview-alt', 'replace-label', 'title',
])
const uiPropertyNames = new Set([
  'action', 'ariaLabel', 'caption', 'chooseLabel', 'clearLabel', 'description', 'dropLabel',
  'editorLabel', 'emptyMessage', 'emptyText', 'error', 'eyebrow', 'hint', 'label', 'message',
  'placeholder', 'previewAlt', 'replaceLabel', 'title',
])
const uiCallNames = new Set([
  'confirm', 'prompt', 'showToast', 'toastFeedback', 'Error', 'FoxesApiError', 'fail',
  'rejectFile', 'errorMessage',
])
const cyrillicPattern = /[А-Яа-яЁё]/u
const letterPattern = /\p{L}/u
const technicalTokenPattern = /^(?:https?:\/\/\S+|\/\S*|\.{1,2}\/\S*|#[0-9a-f]{3,8}|[a-z0-9_.:@%/+~-]+|[A-Za-z]:\\.*)$/u
const technicalFilePattern = /^(?:[\w@.-]+\/)*[\w@.-]+\.(?:html?|json|mjs|[cm]?[jt]sx?|css|scss|png|jpe?g|webp|gif|avif|svg|woff2?|ttf|log|php)$/iu
const failures = []
const usedKeys = new Set()

const localeBytes = await readFile(localePath)
if (localeBytes[0] === 0xef && localeBytes[1] === 0xbb && localeBytes[2] === 0xbf) {
  failures.push('ru-RU.json must not contain a UTF-8 BOM')
}
const localeText = localeBytes.toString('utf8')
if (localeText.includes('\uFFFD')) failures.push('ru-RU.json contains Unicode replacement characters')
let messages = {}
try {
  messages = JSON.parse(localeText)
} catch (error) {
  failures.push(`ru-RU.json is invalid JSON: ${error.message}`)
}
const messageKeys = new Set(Object.keys(messages))
for (const [key, value] of Object.entries(messages)) {
  if (typeof value !== 'string' || value.trim() === '') failures.push(`Invalid translation value: ${key}`)
}

function normalizeMessage(value) {
  return value.replace(/\s+/gu, ' ').trim()
}

function isHumanText(value) {
  const normalized = normalizeMessage(value)
  if (!normalized || !letterPattern.test(normalized)) return false
  if (cyrillicPattern.test(normalized)) return true
  if (technicalFilePattern.test(normalized) || technicalTokenPattern.test(normalized)) return false
  if (/^(?:true|false|null|undefined|auto|none|normal|cover|contain|force|object|array|string|number|boolean)$/iu.test(normalized)) return false
  return /\s/u.test(normalized) || /^[A-ZА-ЯЁ][\p{L}\d-]{2,}$/u.test(normalized) || /^[A-Z\d]{2,}$/u.test(normalized)
}

function propertyName(node) {
  if (!node) return ''
  if (ts.isIdentifier(node) || ts.isStringLiteral(node) || ts.isNumericLiteral(node)) return node.text
  return ''
}

function callName(expression) {
  if (ts.isIdentifier(expression)) return expression.text
  if (ts.isPropertyAccessExpression(expression)) return expression.name.text
  return ''
}

function isImportSpecifierString(node) {
  const parent = node.parent
  return Boolean(parent && (
    (ts.isImportDeclaration(parent) && parent.moduleSpecifier === node)
    || (ts.isExportDeclaration(parent) && parent.moduleSpecifier === node)
  ))
}

function isInsideTranslationCall(node) {
  let current = node.parent
  while (current) {
    if (ts.isCallExpression(current) && callName(current.expression) === 't') return true
    if (ts.isStatement(current) || ts.isSourceFile(current)) break
    current = current.parent
  }
  return false
}

function isEnglishUiContext(node, mode) {
  if (mode === 'template') {
    if (node.parent && ts.isBinaryExpression(node.parent)) return false
    return true
  }
  const parent = node.parent
  if (!parent) return false
  if (ts.isPropertyAssignment(parent) && parent.initializer === node) return uiPropertyNames.has(propertyName(parent.name))
  if (ts.isCallExpression(parent)) return uiCallNames.has(callName(parent.expression))
  if (ts.isNewExpression(parent)) return uiCallNames.has(callName(parent.expression))
  return false
}

function lineAt(source, offset) {
  return source.slice(0, Math.max(0, offset)).split('\n').length
}

function report(path, source, offset, message) {
  failures.push(`${relative(repositoryRoot, path).split(sep).join('/')}:${lineAt(source, offset)} ${message}`)
}

function inspectTypeScript(code, path, source, baseOffset, mode) {
  const sourceFile = ts.createSourceFile(path, code, ts.ScriptTarget.Latest, true, ts.ScriptKind.TSX)

  function inspectLiteral(node, value) {
    if (isImportSpecifierString(node) || isInsideTranslationCall(node)) return
    const normalized = normalizeMessage(value)
    if (!normalized) return
    if (cyrillicPattern.test(normalized) || (isHumanText(normalized) && isEnglishUiContext(node, mode))) {
      report(path, source, baseOffset + node.getStart(sourceFile), `hardcoded interface message: ${JSON.stringify(normalized)}`)
    }
  }

  function visit(node) {
    if (ts.isStringLiteral(node) || ts.isNoSubstitutionTemplateLiteral(node)) inspectLiteral(node, node.text)
    if (ts.isTemplateExpression(node) && !isInsideTranslationCall(node)) {
      const parts = [node.head.text, ...node.templateSpans.map((span) => span.literal.text)]
      if (parts.some((part) => cyrillicPattern.test(part)) || (mode === 'template' && parts.some(isHumanText))) {
        report(path, source, baseOffset + node.getStart(sourceFile), 'hardcoded template interface message')
        return
      }
    }
    if (ts.isCallExpression(node) && callName(node.expression) === 't') {
      const first = node.arguments[0]
      if (first && ts.isStringLiteral(first)) {
        usedKeys.add(first.text)
        if (!messageKeys.has(first.text)) report(path, source, baseOffset + first.getStart(sourceFile), `missing translation key: ${first.text}`)
      }
    }
    ts.forEachChild(node, visit)
  }

  visit(sourceFile)
}

function walkTemplate(node, path, source, templateOffset) {
  if (node.type === NodeTypes.TEXT) {
    if (isHumanText(node.content)) report(path, source, templateOffset + node.loc.start.offset, `hardcoded template text: ${JSON.stringify(normalizeMessage(node.content))}`)
    return
  }
  if (node.type === NodeTypes.INTERPOLATION && node.content?.loc) {
    inspectTypeScript(node.content.loc.source, path, source, templateOffset + node.content.loc.start.offset, 'template')
    return
  }
  if (node.type === NodeTypes.ELEMENT) {
    for (const prop of node.props || []) {
      if (prop.type === NodeTypes.ATTRIBUTE && prop.value && userFacingAttributes.has(prop.name) && isHumanText(prop.value.content)) {
        report(path, source, templateOffset + prop.loc.start.offset, `hardcoded ${prop.name}: ${JSON.stringify(prop.value.content)}`)
      } else if (prop.type === NodeTypes.DIRECTIVE && prop.exp?.loc) {
        const boundAttribute = prop.name === 'bind' && prop.arg?.type === NodeTypes.SIMPLE_EXPRESSION
          ? prop.arg.content
          : ''
        if (!['class', 'style'].includes(boundAttribute)) {
          inspectTypeScript(prop.exp.loc.source, path, source, templateOffset + prop.exp.loc.start.offset, 'template')
        }
      }
    }
    for (const child of node.children || []) walkTemplate(child, path, source, templateOffset)
    return
  }
  if (node.type === NodeTypes.ROOT) {
    for (const child of node.children || []) walkTemplate(child, path, source, templateOffset)
  }
}

async function listFiles(directory) {
  const output = []
  const entries = await readdir(directory, { withFileTypes: true }).catch(() => [])
  for (const entry of entries) {
    if (entry.isDirectory() && ignoredDirectories.has(entry.name)) continue
    const path = resolve(directory, entry.name)
    if (entry.isDirectory()) output.push(...await listFiles(path))
    else if (entry.isFile() && ['.vue', '.ts'].includes(extname(entry.name))) output.push(path)
  }
  return output
}

const files = (await Promise.all(scanRoots.map(listFiles))).flat().sort()
for (const path of files) {
  const bytes = await readFile(path)
  if (bytes[0] === 0xef && bytes[1] === 0xbb && bytes[2] === 0xbf) {
    failures.push(`${relative(repositoryRoot, path)} contains a UTF-8 BOM`)
  }
  const source = bytes.toString('utf8')
  if (source.includes('\uFFFD')) failures.push(`${relative(repositoryRoot, path)} contains Unicode replacement characters`)

  if (extname(path) === '.vue') {
    const parsed = parseSfc(source, { filename: path })
    if (parsed.errors.length) {
      failures.push(`${relative(repositoryRoot, path)} cannot be parsed as Vue SFC`)
      continue
    }
    const { descriptor } = parsed
    if (descriptor.template) {
      const ast = baseParse(descriptor.template.content, { ...parserOptions, comments: false })
      walkTemplate(ast, path, source, descriptor.template.loc.start.offset)
    }
    if (descriptor.scriptSetup) {
      inspectTypeScript(descriptor.scriptSetup.content, path, source, descriptor.scriptSetup.loc.start.offset, 'script')
    }
  } else {
    inspectTypeScript(source, path, source, 0, 'script')
  }

  if (/\bt\s*\(/u.test(source) && !source.includes("from '@/i18n'")) {
    failures.push(`${relative(repositoryRoot, path)} uses t() without importing @/i18n`)
  }
}

if (failures.length) {
  console.error('i18n contract failed:')
  for (const failure of failures) console.error(`- ${failure}`)
  process.exit(1)
}

const unused = [...messageKeys].filter((key) => !usedKeys.has(key))
if (unused.length) {
  console.error('i18n contract failed:')
  for (const key of unused) console.error(`- unused translation key: ${key}`)
  process.exit(1)
}
console.log(`i18n contract passed: ${files.length} client files, ${messageKeys.size} messages, ${usedKeys.size} referenced keys, 0 unused keys.`)
