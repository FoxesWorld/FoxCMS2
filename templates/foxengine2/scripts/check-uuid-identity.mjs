import { readFile, readdir } from 'node:fs/promises'
import { extname, join, relative } from 'node:path'
import { repositoryRoot } from './theme-paths.mjs'

const failures = []
const phpFiles = []
const ignoredDirectories = new Set(['.git', 'node_modules', 'assets', 'templates_c', 'cache'])

async function walk(directory) {
  for (const entry of await readdir(directory, { withFileTypes: true })) {
    if (entry.isDirectory() && ignoredDirectories.has(entry.name)) continue
    const path = join(directory, entry.name)
    if (entry.isDirectory()) await walk(path)
    else if (extname(entry.name).toLowerCase() === '.php' && !entry.name.includes('.bak-')) phpFiles.push(path)
  }
}

await walk(repositoryRoot)
for (const path of phpFiles) {
  const rel = relative(repositoryRoot, path).replaceAll('\\', '/')
  const text = await readFile(path, 'utf8')

  for (const pattern of [
    /(?:UPDATE|DELETE)\s+`?users`?[\s\S]{0,500}?WHERE\s+`?(?:login|user_id)`?\s*=/gi,
    /(?:UPDATE|DELETE)\s+`?(?:usersession|userBadges|password_reset_tokens|user_hardware_reports)`?[\s\S]{0,500}?WHERE\s+`?(?:user|userMd5|userLogin|user_id)`?\s*=/gi,
  ]) {
    if (pattern.test(text)) failures.push(`mutable user identity used by a database mutation in ${rel}`)
  }

  if (/\b(?:userMd5|passMd5|userLogin)\b/.test(text)) {
    failures.push(`legacy user identity column referenced by runtime PHP in ${rel}`)
  }
  if (/INSERT\s+INTO\s+`usersession`[\s\S]{0,300}?\(`(?:user|userMd5|passMd5)`/i.test(text)) {
    failures.push(`launcher session is not keyed by userUuid in ${rel}`)
  }
  if (/USR_SUBFOLDER[^\n]{0,180}\$(?:login|username)/i.test(text)
      && !['authlib/AuthlibProfileService.class.php', 'engine/SystemRequests.class.php', 'scripts/migrate-user-storage.php'].includes(rel)) {
    failures.push(`user storage path depends on login in ${rel}`)
  }
  if (/md5\s*\([^\n]{0,120}\$(?:login|username)/i.test(text)
      && !['authlib/AuthlibProfileService.class.php', 'engine/SystemRequests.class.php', 'scripts/migrate-user-storage.php'].includes(rel)) {
    failures.push(`login-derived identifier remains in ${rel}`)
  }
}

const requiredContracts = new Map([
  ['engine/classes/identity/Uuid.class.php', ['Uuid', 'function v7', 'function normalize', 'function compact', 'function isRfcCompatible', 'function canonical', 'function databaseCandidates', 'self::normalize($value)']],
  ['engine/classes/identity/UserIdentityException.class.php', ['UserIdentityException']],
  ['engine/classes/modules/AuthReg/AuthReg.class.php', ['identity_migration_required', 'catch (UserIdentityException']],
  ['engine/classes/modules/AuthReg/actions/register.class.php', ['Uuid::v7()', "':uuid' => $userUuid"]],
  ['engine/classes/session/UserSession.class.php', ['function uuid()', 'Uuid::compact($uuid)', 'public function userFolder()', 'function persistAuthenticatedState', "unset($_SESSION[$field])"]],
  ['engine/classes/themes/ThemeRenderer.class.php', ["'uuid'", "'user' => $safeUser"]],
  ['engine/Application.class.php', ['Uuid::databaseCandidates($this->session->uuid())', "WHERE `uuid` IN ("]],
  ['engine/SystemRequests.class.php', ['function resolveMutationUserUuid()', '$this->resolveMutationUserUuid()', "request->string('userUuid')"]],
  ['engine/classes/modules/AuthReg/AuthReg.class.php', ['function updateUserTokenByUuid', 'Uuid::databaseCandidates($userUuid)']],
  ['engine/classes/services/PlayTimeService.php', ['Uuid::databaseCandidates($userUuid)', "WHERE `uuid` IN ("]],
  ['engine/classes/modules/UserSettings/client/views/SkinSettingsView.vue', ["bootstrapString(appBootstrap, 'uuid')", "data.set('userUuid', userUuid)", "deleteFile', type, userUuid"]],
  ['engine/classes/modules/UserSettings/client/views/ProfileView.vue', ['const viewerUuid', 'profile.value?.uuid']],
  ['engine/classes/modules/AdminPanel/client/useAdminPanel.ts', ["admPanel: 'updateUser', userUuid:"]],
  ['templates/foxengine2/src/foxEngine/admin/users/UserTable.vue', [':key="user.uuid"', 'selected?.uuid===user.uuid']],
  ['database/migrations/003_uuid_user_identity.sql', ['ux_users_uuid', 'userUuid', 'password_reset_tokens', 'user_hardware_reports']],
  ['database/migrations/004_repair_legacy_schema.sql', ['serversOnline', 'groupAssociation', 'usersession', 'password_reset_tokens', 'user_hardware_reports']],
])
for (const [relativePath, signatures] of requiredContracts) {
  const text = await readFile(join(repositoryRoot, relativePath), 'utf8')
  for (const signature of signatures) {
    if (!text.includes(signature)) failures.push(`UUID identity contract missing ${signature} in ${relativePath}`)
  }
}

const skinSettingsSource = await readFile(
  join(repositoryRoot, 'engine/classes/modules/UserSettings/client/views/SkinSettingsView.vue'),
  'utf8',
)
for (const forbidden of ["data.set('login'", "deleteFile', type, login", "skinPreview', login"]) {
  if (skinSettingsSource.includes(forbidden)) failures.push(`mutable login identity remains in skin settings: ${forbidden}`)
}
const profileViewSource = await readFile(
  join(repositoryRoot, 'engine/classes/modules/UserSettings/client/views/ProfileView.vue'),
  'utf8',
)
if (profileViewSource.includes('const viewerLogin')) {
  failures.push('profile ownership must be determined by viewer UUID, not login')
}

const userSessionSource = await readFile(
  join(repositoryRoot, 'engine/classes/session/UserSession.class.php'),
  'utf8',
)
if (/\$_SESSION\s*=\s*\$safeUser/.test(userSessionSource)) {
  failures.push('database session refresh must preserve CSRF and other security metadata')
}

const uuidSource = await readFile(join(repositoryRoot, 'engine/classes/identity/Uuid.class.php'), 'utf8')
if (!uuidSource.includes("private const CANONICAL_PATTERN = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/D'")) {
  failures.push('UUID canonical normalizer no longer accepts canonicalized legacy 128-bit identities')
}
if (!/function\s+isValid[\s\S]{0,240}?self::normalize\(\$value\)/.test(uuidSource)) {
  failures.push('Uuid::isValid must delegate to normalize so validation and normalization cannot diverge')
}
if (!/function\s+canonical[\s\S]{0,320}?self::fromCompact\(\$value\)/.test(uuidSource)) {
  failures.push('UUID compact-to-canonical compatibility path is missing')
}

if (failures.length) {
  console.error('UUID identity check failed:')
  for (const failure of [...new Set(failures)]) console.error(`- ${failure}`)
  process.exit(1)
}

console.log(`UUID identity passed: ${phpFiles.length} PHP files checked; mutations, sessions, storage and protocol identity are UUID-bound.`)
