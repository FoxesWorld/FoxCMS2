import { readdir, readFile } from 'node:fs/promises'
import { join } from 'node:path'
import { repositoryRoot, themeRoot } from './theme-paths.mjs'

const failures = []
const paths = {
  service: join(repositoryRoot, 'engine', 'classes', 'services', 'RuntimeJdkCatalog.class.php'),
  backend: join(repositoryRoot, 'engine', 'classes', 'modules', 'AdminPanel', 'AdminOptions.class.php'),
  client: join(repositoryRoot, 'engine', 'classes', 'modules', 'AdminPanel', 'client', 'useAdminPanel.ts'),
  editor: join(themeRoot, 'src', 'foxEngine', 'admin', 'servers', 'ServerEditor.vue'),
  request: join(repositoryRoot, 'api', 'bootstrap', 'runtime-catalog', 'request.php'),
  platform: join(repositoryRoot, 'api', 'bootstrap', 'runtime-catalog', 'platform.php'),
  selection: join(repositoryRoot, 'api', 'bootstrap', 'runtime-catalog', 'selection.php'),
  resolver: join(repositoryRoot, 'api', 'bootstrap', 'runtime-catalog', 'resolver.php'),
  archive: join(repositoryRoot, 'api', 'bootstrap', 'runtime-catalog', 'archive.php'),
}

const service = await readFile(paths.service, 'utf8')
for (const token of [
  'final class RuntimeJdkCatalog',
  'RecursiveDirectoryIterator',
  'RecursiveIteratorIterator',
  "isset($tokenSet['linux']) || isset($tokenSet['unix'])",
  "$families[$family]['versionsBySystem'][$system][$version] = true",
  "'value' => $family",
  "'versions' => $versions",
  "'versionsBySystem' => $versionsBySystem",
  "'selectedVersions' => $selectedVersions",
  "'mode' => 'major-families-by-file-name'",
  "'versionSource' => 'archive-file-name-major'",
  'public function normalizeVersion(',
]) {
  if (!service.includes(token)) failures.push(`RuntimeJdkCatalog is missing ${token}`)
}
for (const forbidden of [
  'ZipArchive',
  'PharData',
  'file_get_contents(',
  'inspectRuntimeArchive(',
  'bin/java',
  'bin/javac',
]) {
  if (service.includes(forbidden)) failures.push(`Admin catalog must remain filename-only: ${forbidden}`)
}

const backend = await readFile(paths.backend, 'utf8')
for (const token of [
  'private RuntimeJdkCatalog $runtimeJdkCatalog',
  "'jdkOptions' => $jdkOptions",
  "'jdkCatalog' => $catalog",
  "$runtimeWarning = '';",
  '$this->runtimeJdkCatalog->normalizeVersion($value)',
  '$value = $normalizedVersion',
  "if ($enabled && (!isset($data['jreVersion'])",
  "'type' => $runtimeWarning !== '' ? 'warning' : 'success'",
  'сохранён без проверки каталога runtime',
]) {
  if (!backend.includes(token)) failures.push(`Admin server runtime backend is missing ${token}`)
}
for (const forbidden of [
  "'Не удалось проверить Java runtime в каталоге '",
  "'Семейство JDK ' . $value",
]) {
  if (backend.includes(forbidden)) failures.push(`Server persistence must not be blocked by runtime catalog state: ${forbidden}`)
}

const client = await readFile(paths.client, 'utf8')
for (const token of [
  'versions: string[]',
  'versionsBySystem: Record<string, string[]>',
  'selectedVersions: Record<string, string>',
  "mode?: 'major-families-by-file-name'",
  "versionSource?: 'archive-file-name-major'",
  'option.versions.includes(rawRuntime)',
  'jreVersion: runtimeFamily',
]) {
  if (!client.includes(token)) failures.push(`Admin runtime client is missing ${token}`)
}

