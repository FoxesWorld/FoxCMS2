import { readFile } from 'node:fs/promises'
import { join } from 'node:path'
import { repositoryRoot, themeRoot } from './theme-paths.mjs'
import { includesLocalized } from './i18n-test-utils.mjs'

const files = {
  migration: join(repositoryRoot, 'database', 'migrations', '024_user_browser_sessions.sql'),
  service: join(repositoryRoot, 'engine', 'classes', 'services', 'UserSessionRegistryService.class.php'),
  session: join(repositoryRoot, 'engine', 'classes', 'session', 'UserSession.class.php'),
  authorise: join(repositoryRoot, 'engine', 'classes', 'modules', 'AuthReg', 'actions', 'authorise.class.php'),
  auth: join(repositoryRoot, 'engine', 'classes', 'modules', 'AuthReg', 'AuthReg.class.php'),
  actions: join(repositoryRoot, 'engine', 'classes', 'modules', 'UserSettings', 'UserActions.class.php'),
  application: join(repositoryRoot, 'engine', 'src', 'FoxCMS', 'Engine', 'Application', 'UserSessionSynchronizer.php'),
  health: join(repositoryRoot, 'engine', 'classes', 'services', 'HealthCheckService.class.php'),
  manifest: join(themeRoot, 'frontend.json'),
  userBlock: join(themeRoot, 'src', 'UserBlock.vue'),
  router: join(repositoryRoot, 'engine', 'client', 'router', 'index.ts'),
  client: join(repositoryRoot, 'engine', 'client', 'sessions', 'userSessions.ts'),
  view: join(repositoryRoot, 'engine', 'client', 'views', 'DevicesView.vue'),
}
const values = await Promise.all(Object.values(files).map((path) => readFile(path, 'utf8')))
const source = Object.fromEntries(Object.keys(files).map((key, index) => [key, values[index]]))
const failures = []
const requireText = (label, text, tokens) => {
  for (const token of tokens) if (!includesLocalized(text, token)) failures.push(`${label} is missing ${token}`)
}
const rejectText = (label, text, tokens) => {
  for (const token of tokens) if (text.includes(token)) failures.push(`${label} must not contain ${token}`)
}

requireText('Browser session migration', source.migration, [
  'CREATE TABLE IF NOT EXISTS `userBrowserSessions`',
  '`sessionUuid` CHAR(36)',
  '`rememberDigest` CHAR(64)',
  '`sessionType` VARCHAR(16)',
  '`idleExpiresAt` BIGINT UNSIGNED',
  '`revokedAt` BIGINT UNSIGNED NULL',
  'FOREIGN KEY (`userUuid`) REFERENCES `users` (`uuid`) ON DELETE CASCADE',
])
rejectText('Browser session persistence', source.migration, ['phpSessionId', 'rawToken', '`token` VARCHAR'])

requireText('Browser session registry service', source.service, [
  'final class UserSessionRegistryService',
  'public function issueForAuthenticatedSession(',
  'public function restoreRememberedSession(',
  'public function synchronizeCurrentSession(',
  'public function activeSessions(',
  'public function revokeSession(',
  'public function revokeCurrentSession(',
  "RememberToken::digest($token)",
  "'remembered'",
  "'short'",
  '`expiresAt` = :expiresAt, `idleExpiresAt` = :idleExpiresAt',
  '`expiresAt` > :expiresNow',
  '`idleExpiresAt` > :idleNow',
  "':expiresNow' => $now",
  "':idleNow' => $now",
])
rejectText('Native PDO session queries', source.service, [
  '`expiresAt` = :expiresAt, `idleExpiresAt` = :expiresAt',
])
const activeProjection = source.service.slice(
  source.service.indexOf('public function activeSessions'),
  source.service.indexOf('public function revokeSession'),
)
rejectText('Active-session API projection', activeProjection, ['rememberDigest', 'userAgent', 'password', 'token'])
rejectText('Active-session native PDO placeholders', activeProjection, [':now'])

