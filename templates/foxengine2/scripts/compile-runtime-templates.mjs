import { mkdir, readFile, readdir, rm, writeFile } from 'node:fs/promises'
import { basename, dirname, join } from 'node:path'
import { fileURLToPath } from 'node:url'
import { compileRuntimeTemplateSource } from './runtime-template-compiler.mjs'

const themeRoot = dirname(dirname(fileURLToPath(import.meta.url)))
const themeName = basename(themeRoot)
const bridgeUrl = `/templates/${encodeURIComponent(themeName)}/assets/runtime/vue-runtime.js`
const outputDirectory = join(themeRoot, 'assets', 'runtime', 'templates')
const definitions = [
  ['profile-settings', 'userOptions/ProfileSettings.tpl'],
  ['admin-panel', 'userOptions/AdminPanel.tpl'],
  ['static-content', 'pages/templates/StaticContent.tpl'],
  ['start-game', 'pages/templates/StartGame.tpl'],
  ['badges', 'pages/templates/Badges.tpl'],
  ['badge', 'pages/templates/Badge.tpl'],
  ['achievements', 'pages/templates/Achievements.tpl'],
  ['achievement-statistics', 'pages/templates/achievements/StatisticsTree.tpl'],
  ['achievement-tree-node', 'pages/templates/achievements/TreeNode.tpl'],
  ['achievement-profile-panel', 'pages/templates/achievements/ProfilePanel.tpl'],
]

await mkdir(outputDirectory, { recursive: true })
for (const entry of await readdir(outputDirectory, { withFileTypes: true })) {
  if (entry.isFile() && entry.name.endsWith('.js')) await rm(join(outputDirectory, entry.name))
}
for (const [id, relativeFile] of definitions) {
  const source = await readFile(join(themeRoot, relativeFile), 'utf8')
  const compiled = compileRuntimeTemplateSource(source, id, bridgeUrl)
  const target = join(outputDirectory, `${id}.${compiled.revision}.js`)
  await writeFile(target, compiled.module, 'utf8')
  console.log(`${relativeFile} -> assets/runtime/templates/${basename(target)}`)
}