const editor = await readFile(paths.editor, 'utf8')
for (const token of [
  '<select',
  'v-model="draft.jreVersion"',
  ':required="draft.enabled"',
  'const runtimeConfigured = computed(',
  'const runtimeSaveBlocked = computed(',
  ':disabled="loading || imageUploading || runtimeSaveBlocked"',
  '{{ runtime.label }}',
  'Windows {{ selectedJdk.selectedVersions.windows }}',
  'Linux {{ selectedJdk.selectedVersions.linux }}',
  'macOS {{ selectedJdk.selectedVersions.macos }}',
  'Сохранение конфигурации доступно',
  'Отключённый сервер можно сохранить как черновик',
]) {
  if (!editor.includes(token)) failures.push(`ServerEditor major-family select is missing ${token}`)
}
for (const token of [
  'class="admin-editor admin-user-editor admin-server-editor"',
  'class="admin-user-editor__hero admin-server-editor__hero"',
  '<h3>Подключение</h3>',
  '<h3>Состояние и запуск</h3>',
  '<h3>Представление сервера</h3>',
  '<h3>Доступ и клиентские данные</h3>',
  'class="admin-user-editor__footer admin-server-editor__footer"',
  "@click=\"emit('remove', selected)\"",
  "'fa-spinner' : 'fa-floppy-disk'",
]) {
  if (!editor.includes(token)) failures.push(`ServerEditor structured floating form is missing ${token}`)
}
if (/<button[\s\S]{0,300}>[\s\S]{0,80}Сохранить сервер[\s\S]{0,80}<\/button>[\s\S]{0,40}<\/form>/.test(editor)
    && !editor.includes('admin-server-editor__footer')) {
  failures.push('Server save action is not inside the floating footer')
}

if (editor.includes('!jdkCatalog.available || jdkOptions.length === 0 || !selectedJdk')) {
  failures.push('Server save button is still locked by Java runtime catalog readiness')
}
if (/<input[^>]+v-model="draft\.jreVersion"/i.test(editor)) {
  failures.push('ServerEditor still uses a free-text Java runtime input')
}

const request = await readFile(paths.request, 'utf8')
for (const token of [
  "preg_match('/^[0-9]+(?:\\.[0-9]+)*$/D'",
  "$versionMode = strpos($version, '.') === false ? 'major' : 'exact'",
  "'version_mode' => $versionMode",
]) {
  if (!request.includes(token)) failures.push(`Bootstrap runtime request is missing ${token}`)
}

const platform = await readFile(paths.platform, 'utf8')
for (const token of [
  "array('unix', 'x32')",
  "array('unix', 'x64')",
  "array('unix', 'arm64')",
]) {
  if (!platform.includes(token)) failures.push(`Bootstrap Linux aliases are missing ${token}`)
}

const selection = await readFile(paths.selection, 'utf8')
for (const token of [
  "($request['version_mode'] ?? 'exact') === 'major'",
  "'java_major_mismatch'",
  'Java major and platform match.',
  'Selected newest Java %s.x',
]) {
  if (!selection.includes(token)) failures.push(`Bootstrap selection is missing ${token}`)
}

const resolver = await readFile(paths.resolver, 'utf8')
for (const token of [
  "'runtime_major_version_unavailable'",
  "version_compare((string)$right['version_core'], (string)$left['version_core'])",
  "'version_mode' => $request['version_mode'] ?? 'exact'",
]) {
  if (!resolver.includes(token)) failures.push(`Bootstrap resolver is missing ${token}`)
}

const archive = await readFile(paths.archive, 'utf8')
for (const token of [
  "$version = $metadataVersion !== '' ? $metadataVersion : $fileVersion",
  'release === array()',
  "str_replace('-metadata', '-layout', $inspection)",
  'The Java version cannot be derived from release metadata or the archive filename.',
]) {
  if (!archive.includes(token)) failures.push(`Release-less bootstrap archive fallback is missing ${token}`)
}
if (archive.includes('Runtime archive has no release metadata beside the selected Java home.')) {
  failures.push('Bootstrap archive scanner still requires release metadata')
}