requireText('Native session binding', source.session, [
  'public function browserSessionUuid(): string',
  'public function browserSessionType(): string',
  'public function bindBrowserSession(',
  "$_SESSION['_fox_browser_session_uuid']",
  "unset($_SESSION['_fox_browser_session_uuid'], $_SESSION['_fox_browser_session_type'])",
])
requireText('Password login session issue', source.authorise, [
  'new UserSessionRegistryService($this->db, $this->config)',
  '->issueForAuthenticatedSession($this->session, $userUuid, $remember, $context)',
  'UserSessionRegistryService::isSchemaMissing($error)',
])
requireText('Remembered session restore and logout', source.auth, [
  '->restoreRememberedSession($token, $this->session, $context)',
  "'legacy_migrated'",
  '->revokeCurrentSession($this->session)',
  'private function setRememberCookie(',
])
requireText('Request lifecycle synchronization', source.application, [
  'final class UserSessionSynchronizer',
  '$this->synchronizeBrowserSession();',
  '->synchronizeCurrentSession(',
])
requireText('Session list API', source.actions, [
  "'getActiveSessions' => 'getActiveSessions'",
  "'getActiveSessions' => $this->getActiveSessions()",
  'private function getActiveSessions(): never',
  '->activeSessions(',
  '024_user_browser_sessions.sql',
  "'revokeActiveSession' => 'revokeActiveSession'",
  "'revokeActiveSession' => $this->revokeActiveSession()",
  'private function revokeActiveSession(): never',
  'CsrfToken::requireValid($this->request->csrfToken())',
  '->revokeSession($userUuid, $sessionUuid, $currentSessionUuid)',
])
requireText('Health check schema', source.health, ["'userBrowserSessions' => ["])

const manifest = JSON.parse(source.manifest)
const devicesRoute = manifest.routes.find((route) => route.name === 'devices')
if (!devicesRoute || devicesRoute.path !== '/devices' || devicesRoute.view !== 'DevicesView' || devicesRoute.auth !== 'user') {
  failures.push('Frontend manifest must expose authenticated /devices route through DevicesView')
}
const devicesNavigation = manifest.navigation.find((item) => item.area === 'account' && item.intent === 'devices')
if (devicesNavigation) {
  failures.push('Active devices must be rendered dynamically instead of being declared as a static account navigation item')
}
requireText('Dynamic profile dropdown devices', source.userBlock, [
  "refreshUserSessions({ silent: userSessions.initialized })",
  'profile-dropdown__devices',
  'devicesPreview',
  'userSessions.activeCount',
  'sessionDeviceIcon(session)',
  'openDevicesPage',
])
requireText('Devices route navigation', source.userBlock, [
  "import { useRouter } from 'vue-router'",
  'const router = useRouter()',
  "router.push({ name: 'devices' })",
])
rejectText('Devices route navigation', source.userBlock, [
  'window.location.assign(panelState.devicesUrl)',
])
requireText('Devices route fallback', source.router, [
  "!routes.some((route) => route.name === 'devices')",
  "viewModules.get('DevicesView')",
  "path: '/devices'",
  "name: 'devices'",
  "t('engine.router.index.005')",
])
requireText('Device sessions client', source.client, [
  "user_doaction: 'getActiveSessions'",
  "credentials: 'same-origin'",
  'export const userSessions = reactive',
  'export async function refreshUserSessions',
  "user_doaction: 'revokeActiveSession'",
  'export async function revokeUserSession',
  'revokingSessionUuid',
])
requireText('My devices page', source.view, [
  'Мои устройства',
  'Текущее устройство',
  'Последняя активность',
  'Действует до',
  'session.remembered',
  'userSessions.activeCount',
  'deactivateSession(session)',
  'device-session-card__revoke',
  'userSessions.revokingSessionUuid',
])

if (failures.length) {
  console.error('User browser session contract failed:')
  for (const failure of failures) console.error(`- ${failure}`)
  process.exit(1)
}
console.log('User browser session contract passed: active short and remembered sessions are registered per device and loaded dynamically into the profile dropdown and exposed through the authenticated My devices page without leaking credentials; every remote session can be individually revoked while the current session remains protected.')
