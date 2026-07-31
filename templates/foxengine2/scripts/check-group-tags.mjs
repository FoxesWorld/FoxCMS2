import { readFile, readdir } from 'node:fs/promises'
import { extname, join, relative } from 'node:path'
import { repositoryRoot, themeRoot } from './theme-paths.mjs'

const failures = []
const contracts = [
  ['engine/classes/repositories/GroupRepository.class.php', [
    'public static function normalizeTag(',
    'public function find(string $groupTag)',
    'public function resolveTag(',
    'public function all(): array',
  ]],
  ['engine/classes/session/UserSession.class.php', [
    'public function group(): string',
    "return $this->group() === 'admin';",
    "'groupTag' => 'guest'",
  ]],
  ['engine/classes/modules/AdminPanel/AdminOptions.class.php', [
    "'groupTag'",
    "'groups' => $this->groupRepository->all()",
    'normalizeGroupList(',
    'стабильным идентификатором',
  ]],
  ['templates/foxengine2/src/foxEngine/admin/users/UserEditor.vue', [
    '<select v-model="draft.groupTag"',
    ':value="group.groupTag"',
  ]],
  ['templates/foxengine2/src/foxEngine/admin/servers/ServerEditor.vue', [
    '<select v-model="draft.serverGroups" multiple',
    ':value="group.groupTag"',
  ]],
  ['database/migrations/006_group_tag_identity.sql', [
    'ADD COLUMN `groupTag`',
    'UPDATE `users` AS `user`',
    'UPDATE `regCodes` AS `registrationCode`',
    "SET `allowedGroups` = '[\"admin\"]'",
  ]],
]

for (const [relativePath, signatures] of contracts) {
  const text = await readFile(join(repositoryRoot, relativePath), 'utf8')
  for (const signature of signatures) {
    if (!text.includes(signature)) failures.push(`${relativePath} missing group-tag contract: ${signature}`)
  }
}

async function sourceFiles(directory) {
  const output = []
  for (const entry of await readdir(directory, { withFileTypes: true })) {
    if (['node_modules', 'runtime', '.vite'].includes(entry.name)) continue
    const path = join(directory, entry.name)
    if (entry.isDirectory()) output.push(...await sourceFiles(path))
    else if (['.php', '.ts', '.vue', '.json', '.mjs'].includes(extname(entry.name))) output.push(path)
  }
  return output
}

const activeRoots = [join(repositoryRoot, 'engine'), join(themeRoot, 'src')]
for (const activeRoot of activeRoots) {
  for (const file of await sourceFiles(activeRoot)) {
    const text = await readFile(file, 'utf8')
    const name = relative(repositoryRoot, file).replaceAll('\\', '/')
    if (text.includes('user_group')) failures.push(`${name} still uses numeric user_group identity`)
    if (name.includes('/client/') || name.startsWith('templates/')) {
      if (text.includes('groupNum')) failures.push(`${name} exposes legacy groupNum to the client`)
      if (text.includes('groupType')) failures.push(`${name} exposes legacy groupType to the client`)
    }
  }
}

const moduleManifest = JSON.parse(await readFile(join(repositoryRoot, 'engine/data/modules.json'), 'utf8'))
const adminModule = moduleManifest.find((module) => module?.name === 'AdminPanel')
const userSettingsModule = moduleManifest.find((module) => module?.name === 'UserSettings')
if (JSON.stringify(adminModule?.groups) !== '["admin"]') failures.push('AdminPanel must remain restricted to the admin tag')
if (userSettingsModule?.groups !== '*') failures.push('UserSettings must remain available to every canonical group tag')

for (const manifestPath of [join(repositoryRoot, 'engine/data/modules.json'), join(themeRoot, 'frontend.json')]) {
  const manifest = JSON.parse(await readFile(manifestPath, 'utf8'))
  const inspect = (value, location = 'root') => {
    if (Array.isArray(value)) {
      if (location.endsWith('.groups') && value.some((group) => typeof group !== 'string')) {
        failures.push(`${relative(repositoryRoot, manifestPath)} ${location} contains a non-string group identity`)
      }
      value.forEach((entry, index) => inspect(entry, `${location}[${index}]`))
      return
    }
    if (value && typeof value === 'object') {
      for (const [key, entry] of Object.entries(value)) inspect(entry, `${location}.${key}`)
    }
  }
  inspect(manifest)
}

const userEditor = await readFile(join(themeRoot, 'src/foxEngine/admin/users/UserEditor.vue'), 'utf8')
if (/draft\.(?:user_group|groupNum)/.test(userEditor) || /type="number"[^>]*group/i.test(userEditor)) {
  failures.push('UserEditor still offers a numeric group editor')
}
const serverEditor = await readFile(join(themeRoot, 'src/foxEngine/admin/servers/ServerEditor.vue'), 'utf8')
if (/textarea[^>]*serverGroups|serverGroups[^\n]*textarea/i.test(serverEditor)) {
  failures.push('ServerEditor still exposes server groups as free-form text')
}

if (failures.length) {
  console.error('Group-tag identity check failed:')
  for (const failure of failures) console.error(`- ${failure}`)
  process.exit(1)
}
console.log('Group-tag identity passed: ACLs, sessions, registration, administration and server access use canonical group tags.')
