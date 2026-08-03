import { readdir, readFile } from 'node:fs/promises'
import { extname, join, relative } from 'node:path'
import { repositoryRoot, themeRoot } from './theme-paths.mjs'

const failures = []
const roots = [
  join(themeRoot, 'src'),
  join(repositoryRoot, 'engine', 'client'),
  join(repositoryRoot, 'engine', 'classes', 'modules'),
]
const allowedNativeCheckbox = join(repositoryRoot, 'engine', 'client', 'components', 'UiCheckbox.vue')

async function walk(directory) {
  const entries = await readdir(directory, { withFileTypes: true })
  const files = []
  for (const entry of entries) {
    const path = join(directory, entry.name)
    if (entry.isDirectory()) files.push(...await walk(path))
    else if (entry.isFile() && extname(entry.name) === '.vue') files.push(path)
  }
  return files
}

for (const root of roots) {
  for (const file of await walk(root)) {
    const source = await readFile(file, 'utf8')
    const directCheckbox = /<input\b[^>]*\btype\s*=\s*["']checkbox["'][^>]*>/gis.test(source)
    if (directCheckbox && file !== allowedNativeCheckbox) {
      failures.push(`default checkbox found in ${relative(repositoryRoot, file)}; use UiCheckbox`)
    }
  }
}

const checkbox = await readFile(allowedNativeCheckbox, 'utf8')
for (const token of ['defineModel<boolean>', "variant?: CheckboxVariant", 'ui-checkbox__control', 'type="checkbox"']) {
  if (!checkbox.includes(token)) failures.push(`UiCheckbox missing ${token}`)
}

const checkboxCss = await readFile(join(repositoryRoot, 'engine', 'client', 'components', 'ui-checkbox.css'), 'utf8')
for (const token of ['.ui-checkbox--checkbox', '.ui-checkbox--switch', '.is-checked', ':focus-visible', ':user-invalid']) {
  if (!checkboxCss.includes(token)) failures.push(`UiCheckbox CSS missing ${token}`)
}

const appCss = await readFile(join(themeRoot, 'src', 'styles', 'app.css'), 'utf8')
for (const token of ['.button--glass::before', '.button--glass:hover::before', 'backdrop-filter:blur(18px)', '.button--glass.button--large', '.button:focus-visible']) {
  if (!appCss.includes(token)) failures.push(`glass button CSS missing ${token}`)
}

const codeEditorPath = join(themeRoot, 'src', 'foxEngine', 'editor', 'CodeEditor.vue')
const codeEditor = await readFile(codeEditorPath, 'utf8')
for (const token of [
  "from 'codemirror'",
  "codemirror/lib/codemirror.css",
  "codemirror/mode/htmlmixed/htmlmixed",
  "codemirror/addon/fold/foldgutter",
  'CodeMirror.version',
  'CodeMirror(host.value',
  'CodeMirror-foldgutter',
]) {
  if (!codeEditor.includes(token)) failures.push(`CodeMirror 5 editor missing ${token}`)
}
if (codeEditor.includes('@codemirror/')) failures.push('CodeEditor must use CodeMirror 5, not @codemirror version 6 packages')


const jsonTypes = await readFile(join(repositoryRoot, 'engine', 'client', 'forms', 'json-form', 'types.ts'), 'utf8')
const jsonForm = await readFile(join(repositoryRoot, 'engine', 'client', 'forms', 'json-form', 'JsonFormEditor.vue'), 'utf8')
const jsonValue = await readFile(join(repositoryRoot, 'engine', 'client', 'forms', 'json-form', 'JsonValueEditor.vue'), 'utf8')
const jsonFormCss = await readFile(join(repositoryRoot, 'engine', 'client', 'forms', 'json-form', 'json-form.css'), 'utf8')
const catalogs = await readFile(join(themeRoot, 'src', 'foxEngine', 'admin', 'Catalogs.vue'), 'utf8')
for (const [source, token, message] of [
  [jsonTypes, "export type JsonFieldControl = 'color'", 'JSON form color control type is missing'],
  [jsonForm, 'fieldControls?: JsonFieldControls', 'JsonFormEditor does not expose field controls'],
  [jsonForm, ':field-controls="fieldControls"', 'JsonFormEditor does not pass field controls to values'],
  [jsonValue, 'type="color"', 'JSON value editor does not render a native color picker'],
  [jsonValue, 'colorPickerValue', 'JSON value editor does not normalize color picker values'],
  [jsonValue, ':field-controls="fieldControls"', 'JSON value editor does not propagate field controls recursively'],
  [jsonFormCss, '.json-color-control__picker', 'JSON color picker styles are missing'],
  [catalogs, "{ groupColor: 'color' }", 'Groups catalog does not declare groupColor as a color picker'],
  [catalogs, ':field-controls="fieldControls"', 'Groups catalog does not pass color field controls'],
]) {
  if (!source.includes(token)) failures.push(message)
}

const packageJson = JSON.parse(await readFile(join(themeRoot, 'package.json'), 'utf8'))
const codeMirrorVersion = packageJson?.dependencies?.codemirror
if (typeof codeMirrorVersion !== 'string' || !/^\^?5\./.test(codeMirrorVersion)) {
  failures.push(`CodeMirror dependency must remain on major version 5, received ${String(codeMirrorVersion)}`)
}
for (const dependency of Object.keys(packageJson?.dependencies ?? {})) {
  if (dependency.startsWith('@codemirror/')) failures.push(`CodeMirror 6 dependency is forbidden: ${dependency}`)
}

if (failures.length) {
  console.error('UI controls contract failed:')
  for (const failure of failures) console.error(`- ${failure}`)
  process.exit(1)
}

console.log('UI controls contract passed: shared controls, JSON color pickers and the CodeMirror 5 editor are enforced.')
