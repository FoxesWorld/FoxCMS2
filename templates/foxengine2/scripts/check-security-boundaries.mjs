import { spawnSync } from 'node:child_process'
import { copyFile, mkdir, mkdtemp, readFile, rm, writeFile } from 'node:fs/promises'
import { homedir, tmpdir } from 'node:os'
import { join } from 'node:path'
import { repositoryRoot } from './theme-paths.mjs'

const read = (path) => readFile(join(repositoryRoot, path), 'utf8')
const files = {
  rootAutoload: await read('autoload.php'),
  environment: await read('src/FoxCMS/Shared/Environment/Environment.php'),
  responseHeaders: await read('src/FoxCMS/Shared/Http/ResponseHeaders.php'),
  apiResponse: await read('api/src/FoxCMS/Api/Core/JsonResponse.php'),
  engineResponse: await read('engine/classes/http/JsonResponse.class.php'),
  cors: await read('api/src/FoxCMS/Api/Bootstrap/BootstrapCorsPolicy.php'),
  manifest: await read('api/src/FoxCMS/Api/Bootstrap/ManifestController.php'),
  runtimeRequest: await read('api/src/FoxCMS/Api/Bootstrap/Runtime/RuntimeRequest.php'),
  runtimeController: await read('api/src/FoxCMS/Api/Launcher/RuntimeCatalogController.php'),
  fileCatalogController: await read('api/src/FoxCMS/Api/Launcher/FileCatalogController.php'),
  bridgeAuthenticator: await read('api/src/FoxCMS/Api/Launcher/BridgeAuthenticator.php'),
  authlib: await read('authlib/AuthlibRuntime.class.php'),
  rememberCookie: await read('engine/src/FoxCMS/Engine/Auth/RememberCookie.php'),
  authLifecycle: await read('engine/src/FoxCMS/Engine/Auth/AuthSessionLifecycle.php'),
  monitoring: await read('engine/classes/modules/Monitoring/Monitoring.class.php'),
  monitoringRecords: await read('engine/src/FoxCMS/Engine/Monitoring/MonitoringRecordStore.php'),
  newsImageEncoder: await read('api/src/FoxCMS/Api/News/ImageDataUrlEncoder.php'),
  runtimeErrorHandler: await read('engine/classes/support/RuntimeErrorHandler.class.php'),
  frontController: await read('index.php'),
  emergencyHandler: await read('engine/EmergencyRuntimeHandler.php'),
  throwableDiagnostic: await read('src/FoxCMS/Shared/Error/ThrowableDiagnostic.php'),
  apiFatalResponse: await read('api/src/FoxCMS/Api/Core/FatalResponse.php'),
  systemRequests: await read('engine/SystemRequests.class.php'),
  authManager: await read('engine/classes/modules/AuthReg/AuthReg.class.php'),
  contentApi: await read('api/src/FoxCMS/Api/Content/ContentApiApplication.php'),
  newsApi: await read('api/src/FoxCMS/Api/News/NewsApiApplication.php'),
  gameApi: await read('api/src/FoxCMS/Api/Game/GameApiApplication.php'),
  manifestController: await read('api/src/FoxCMS/Api/Bootstrap/ManifestController.php'),
  apiIndex: await read('api/index.php'),
  apiContentEntry: await read('api/content.php'),
  apiHealthEntry: await read('api/health.php'),
  apiNewsEntry: await read('api/news.php'),
  bootstrapManifestEntry: await read('api/bootstrap/manifest.php'),
  bootstrapDownloadEntry: await read('api/bootstrap/download.php'),
  launcherFileEntry: await read('api/launcher/file-catalog.php'),
  launcherRuntimeEntry: await read('api/launcher/runtime-catalog.php'),
  authlibConfig: await read('authlib/config.php'),
}

const failures = []
const requireTokens = (label, source, tokens) => {
  for (const token of tokens) if (!source.includes(token)) failures.push(`${label} is missing ${token}`)
}
const forbidTokens = (label, source, tokens) => {
  for (const token of tokens) if (source.includes(token)) failures.push(`${label} must not contain ${token}`)
}

