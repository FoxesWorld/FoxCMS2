import { readdir, readFile } from 'node:fs/promises'
import { join } from 'node:path'
import { repositoryRoot, themeRoot } from './theme-paths.mjs'

const failures = []
const paths = {
  service: join(repositoryRoot, 'engine', 'classes', 'services', 'RuntimeJdkCatalog.class.php'),
  gameVersions: join(repositoryRoot, 'engine', 'classes', 'services', 'GameVersionCatalog.class.php'),
  backend: join(repositoryRoot, 'engine', 'classes', 'modules', 'AdminPanel', 'AdminOptions.class.php'),
  serverBackend: join(repositoryRoot, 'engine', 'classes', 'modules', 'AdminPanel', 'AdminServerController.class.php'),
  client: join(repositoryRoot, 'engine', 'classes', 'modules', 'AdminPanel', 'client', 'useAdminPanel.ts'),
  editor: join(themeRoot, 'src', 'foxEngine', 'admin', 'servers', 'ServerEditor.vue'),
  selectBox: join(repositoryRoot, 'engine', 'client', 'components', 'UiSelectBox.vue'),
  systemRequests: join(repositoryRoot, 'engine', 'SystemRequests.class.php'),
  getJre: join(repositoryRoot, 'engine', 'classes', 'utils', 'GetJre', '1.0.0', 'GetJre.class.php'),
  request: join(repositoryRoot, 'api', 'src', 'FoxCMS', 'Api', 'Bootstrap', 'Runtime', 'RuntimeRequest.php'),
  platform: join(repositoryRoot, 'api', 'src', 'FoxCMS', 'Api', 'Bootstrap', 'Runtime', 'RuntimePlatform.php'),
  selection: join(repositoryRoot, 'api', 'src', 'FoxCMS', 'Api', 'Bootstrap', 'Runtime', 'RuntimeSelection.php'),
  resolver: join(repositoryRoot, 'api', 'src', 'FoxCMS', 'Api', 'Bootstrap', 'Runtime', 'RuntimeResolver.php'),
  archive: join(repositoryRoot, 'api', 'src', 'FoxCMS', 'Api', 'Bootstrap', 'Runtime', 'RuntimeArchive.php'),
}

function requireTokens(source, label, tokens) {
  for (const token of tokens) {
    if (!source.includes(token)) failures.push(`${label} is missing ${token}`)
  }
}

function forbidTokens(source, label, tokens) {
  for (const token of tokens) {
    if (source.includes(token)) failures.push(`${label} still contains forbidden contract ${token}`)
  }
}

const service = await readFile(paths.service, 'utf8')
requireTokens(service, 'RuntimeJdkCatalog', [
  'final class RuntimeJdkCatalog',
  'inspectRuntimeArchive(',
  'runtimeBuildProfileSelector(',
  "'value' => (string)$major",
  "'profile' => $profile",
  "'version' => (string)$major",
  "'artifacts' => $artifacts",
  "'archives' => $archiveCount",
  "'versionsByPlatform' => $versionsByPlatform",
  "'complete' => $complete",
  "'missingPlatforms' => $missingPlatforms",
  "'mode' => 'exact-runtime-profiles'",
  'public function normalizeVersion(',
  'public static function normalizeMajorSelector(',
  'public function resolveArtifact(',
])
forbidTokens(service, 'RuntimeJdkCatalog persistence', [
  "'value' => $profile",
  "'version' => $profile",
  "array_diff(self::REQUIRED_PLATFORMS, array_keys($selectedByPlatform)) !== []",
])

const backend = (await Promise.all([paths.backend, paths.serverBackend].map((path) => readFile(path, 'utf8')))).join('\n')
requireTokens(backend, 'Admin server runtime backend', [
  'private RuntimeJdkCatalog $runtimeJdkCatalog',
  'private GameVersionCatalog $gameVersionCatalog',
  "'jdkOptions' => $jdkOptions",
  "'gameVersionOptions' => $gameVersionOptions",
  'RuntimeJdkCatalog::normalizeMajorSelector($value)',
  '$value = $major',
  '$this->runtimeJdkCatalog->normalizeVersion($value)',
  '$this->requiredJavaMajorForServerVersion',
  "$data['jreVersion'] = $requiredJavaMajor",
  "if ($enabled && (!isset($data['jreVersion'])",
  "'type' => $runtimeWarning !== '' ? 'warning' : 'success'",
  "foxEnv('FOXESCRAFT_GAME_VERSIONS_DIRECTORY'",
  ". DIRECTORY_SEPARATOR . 'game'",
  ". DIRECTORY_SEPARATOR . 'versions'",
])
forbidTokens(backend, 'Admin server runtime persistence', [
  '$value = $normalizedProfile',
  'Выберите полный runtime profile',
])

