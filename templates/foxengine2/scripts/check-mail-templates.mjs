import { access, readdir, readFile, stat } from 'node:fs/promises'
import { join, relative } from 'node:path'
import { repositoryRoot, themeRoot } from './theme-paths.mjs'

const failures = []
const mailDirectory = join(themeRoot, 'data', 'mail')
const legacyDirectory = join(themeRoot, 'mail')
const foxMailPath = join(repositoryRoot, 'engine', 'classes', 'utils', 'FoxMail', '1.0.0', 'FoxMail.class.php')
const legacyMailerPath = join(repositoryRoot, 'engine', 'classes', 'utils', 'FoxMail', '1.0.0', 'Mailer.class.php')
const phpmailerPath = join(repositoryRoot, 'engine', 'classes', 'utils', 'FoxMail', '1.0.0', 'vendor', 'PHPMailer', 'PHPMailer.php')
const adminMailControllerPath = join(repositoryRoot, 'engine', 'src', 'FoxCMS', 'Engine', 'Admin', 'AdminMailController.php')
const adminRouterPath = join(repositoryRoot, 'engine', 'src', 'FoxCMS', 'Engine', 'Admin', 'AdminActionRouterFactory.php')
const adminPanelClientPath = join(repositoryRoot, 'engine', 'classes', 'modules', 'AdminPanel', 'client', 'useAdminPanel.ts')
const registerPath = join(repositoryRoot, 'engine', 'classes', 'modules', 'AuthReg', 'actions', 'register.class.php')
const lostPasswordPath = join(repositoryRoot, 'engine', 'classes', 'modules', 'UserSettings', 'actions', 'lostpassword.class.php')

try {
  const directoryStat = await stat(mailDirectory)
  if (!directoryStat.isDirectory()) failures.push(`mail data path is not a directory: ${relative(repositoryRoot, mailDirectory)}`)
} catch {
  failures.push(`mail data directory missing: ${relative(repositoryRoot, mailDirectory)}`)
}

try {
  await access(legacyDirectory)
  failures.push(`legacy theme mail directory still exists: ${relative(repositoryRoot, legacyDirectory)}`)
} catch { /* Expected. */ }

for (const template of ['welcome.html', 'lostpass.html', 'verify-email.html', 'smtp-test.html']) {
  try {
    const content = await readFile(join(mailDirectory, template), 'utf8')
    if (!content.includes('<html') || !content.includes('</html>')) failures.push(`invalid mail HTML template: ${template}`)
  } catch {
    failures.push(`required mail template missing: ${template}`)
  }
}

const entries = await readdir(mailDirectory, { withFileTypes: true }).catch(() => [])
for (const entry of entries) {
  if (!entry.isFile() || !/^[A-Za-z0-9_-]+\.html$/.test(entry.name)) {
    failures.push(`unsupported mail data entry: ${entry.name}`)
  }
}


try {
  await access(legacyMailerPath)
  failures.push('obsolete PHPMailer 5 monolith still exists: engine/classes/utils/FoxMail/1.0.0/Mailer.class.php')
} catch { /* Expected after PHPMailer 7 migration. */ }

try {
  const phpmailer = await readFile(phpmailerPath, 'utf8')
  if (!phpmailer.includes("const VERSION = '7.1.1';")) failures.push('bundled PHPMailer runtime must be 7.1.1')
} catch {
  failures.push('bundled PHPMailer 7.1.1 runtime is missing')
}

const foxMail = await readFile(foxMailPath, 'utf8')
for (const token of [
  "CURRENT_TEMPLATE . 'data' . DIRECTORY_SEPARATOR . 'mail'",
  'Mail template directory not found:',
  'Mail template not found: ',
  '@file_get_contents($path)',
]) {
  if (!foxMail.includes(token)) failures.push(`FoxMail data contract missing ${token}`)
}
if (foxMail.includes("CURRENT_TEMPLATE . 'mail'")) failures.push('FoxMail still reads templates from the legacy root mail directory')

if (!foxMail.includes('public function sendContent(')) failures.push('FoxMail dynamic campaign sender is missing')

const adminMailController = await readFile(adminMailControllerPath, 'utf8')
for (const token of [
  'public function audience(): never',
  'public function sendCampaign(): never',
  'expectedCount',
  'confirmed',
  '$count > 250',
  'sendContent(',
  'mergeTags(',
]) {
  if (!adminMailController.includes(token)) failures.push(`admin mail campaign contract missing ${token}`)
}

const adminRouter = await readFile(adminRouterPath, 'utf8')
for (const action of ['mailAudience', 'sendMailCampaign']) {
  if (!adminRouter.includes(`register('${action}'`)) failures.push(`admin mail action is not registered: ${action}`)
}

const adminPanelClient = await readFile(adminPanelClientPath, 'utf8')
for (const action of ["admPanel: 'mailAudience'", "admPanel: 'sendMailCampaign'"]) {
  if (!adminPanelClient.includes(action)) failures.push(`admin mail client action is missing: ${action}`)
}


for (const [label, path] of [
  ['registration', registerPath],
  ['password recovery', lostPasswordPath],
]) {
  const source = await readFile(path, 'utf8')
  for (const token of [
    "$this->config['siteSettings']",
    'new FoxMail(true, $settings)',
    '$sent = $mailer->send(',
    'if (!$sent)',
  ]) {
    if (!source.includes(token)) failures.push(`${label} mail flow missing runtime SMTP contract: ${token}`)
  }
}

if (failures.length) {
  console.error('Mail template contract failed:')
  for (const failure of failures) console.error(`- ${failure}`)
  process.exit(1)
}

console.log(`Mail template contract passed: ${entries.length} templates are stored under data/mail.`)
