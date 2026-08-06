import { access, readFile, readdir } from 'node:fs/promises'
import { extname, join, relative } from 'node:path'
import { fileURLToPath } from 'node:url'
import { themeName } from './theme-paths.mjs'

const repositoryRoot = fileURLToPath(new URL('../../..', import.meta.url))
const forbiddenPaths = [
  'frontend',
  'plugins',
  `templates/${themeName}/app`,
  `templates/${themeName}/data/pages`,
  `templates/${themeName}/foxEngine`,
  'engine/classes/modules/tmp',
  'engine/TODO',
  'engine/init.php',
  'engine/initHelper.php',
  'engine/RequestHandler.class.php',
  'engine/classes/modules/Smarty',
  'engine/classes/syslib/smarty',
  'engine/classes/utils/SSV',
  'engine/classes/utils/SessionManager',
]
const forbiddenDirectoryNames = new Set(['todo', 'tmp', 'old', 'backup', 'unused', 'deprecated', 'templates_c'])
const backupPattern = /(?:\.bak-|\.(?:old|bak|backup|php1|php-old|js--|png--)$)/i
const textExtensions = new Set(['.php', '.js', '.mjs', '.ts', '.vue', '.html', '.json', '.css', '.tpl', '.ftpl'])
const approvedRuntimeTemplates = new Set([
  `templates/${themeName}/userOptions/ProfileSettings.tpl`,
  `templates/${themeName}/userOptions/AdminPanel.tpl`,
  `templates/${themeName}/pages/templates/StaticContent.tpl`,
  `templates/${themeName}/pages/templates/StartGame.tpl`,
  `templates/${themeName}/pages/templates/Badges.tpl`,
  `templates/${themeName}/pages/templates/Badge.tpl`,
  `templates/${themeName}/pages/templates/Achievements.tpl`,
  `templates/${themeName}/pages/templates/achievements/StatisticsTree.tpl`,
  `templates/${themeName}/pages/templates/achievements/TreeNode.tpl`,
  `templates/${themeName}/pages/templates/achievements/ProfilePanel.tpl`,
])
const forbiddenSignatures = [
  'init::$usrArray',
  'init::$usrFiles',
  'RequestHandler::$REQUEST',
  'extends init',
  'new RequestHandler',
  'FoxesModule%>',
  'Smarty',
  'secureKey',
  'foxesHash',
]
const failures = []

for (const rel of forbiddenPaths) {
  try { await access(join(repositoryRoot, rel)); failures.push(`forbidden path exists: ${rel}`) }
  catch { /* expected */ }
}

async function walk(directory) {
  for (const entry of await readdir(directory, { withFileTypes: true })) {
    if (entry.name === '.git' || entry.name === 'node_modules' || entry.name === '.vite') continue
    const path = join(directory, entry.name)
    const rel = relative(repositoryRoot, path).replaceAll('\\', '/')
    if (entry.isDirectory()) {
      if (forbiddenDirectoryNames.has(entry.name.toLowerCase())) failures.push(`legacy directory exists: ${rel}`)
      await walk(path)
      continue
    }

    if (backupPattern.test(entry.name)) failures.push(`backup artifact exists: ${rel}`)
    if (['.tpl', '.ftpl'].includes(extname(entry.name).toLowerCase()) && !approvedRuntimeTemplates.has(rel)) {
      failures.push(`unapproved legacy template exists: ${rel}`)
    }
    if (!textExtensions.has(extname(entry.name).toLowerCase())) continue
    if (rel.startsWith(`templates/${themeName}/scripts/`)) continue

    const text = await readFile(path, 'utf8').catch(() => '')
    for (const signature of forbiddenSignatures) {
      const codeMirrorFiveModeName = signature === 'Smarty'
        && new RegExp(`^templates/${themeName}/assets/runtime/chunks/CodeEditor-[^/]+\\.js$`).test(rel)
      if (!codeMirrorFiveModeName && text.includes(signature)) failures.push(`forbidden signature ${signature} in ${rel}`)
    }
  }
}
await walk(repositoryRoot)

if (failures.length) {
  console.error('Removed-runtime gate failed:')
  for (const failure of [...new Set(failures)]) console.error(`- ${failure}`)
  process.exit(1)
}
console.log('Removed-runtime gate passed: only approved runtime userOptions/page TPL files exist; legacy runtimes and archive artifacts remain absent.')