const gameVersions = await readFile(paths.gameVersions, 'utf8')
requireTokens(gameVersions, 'GameVersionCatalog', [
  'final class GameVersionCatalog',
  'realpath($this->versionsDirectory)',
  'scandir($root)',
  "str_starts_with($entry, '.')",
  '!is_dir($path) || is_link($path) || !is_readable($path)',
  "preg_match('/^[A-Za-z0-9._+ -]{1,128}$/D'",
  "'value' => $entry",
  'strnatcasecmp($right[\'value\'], $left[\'value\'])',
])

const client = await readFile(paths.client, 'utf8')
requireTokens(client, 'Admin runtime client', [
  'export function javaMajorFromSelector',
  'profile: string',
  'selectors: string[]',
  'artifacts: Record<string, JdkRuntimeArtifact>',
  'complete: boolean',
  'missingSystems: string[]',
  'missingPlatforms: string[]',
  "mode?: 'exact-runtime-profiles'",
  'gameVersionOptions: GameVersionOption[]',
  'gameVersionCatalog: GameVersionCatalogStatus',
  'const parsedRuntimeMajor = javaMajorFromSelector(rawRuntime)',
  'const runtimeMajor = jdkOptions.value.find',
  'option.profile === rawRuntime',
  'jreVersion: runtimeMajor',
  "serverVersion: gameVersionOptions.value[0]?.value ?? ''",
  "jreVersion: jdkOptions.value[0]?.value ?? ''",
])

const editor = await readFile(paths.editor, 'utf8')
requireTokens(editor, 'ServerEditor custom selects', [
  "import UiSelectBox from '@/components/UiSelectBox.vue'",
  'v-model="draft.serverVersion"',
  ':options="gameVersionSelectOptions"',
  'v-model="draft.jreVersion"',
  ':options="jdkSelectOptions"',
  ':required="draft.enabled"',
  ':invalid="runtimeSaveBlocked"',
  'const parsedRuntimeMajor = computed(() => javaMajorFromSelector(rawRuntimeValue.value))',
  'option.profile === rawRuntimeValue.value',
  'gameVersionCatalog.root',
  "runtimeVersionOrMissing(runtime, 'windows')",
  "runtimeVersionOrMissing(runtime, 'linux')",
  "runtimeVersionOrMissing(runtime, 'macos')",
  "tone: runtime.complete ? 'default' as const : 'warning' as const",
  'selectedJdk.missingPlatforms.join',
  'compatibilityRequiredJavaMajor',
  'requiredJavaMajorForGameVersion',
  'label: `JDK ${runtime.javaMajor}`',
  'class="server-runtime-select"',
  "runtime.versions.join(', ')",
  "selectedJdk.versions.join(', ')",
])
if (/<input[^>]+v-model="draft\.jreVersion"/i.test(editor)) {
  failures.push('ServerEditor still uses a free-text Java runtime input')
}
if (/<select[^>]+v-model="draft\.jreVersion"/i.test(editor)) {
  failures.push('ServerEditor still uses a native Java runtime select')
}
if (/<input[^>]+v-model(?:\.trim)?="draft\.serverVersion"/i.test(editor)) {
  failures.push('ServerEditor still uses a free-text game version input')
}

const selectBox = await readFile(paths.selectBox, 'utf8')
requireTokens(selectBox, 'UiSelectBox', [
  'role="combobox"',
  'role="listbox"',
  'role="option"',
  'aria-activedescendant',
  'handleTriggerKeydown',
  'handleListKeydown',
  "event.key === 'ArrowDown'",
  "event.key === 'Escape'",
  'type="search"',
  'filteredOptions',
  'scrollActiveOptionIntoView',
  "scrollIntoView({ block: 'nearest' })",
  'document.addEventListener(\'pointerdown\'',
])

