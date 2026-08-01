import { access, readdir, readFile, stat } from 'node:fs/promises'
import { join, relative } from 'node:path'
import { repositoryRoot, themeRoot } from './theme-paths.mjs'

const failures = []
const mailDirectory = join(themeRoot, 'data', 'mail')
const legacyDirectory = join(themeRoot, 'mail')
const foxMailPath = join(repositoryRoot, 'engine', 'classes', 'utils', 'FoxMail', '1.0.0', 'FoxMail.class.php')

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

for (const template of ['welcome.html', 'lostpass.html']) {
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

if (failures.length) {
  console.error('Mail template contract failed:')
  for (const failure of failures) console.error(`- ${failure}`)
  process.exit(1)
}

console.log(`Mail template contract passed: ${entries.length} templates are stored under data/mail.`)
