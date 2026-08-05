import { readFile, readdir, stat } from 'node:fs/promises'
import { dirname, join, resolve } from 'node:path'
import { fileURLToPath } from 'node:url'

const themeRoot = resolve(dirname(fileURLToPath(import.meta.url)), '..')
const repositoryRoot = resolve(themeRoot, '..', '..')
const directory = join(themeRoot, 'data', 'emoticons')
const failures = []

const manifest = JSON.parse(await readFile(join(directory, 'emoji.json'), 'utf8'))
if (manifest.schema !== 1 || !Array.isArray(manifest.categories) || manifest.categories.length === 0) {
  failures.push('data/emoticons/emoji.json must use schema 1 and contain categories')
}

const declared = new Set()
const categoryIds = new Set()
for (const category of manifest.categories ?? []) {
  if (!/^[a-z][a-z0-9_]{0,47}$/.test(category.id ?? '') || categoryIds.has(category.id)) {
    failures.push(`invalid or duplicate emoticon category: ${String(category.id)}`)
    continue
  }
  categoryIds.add(category.id)
  if (typeof category.label !== 'string' || !category.label.trim()) failures.push(`category ${category.id} has no label`)
  if (!Array.isArray(category.items) || category.items.length === 0) failures.push(`category ${category.id} is empty`)
  for (const item of category.items ?? []) {
    const name = item?.name
    if (!/^[A-Za-z][A-Za-z0-9_-]{0,47}$/.test(name ?? '')) {
      failures.push(`invalid emoticon name in ${category.id}: ${String(name)}`)
      continue
    }
    const key = name.toLowerCase()
    if (declared.has(key)) failures.push(`duplicate emoticon name: ${name}`)
    declared.add(key)
    const path = join(directory, category.id, `${name}.png`)
    const info = await stat(path).catch(() => null)
    if (!info?.isFile() || info.size < 1 || info.size > 2 * 1024 * 1024) failures.push(`invalid emoticon image: ${category.id}/${name}.png`)
  }
}

for (const entry of await readdir(directory, { withFileTypes: true })) {
  if (!entry.isDirectory()) continue
  for (const file of await readdir(join(directory, entry.name), { withFileTypes: true })) {
    if (!file.isFile() || !file.name.endsWith('.png')) continue
    const name = file.name.slice(0, -4).toLowerCase()
    if (!declared.has(name)) failures.push(`undeclared emoticon image: ${entry.name}/${file.name}`)
  }
}

const api = await readFile(join(repositoryRoot, 'api', 'src', 'FoxCMS', 'Api', 'Content', 'ContentApiApplication.php'), 'utf8')
const repository = await readFile(join(repositoryRoot, 'engine', 'classes', 'themes', 'ThemeEmoticonRepository.class.php'), 'utf8')
const renderer = await readFile(join(repositoryRoot, 'engine', 'client', 'emoticons', 'render.ts'), 'utf8')
for (const [label, source, tokens] of [
  ['content API', api, ["'emoticons' =>", 'ThemeEmoticonRepository']],
  ['emoticon repository', repository, ["'syntax' => ':emoji:'", "'shortcode' => ':' . $name . ':'"]],
  ['client renderer', renderer, ['shortcodePattern', 'blockedSelector', 'fox-emoticon']],
]) {
  for (const token of tokens) if (!source.includes(token)) failures.push(`${label} is missing ${token}`)
}

if (failures.length) {
  console.error('emoticon contract failed:')
  for (const failure of failures) console.error(`- ${failure}`)
  process.exit(1)
}
console.log(`emoticon contract passed: ${declared.size} images across ${categoryIds.size} categories.`)