const systemRequests = await readFile(paths.systemRequests, 'utf8')
requireTokens(systemRequests, 'SystemRequests GetJre call', [
  'new GetJre(',
  "$this->request->string('jreVersion')",
  "$this->request->string('platform')",
  '$this->config',
])

const getJre = await readFile(paths.getJre, 'utf8')
requireTokens(getJre, 'GetJre payload', [
  'RuntimeJdkCatalog::normalizePlatform(',
  '$catalog->resolveArtifact($selector, $normalizedPlatform)',
  "'requestedVersion' => $selector",
  "'jreVersion' => (string)($artifact['java_major'] ?? '')",
  "'runtimeProfile' => (string)($artifact['profile'] ?? $selector)",
  "'version' => (string)($artifact['version'] ?? '')",
  "'javaMajor' => (int)($artifact['java_major'] ?? 0)",
  "'platform' => (string)($artifact['platform'] ?? $normalizedPlatform)",
  "'installPath' => (string)($artifact['install_path'] ?? '')",
  "'javaPath' => (string)($artifact['java_path'] ?? '')",
  "'stripComponents' => (int)($artifact['strip_components'] ?? 0)",
])

const request = await readFile(paths.request, 'utf8')
requireTokens(request, 'Bootstrap runtime request', [
  "$versionMode = strpos($version, '.') === false ? 'major' : 'exact'",
  "'version_mode' => $versionMode",
])

const platform = await readFile(paths.platform, 'utf8')
requireTokens(platform, 'Bootstrap platform aliases', [
  "array('unix', 'x32')",
  "array('unix', 'x64')",
  "array('unix', 'arm64')",
])

const selection = await readFile(paths.selection, 'utf8')
requireTokens(selection, 'Bootstrap runtime selection', [
  "($request['version_mode'] ?? 'exact') === 'major'",
  "'java_major_mismatch'",
  'Selected newest Java %s.x',
])

const resolver = await readFile(paths.resolver, 'utf8')
requireTokens(resolver, 'Bootstrap runtime resolver', [
  "'runtime_major_version_unavailable'",
  "'version_mode' => $request['version_mode'] ?? 'exact'",
])

const archive = await readFile(paths.archive, 'utf8')
requireTokens(archive, 'Runtime archive parser', [
  "$version = $metadataVersion !== '' ? $metadataVersion : $fileVersion",
  'release === array()',
  '$jdkRoots',
  "$candidatePrefix . 'bin/' . $expectedJavac",
  '$releaseRoots',
  'Java 8 JDK archives legitimately contain',
  'The Java version cannot be derived from release metadata or the archive filename.',
])

function versionFromName(fileName) {
  const name = fileName.replace(/\.(?:tar\.gz|zip|tgz)$/i, '')
  const legacy = name.match(/(?:jdk|jre|java)[-_]?(?:1\.8\.0[_-]([0-9]+)|8u([0-9]+))/i)
  if (legacy) return `8u${legacy[1] ?? legacy[2]}`
  return name.match(/(?:jdk|jre|java)[-_]?([0-9]+(?:\.[0-9]+)+(?:[+_][0-9]+)?)/i)?.[1]
    ?? name.match(/(?:^|[-_])([0-9]+(?:\.[0-9]+)*)(?:$|[-_+])/i)?.[1]
    ?? ''
}

function majorVersion(version) {
  if (/^8u[0-9]+/i.test(version)) return 8
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
  const a = left.match(/[0-9]+/g)?.map(Number) ?? []
  const b = right.match(/[0-9]+/g)?.map(Number) ?? []
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

const availableFamilies = [...families]
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
  { major: 8, selected: { windows: '8u502', linux: '8u502', macos: '8u502' } },
]
for (const expectedFamily of expected) {
  const actual = availableFamilies.find((entry) => entry.major === expectedFamily.major)
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

if (failures.length) {
  console.error('Runtime JDK and game-version catalog contract failed:')
  for (const failure of failures) console.error(`- ${failure}`)
  process.exit(1)
}

console.log(
  `Runtime JDK and game-version catalog passed: ${archives.length} archives; visible majors ${availableFamilies.map((entry) => entry.major).join(', ')}.`,
)