requireTokens('Repository autoload', files.rootAutoload, [
  "'FoxCMS\\\\Shared\\\\'",
  "'FoxCMS\\\\Api\\\\'",
  "'FoxCMS\\\\Engine\\\\'",
])
requireTokens('Shared environment', files.environment, [
  'final class Environment',
  'public static function boot(',
  'public function boolean(',
  'public function integer(',
  'public function csv(',
])
requireTokens('Shared response header policy', files.responseHeaders, [
  'final class ResponseHeaders',
  "preg_match('/^[A-Za-z0-9-]{1,64}$/D'",
  'str_contains($value, "\\r")',
  'str_contains($value, "\\n")',
  'validateStatus(',
])
for (const [label, source] of [['API JSON response', files.apiResponse], ['Engine JSON response', files.engineResponse], ['Authlib response', files.authlib]]) {
  requireTokens(label, source, ['ResponseHeaders::begin('])
}
forbidTokens('Authlib response', files.authlib, ['header($name .'])

requireTokens('Bootstrap CORS', files.cors, [
  'private function requireAllowedWriteOrigin(',
  "if ($origin === '')",
  "!in_array($path, ['', '/'], true)",
  'cors_origin_not_allowed',
])
forbidTokens('Manifest CORS ownership', files.manifest, ["Access-Control-Allow-Origin: *"])

requireTokens('Runtime request boundary', files.runtimeRequest, [
  'public static function fromRequest(',
  'public static function fromArray(',
  "'runtime_request_client_version_invalid'",
])
forbidTokens('Runtime request boundary', `${files.runtimeRequest}
${files.runtimeController}`, ['$_GET'])

requireTokens('Launcher bridge authenticator', files.bridgeAuthenticator, [
  'private readonly Environment $environment',
  '$this->environment->string(',
  "'FOXESCRAFT_LAUNCHER_BRIDGE_TOKEN'",
  'hash_equals($configured, $provided)',
])
for (const [label, source] of [
  ['Launcher bridge authenticator', files.bridgeAuthenticator],
  ['Launcher file catalog', files.fileCatalogController],
  ['Launcher runtime catalog', files.runtimeController],
]) forbidTokens(label, source, ['foxEnv(', 'foxEnvInt(', 'foxEnvBool('])
requireTokens('Launcher file catalog environment ownership', files.fileCatalogController, [
  'Environment::boot($this->rootDirectory)',
  'new BridgeAuthenticator($environment)',
  "$environment->integer('FOXESCRAFT_LAUNCHER_CATALOG_TTL'",
  "$environment->string('FOXESCRAFT_GAME_FILES_DIR', 'game/')",
])
requireTokens('Launcher runtime catalog environment ownership', files.runtimeController, [
  'Environment::boot($this->rootDirectory)',
  'new BridgeAuthenticator($environment)',
  "$environment->boolean('FOXESCRAFT_DEBUG', false)",
])

requireTokens('Remember-cookie policy', files.rememberCookie, [
  "'secure' => $this->request->isSecure()",
  "'httponly' => true",
  "'samesite' => 'Lax'",
])
requireTokens('Authentication lifecycle cookie delegation', files.authLifecycle, [
  'new RememberCookie(',
  '$this->rememberCookie->set(',
  '$this->rememberCookie->clear()',
])

requireTokens('News image host policy', files.newsImageEncoder, [
  'public static function allowedHosts(',
  '$network->origin()',
  "$environment->csv('FOXESCRAFT_PUBLIC_HOSTS')",
])
forbidTokens('News image host policy', files.newsImageEncoder, ['$_SERVER'])

requireTokens('Monitoring record storage', files.monitoringRecords, [
  'flock($stream, LOCK_SH)',
  'flock($stream, LOCK_EX)',
  'ftruncate($stream, 0)',
  'fflush($stream)',
  'is_link($directory)',
])
forbidTokens('Monitoring domain service', files.monitoring, [
  'global $config',
  'file_put_contents(',
  "header('Content-Type",
])