function versionFromName(fileName) {
  const name = fileName.replace(/\.(?:tar\.gz|zip|tgz)$/i, '')
  return name.match(/(?:jdk|jre|java)[-_]?([0-9]+(?:\.[0-9]+)*)/i)?.[1]
    ?? name.match(/(?:^|[-_])([0-9]+(?:\.[0-9]+)*)(?:$|[-_+])/i)?.[1]
    ?? ''
}
function majorVersion(version) {
  return Number(version.match(/^(?:1\.)?([0-9]+)/)?.[1] ?? 0)
}
function systemFromPath(path) {
  const tokens = new Set(path.toLowerCase().split(/[^a-z0-9]+/).filter(Boolean))
  if (['windows', 'win', 'win32', 'win64'].some((token) => tokens.has(token))) return 'windows'
  if (tokens.has('linux') || tokens.has('unix')) return 'linux'
  if (['mac', 'macos', 'osx', 'darwin'].some((token) => tokens.has(token))) return 'macos'
  return null
}
function compareVersionsDesc(left, right) {
  const a = left.split('.').map(Number)
  const b = right.split('.').map(Number)
  for (let index = 0; index < Math.max(a.length, b.length); index += 1) {
    const difference = (b[index] ?? 0) - (a[index] ?? 0)
    if (difference !== 0) return difference
  }
  return 0
}
async function walkArchives(directory, prefix = '') {
  const result = []
  for (const entry of await readdir(directory, { withFileTypes: true }).catch(() => [])) {
    const relative = prefix ? `${prefix}/${entry.name}` : entry.name
    const absolute = join(directory, entry.name)
    if (entry.isDirectory()) result.push(...await walkArchives(absolute, relative))
    else if (entry.isFile() && /\.(?:zip|tgz|tar\.gz)$/i.test(entry.name)) result.push(relative)
  }
  return result
}

const runtimeRoot = join(repositoryRoot, 'uploads', 'bootstrap', 'runtime')
const archives = await walkArchives(runtimeRoot)
const families = new Map()
for (const path of archives) {
  const fileName = path.split('/').at(-1) ?? ''
  const version = versionFromName(fileName)
  const major = majorVersion(version)
  const system = systemFromPath(path)
  if (!version || !major || !system) continue
  const family = families.get(major) ?? new Map()
  const versions = family.get(system) ?? new Set()
  versions.add(version)
  family.set(system, versions)
  families.set(major, family)
}

const commonFamilies = [...families]
  .filter(([, systems]) => ['windows', 'linux', 'macos'].every((system) => systems.has(system)))
  .sort(([left], [right]) => right - left)
  .map(([major, systems]) => ({
    major,
    selected: Object.fromEntries(
      [...systems].map(([system, versions]) => [system, [...versions].sort(compareVersionsDesc)[0]]),
    ),
  }))

const expected = [
  { major: 25, selected: { windows: '25.0.2', linux: '25.0.2', macos: '25.0.1' } },
  { major: 17, selected: { windows: '17.0.20', linux: '17.0.13', macos: '17.0.13' } },
  { major: 11, selected: { windows: '11.0.29', linux: '11.0.29', macos: '11.0.29' } },
]
if (commonFamilies.length !== expected.length) {
  failures.push(`actual runtime family count mismatch: ${JSON.stringify(commonFamilies)}`)
} else {
  for (const expectedFamily of expected) {
    const actual = commonFamilies.find((entry) => entry.major === expectedFamily.major)
    if (!actual) {
      failures.push(`missing JDK family ${expectedFamily.major}`)
      continue
    }
    for (const system of ['windows', 'linux', 'macos']) {
      if (actual.selected[system] !== expectedFamily.selected[system]) {
        failures.push(`JDK ${expectedFamily.major} ${system} expected ${expectedFamily.selected[system]}, got ${actual.selected[system] ?? 'none'}`)
      }
    }
  }
}

if (failures.length) {
  console.error('Runtime JDK catalog contract failed:')
  for (const failure of failures) console.error(`- ${failure}`)
  process.exit(1)
}

console.log(`Runtime JDK catalog passed: ${archives.length} archives; families ${commonFamilies.map((entry) => entry.major).join(', ')}.`)
