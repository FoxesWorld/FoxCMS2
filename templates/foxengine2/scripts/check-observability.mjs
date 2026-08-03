import { readFile } from 'node:fs/promises'
import { join } from 'node:path'
import { repositoryRoot, themeRoot } from './theme-paths.mjs'

const failures = []
const files = {
  request: join(repositoryRoot, 'engine', 'classes', 'http', 'HttpRequest.class.php'),
  telemetry: join(repositoryRoot, 'engine', 'classes', 'observability', 'RequestTelemetry.class.php'),
  query: join(repositoryRoot, 'engine', 'classes', 'observability', 'LogQueryService.class.php'),
  logger: join(repositoryRoot, 'engine', 'classes', 'syslib', 'syslog'),
  application: join(repositoryRoot, 'engine', 'Application.class.php'),
  modules: join(repositoryRoot, 'engine', 'ModulesLoader.class.php'),
  logUi: join(themeRoot, 'src', 'foxEngine', 'admin', 'Logs.vue'),
  adminClient: join(repositoryRoot, 'engine', 'classes', 'modules', 'AdminPanel', 'client', 'useAdminPanel.ts'),
  styles: join(themeRoot, 'assets', 'css', 'legacy-continuation.css'),
}
const source = Object.fromEntries(await Promise.all(
  Object.entries(files).map(async ([name, path]) => [name, await readFile(path, 'utf8')]),
))

for (const token of [
  'TELEMETRY_ACTION_FIELDS',
  'telemetryContext()',
  'telemetryOperation()',
  "'requestInputKeys'",
  "'uploadFields'",
]) if (!source.request.includes(token)) failures.push(`HttpRequest telemetry is missing ${token}`)

for (const token of [
  'self::requestMessage($status, $duration)',
  "'requestChannel'",
]) if (!source.telemetry.includes(token)) failures.push(`RequestTelemetry is missing ${token}`)

for (const forbidden of [
  "'http.request.started'",
  "'HTTP request accepted.'",
  "'HTTP request completed.'",
  "'Module loading phase started.'",
  "'Module loaded.'",
  "'Session synchronized with the authoritative user state.'",
]) {
  const combined = Object.values(source).join('\n')
  if (combined.includes(forbidden)) failures.push(`Empty lifecycle log message remains: ${forbidden}`)
}

for (const token of [
  "'loadedModules' => $loadedNames",
  "'moduleTimingsMs' => $moduleTimings",
  "'skippedModules' => $skipped",
  'Module phase %s loaded %d of %d candidates',
]) if (!source.modules.includes(token)) failures.push(`Module summary is missing ${token}`)

for (const token of [
  "'sessionState' => $sessionState",
  'Session refreshed from the users table for %s [%s]',
  'session_identity_not_found',
]) if (!source.application.includes(token)) failures.push(`Session diagnostics are missing ${token}`)

for (const token of [
  "'httpMethod' =>",
  "'actorLogin' =>",
  "'requestChannel' =>",
  "'exception' =>",
]) if (!source.query.includes(token)) failures.push(`LogQueryService does not expose ${token}`)

for (const token of [
  'admin-log-line__meta',
  'Диагностический контекст',
  'Request ID',
  'Correlation ID',
  'entry.deviation',
  'entry.exception',
  'entry.httpMethod',
  'entry.actorLogin',
]) if (!source.logUi.includes(token)) failures.push(`Admin log UI is missing ${token}`)

for (const token of [
  'export interface LogDeviation',
  'export interface LogException',
  'requestChannel?: string',
  'context?: Record<string, unknown>',
]) if (!source.adminClient.includes(token)) failures.push(`LogEntry contract is missing ${token}`)

for (const token of [
  '.admin-log-line__header',
  '.admin-log-line__meta',
  '.admin-log-line__details',
  '.admin-log-deviation',
  '.admin-log-exception',
]) if (!source.styles.includes(token)) failures.push(`Log UI styles are missing ${token}`)

if (!source.logger.includes("'exception',") || !source.logger.includes("'requestChannel',")) {
  failures.push('Logger must promote exception and request routing metadata')
}

if (failures.length) {
  console.error('Observability contract failed:')
  for (const failure of failures) console.error(`- ${failure}`)
  process.exit(1)
}
console.log('Observability contract passed: request, actor, handler, module, outcome and diagnostic context are visible without logging sensitive payload values.')