for (const [label, source] of Object.entries(files)) {
  if (/\beval\s*\(/.test(source)) failures.push(`${label} contains eval()`)
  if (/\bunserialize\s*\(/.test(source)) failures.push(`${label} contains unserialize()`)
}

requireTokens('Runtime fatal response policy', files.runtimeErrorHandler, [
  'private static function emitThrowableResponse(',
  'private static function discardOutputBuffers(',
  'private static function publicMessage(',
  "'exception' => get_class($throwable)",
  "'fatal' => true",
])
forbidTokens('Runtime fatal response policy', files.runtimeErrorHandler, [
  "'Internal server error.'",
  "echo 'Internal server error.",
])
requireTokens('Pre-bootstrap front controller', files.frontController, [
  "require_once __DIR__ . '/engine/EmergencyRuntimeHandler.php'",
  'EmergencyRuntimeHandler::register(__DIR__)',
])
requireTokens('Pre-bootstrap fatal response policy', files.emergencyHandler, [
  'final class EmergencyRuntimeHandler',
  'set_exception_handler(',
  'register_shutdown_function(',
  "'phase' => 'bootstrap'",
  'sanitizeMessage(',
  'ob_end_clean()',
])

requireTokens('Shared throwable diagnostic', files.throwableDiagnostic, [
  'final class ThrowableDiagnostic',
  'public static function payload(',
  "'fatal' => true",
  "'exception' => $error::class",
  'sanitizeMessage(',
  '[credentials-redacted]',
])
requireTokens('API fatal response', files.apiFatalResponse, [
  'final class FatalResponse',
  'ThrowableDiagnostic::payload(',
  "'X-Request-ID' => $requestId",
])
requireTokens('Engine system request fatal boundary', files.systemRequests, [
  'ThrowableDiagnostic::payload(',
  "'error' => 'system_request_failed'",
])
requireTokens('Authentication fatal boundary', files.authManager, [
  'ThrowableDiagnostic::payload(',
  "'authentication_internal_error'",
])
for (const [label, source] of [
  ['Content API', files.contentApi],
  ['News API', files.newsApi],
  ['Game API', files.gameApi],
]) requireTokens(`${label} fatal boundary`, source, ['FatalResponse::send('])
requireTokens('Bootstrap manifest fatal boundary', files.manifestController, [
  'ThrowableDiagnostic::payload(',
  "'bootstrap_manifest_internal_error'",
])

for (const [label, source] of [
  ['API index', files.apiIndex],
  ['Content API entrypoint', files.apiContentEntry],
  ['Health API entrypoint', files.apiHealthEntry],
  ['News API entrypoint', files.apiNewsEntry],
  ['Bootstrap manifest entrypoint', files.bootstrapManifestEntry],
  ['Bootstrap download entrypoint', files.bootstrapDownloadEntry],
  ['Launcher file entrypoint', files.launcherFileEntry],
  ['Launcher runtime entrypoint', files.launcherRuntimeEntry],
  ['Authlib config entrypoint', files.authlibConfig],
]) requireTokens(`${label} emergency bootstrap`, source, [
  'EmergencyRuntimeHandler.php',
  'EmergencyRuntimeHandler::register(',
])

if (failures.length) {
  console.error('Security boundary contract failed:')
  for (const failure of failures) console.error(`- ${failure}`)
  process.exit(1)
}

const phpCandidates = [
  process.env.PHP_BINARY,
  join(homedir(), 'Documents', 'Take Some', 'Tools', 'toolbelt', 'third_party', 'php', 'php.exe'),
  'php',
].filter(Boolean)
let php = null
for (const candidate of [...new Set(phpCandidates)]) {
  const probe = spawnSync(candidate, ['--version'], { encoding: 'utf8', windowsHide: true })
  if (!probe.error && probe.status === 0) {
    php = candidate
    break
  }
}
if (!php) {
  console.error('Security boundary runtime tests require PHP CLI.')
  process.exit(1)
}
const runtimeContract = spawnSync(php, [join(repositoryRoot, 'scripts/check-security-boundaries.php')], {
  cwd: repositoryRoot,
  encoding: 'utf8',
  windowsHide: true,
})
if (runtimeContract.stdout) process.stdout.write(runtimeContract.stdout)
if (runtimeContract.stderr) process.stderr.write(runtimeContract.stderr)
if (runtimeContract.error || runtimeContract.status !== 0) process.exit(runtimeContract.status ?? 1)

const phpString = (value) => JSON.stringify(value)
const runtimeHandlerPath = join(repositoryRoot, 'engine/classes/support/RuntimeErrorHandler.class.php')
const assertFatalProcess = (label, result, expectedTokens) => {
  if (result.error) failures.push(`${label} could not run: ${result.error.message}`)
  for (const token of expectedTokens) {
    if (!result.stdout?.includes(token)) failures.push(`${label} did not expose ${token}`)
  }
  if (result.stdout?.includes('Internal server error.')) failures.push(`${label} returned a silent generic error`)
}

const visibilityDirectory = await mkdtemp(join(tmpdir(), 'foxcms-fatal-visibility-'))
try {
  const runPhpScenario = async (name, source) => {
    const scenarioPath = join(visibilityDirectory, `${name}.php`)
    await writeFile(scenarioPath, `<?php\n${source}\n`, 'utf8')
    return spawnSync(php, [scenarioPath], {
      cwd: repositoryRoot,
      encoding: 'utf8',
      windowsHide: true,
    })
  }

  const htmlFatal = await runPhpScenario('runtime-html', [
    `require ${phpString(runtimeHandlerPath)};`,
    `$_SERVER['REQUEST_URI'] = '/';`,
    `RuntimeErrorHandler::register(${phpString(repositoryRoot)}, false);`,
    `throw new RuntimeException('Visible runtime failure token=super-secret');`,
  ].join(' '))
  assertFatalProcess('Runtime HTML fatal response', htmlFatal, [
    'RuntimeException',
    'Visible runtime failure',
    'token=[redacted]',
    'Request ID',
  ])

  const jsonFatal = await runPhpScenario('runtime-json', [
    `require ${phpString(runtimeHandlerPath)};`,
    `$_SERVER['REQUEST_URI'] = '/api/test';`,
    `$_SERVER['HTTP_ACCEPT'] = 'application/json';`,
    `RuntimeErrorHandler::register(${phpString(repositoryRoot)}, false);`,
    `throw new LogicException('Visible JSON fatal password=hunter2');`,
  ].join(' '))
  try {
    const payload = JSON.parse(jsonFatal.stdout || '{}')
    if (payload.fatal !== true) failures.push('Runtime JSON fatal response is missing fatal=true')
    if (payload.exception !== 'LogicException') failures.push('Runtime JSON fatal response is missing exception class')
    if (payload.message !== 'Visible JSON fatal password=[redacted]') failures.push('Runtime JSON fatal response did not expose the sanitized message')
    if (!payload.requestId) failures.push('Runtime JSON fatal response is missing Request ID')
  } catch (error) {
    failures.push(`Runtime JSON fatal response is not valid JSON: ${error.message}`)
  }

  const shutdownFatal = await runPhpScenario('runtime-shutdown', [
    `require ${phpString(runtimeHandlerPath)};`,
    `$_SERVER['REQUEST_URI'] = '/';`,
    `RuntimeErrorHandler::register(${phpString(repositoryRoot)}, false);`,
    'undefined_fatal_function_for_fox_test();',
  ].join(' '))
  assertFatalProcess('Runtime shutdown fatal response', shutdownFatal, [
    'Error',
    'undefined_fatal_function_for_fox_test',
    'Request ID',
  ])

  const bootstrapDirectory = join(visibilityDirectory, 'bootstrap')
  await mkdir(join(bootstrapDirectory, 'engine'), { recursive: true })
  await copyFile(join(repositoryRoot, 'index.php'), join(bootstrapDirectory, 'index.php'))
  await copyFile(
    join(repositoryRoot, 'engine/EmergencyRuntimeHandler.php'),
    join(bootstrapDirectory, 'engine/EmergencyRuntimeHandler.php'),
  )
  await writeFile(join(bootstrapDirectory, 'engine/bootstrap.php'), '<?php this is invalid syntax !!!', 'utf8')
  const bootstrapFatal = spawnSync(php, [join(bootstrapDirectory, 'index.php')], {
    cwd: bootstrapDirectory,
    encoding: 'utf8',
    windowsHide: true,
  })
  assertFatalProcess('Pre-bootstrap parse fatal response', bootstrapFatal, [
    'ParseError',
    'syntax error',
    'engine',
    'Request ID',
  ])
} finally {
  await rm(visibilityDirectory, { recursive: true, force: true })
}

if (failures.length) {
  console.error('Security boundary runtime visibility failed:')
  for (const failure of failures) console.error(`- ${failure}`)
  process.exit(1)
}

console.log('Fatal error visibility tests passed: bootstrap, HTML, JSON and shutdown failures expose sanitized diagnostics.')
console.log('Security boundary contract passed: environment, headers, CORS, runtime input, cookies, monitoring storage and fatal error visibility use explicit validated policies.')
