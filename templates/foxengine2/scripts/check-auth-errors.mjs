import { readFile } from 'node:fs/promises'
import { join } from 'node:path'
import { repositoryRoot } from './theme-paths.mjs'
import { includesLocalized } from './i18n-test-utils.mjs'

const failures = []

async function source(relativePath) {
  return readFile(join(repositoryRoot, relativePath), 'utf8')
}

const contracts = new Map([
  ['engine/classes/modules/AuthReg/AuthFailure.class.php', [
    'final class AuthFailure',
    'function publicCode()',
    'function status()',
    'function field()',
    'function payload()',
  ]],
  ['engine/classes/modules/AuthReg/AuthInputValidator.class.php', [
    'login_contains_forbidden_characters',
    'email_contains_forbidden_characters',
    'password_contains_forbidden_characters',
    'passwords_do_not_match',
    'registration_code_contains_forbidden_characters',
    'Логин содержит недопустимые символы.',
    'Пароль содержит недопустимые символы.',
  ]],
  ['engine/classes/modules/AuthReg/AuthReg.class.php', [
    'catch (AuthFailure $failure)',
    'catch (Throwable $error)',
    'authentication_method_not_allowed',
    'csrf_token_invalid',
    'handleExpectedFailure(',
    'handleUnexpectedFailure(',
    "'requestId'",
    "'correlationId'",
  ]],
  ['engine/classes/modules/AuthReg/actions/authorise.class.php', [
    'private const DUMMY_PASSWORD_HASH',
    '$antiBrute->assertAllowed()',
    'invalid_credentials',
    'identity_migration_required',
    'passwordRehashed',
  ]],
  ['engine/classes/modules/AuthReg/actions/register.class.php', [
    'public function register(): array',
    'login_already_used',
    'email_already_used',
    'registration_completed_login_required',
    'isIntegrityViolation(',
    'auth.registration.session_initialization_failed',
  ]],
  ['engine/classes/syslib/antiBrute', [
    'public function assertAllowed(): void',
    'authentication_rate_limited',
    "headers: ['Retry-After'",
    'private Logger $logger',
  ]],
  ['engine/client/api/FoxesApiClient.ts', [
    'foxesApiFailureFeedback(',
    'field?: unknown',
    'correlationId?: unknown',
  ]],
  ['engine/classes/modules/AuthReg/client/views/AuthView.vue', [
    'foxesApiFailureFeedback(',
    'focusFormField(failure.field)',
  ]],
  ['engine/classes/modules/AuthReg/client/views/RegisterView.vue', [
    'foxesApiFailureFeedback(',
    'focusFormField(failure.field)',
    'Логин содержит недопустимые символы.',
    'Пароль содержит недопустимые символы.',
  ]],
])

for (const [relativePath, signatures] of contracts) {
  const text = await source(relativePath)
  for (const signature of signatures) {
    if (!includesLocalized(text, signature)) failures.push(`${relativePath} missing authentication contract: ${signature}`)
  }
}

const authManager = await source('engine/classes/modules/AuthReg/AuthReg.class.php')
if (authManager.indexOf('catch (AuthFailure $failure)') > authManager.indexOf('catch (Throwable $error)')) {
  failures.push('AuthFailure must be caught before Throwable')
}
if (!/in_array\(\$action, \['auth', 'register', 'logout'\], true\)[\s\S]{0,180}CsrfToken::validate/.test(authManager)) {
  failures.push('mutating authentication actions are not protected by centralized CSRF validation')
}

const authorise = await source('engine/classes/modules/AuthReg/actions/authorise.class.php')
const register = await source('engine/classes/modules/AuthReg/actions/register.class.php')
for (const [name, text] of [['authorise', authorise], ['register', register]]) {
  if (/functions::jsonAnswer|JsonResponse::|\bexit\s*\(/.test(text.replace(/if \(!defined\('auth'\)\)[\s\S]*?\}\s*/m, ''))) {
    failures.push(`${name} action terminates HTTP responses inside business logic`)
  }
}
for (const sensitiveLoggerExpression of [
  /logger->[A-Za-z]+\([^;]{0,500}\$password\b/s,
  /logger->[A-Za-z]+\([^;]{0,500}\$email\b/s,
  /logger->[A-Za-z]+\([^;]{0,500}\$clientIp\b/s,
]) {
  if (sensitiveLoggerExpression.test(authorise) || sensitiveLoggerExpression.test(register)) {
    failures.push('authentication logger receives a sensitive raw credential or identity value')
  }
}

const antiBrute = await source('engine/classes/syslib/antiBrute')
if (antiBrute.includes("new Logger('AuthLog')")) {
  failures.push('anti-brute creates a separate AuthLog instead of using the shared lastlog logger')
}
if (antiBrute.includes('functions::jsonAnswer')) {
  failures.push('anti-brute still terminates responses instead of throwing AuthFailure')
}

const apiClient = await source('engine/client/api/FoxesApiClient.ts')
if (!apiClient.includes('message: error.message.trim() || fallback')) {
  failures.push('frontend API error adapter does not preserve the server-provided message')
}

if (failures.length) {
  console.error('Authentication error contract check failed:')
  for (const failure of [...new Set(failures)]) console.error(`- ${failure}`)
  process.exit(1)
}

console.log('Authentication error contracts passed: typed failures, exact field messages, centralized responses, anti-brute and frontend propagation are present.')
