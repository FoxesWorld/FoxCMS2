import { readFile } from 'node:fs/promises'
import { join } from 'node:path'
import { repositoryRoot } from './theme-paths.mjs'

const locale = JSON.parse(await readFile(
  join(repositoryRoot, 'engine', 'client', 'i18n', 'locales', 'ru-RU.json'),
  'utf8',
))

function normalize(value) {
  return String(value)
    .replace(/\{\{[\s\S]*?\}\}/g, ' ')
    .replace(/<[^>]+>/g, ' ')
    .replace(/&quot;/g, '"')
    .replace(/&lt;/g, '<')
    .replace(/&gt;/g, '>')
    .replace(/&amp;/g, '&')
    .replace(/\s+/g, ' ')
    .trim()
}

function candidateFragments(expected) {
  const plain = normalize(expected)
  if (!plain) return []
  const fragments = [plain]
  const markupParts = String(expected)
    .replace(/\{\{[\s\S]*?\}\}/g, '|')
    .replace(/<[^>]+>/g, '|')
    .split('|')
    .map(normalize)
    .filter((value) => /[\p{L}\p{N}]/u.test(value))
  for (const part of markupParts) if (!fragments.includes(part)) fragments.push(part)
  return fragments
}

function decodeEntities(value) {
  return String(value)
    .replace(/&quot;/g, '"')
    .replace(/&lt;/g, '<')
    .replace(/&gt;/g, '>')
    .replace(/&amp;/g, '&')
}

function keysForExactValue(value) {
  const decoded = decodeEntities(value).trim()
  return Object.entries(locale)
    .filter(([, translation]) => String(translation).trim() === decoded)
    .map(([key]) => key)
}

function keysForFragment(fragment) {
  const escaped = fragment.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')
  const wordPattern = fragment.length >= 4
    ? new RegExp(`(?:^|[^\p{L}\p{N}])${escaped}(?:$|[^\p{L}\p{N}])`, 'iu')
    : null
  return Object.entries(locale)
    .filter(([, value]) => {
      const normalized = normalize(value)
      return normalized === fragment
        || normalized.startsWith(`${fragment} `)
        || normalized.startsWith(`${fragment}{`)
        || Boolean(wordPattern?.test(normalized))
        || (fragment.length >= 8 && normalized.includes(fragment))
    })
    .map(([key]) => key)
}

export function includesLocalized(source, expected) {
  if (source.includes(expected)) return true
  const exactKeys = keysForExactValue(expected)
  if (exactKeys.some((key) => source.includes(`t('${key}'`))) return true
  const fragments = candidateFragments(expected)
  return fragments.length > 0 && fragments.every((fragment) => {
    const keys = keysForFragment(fragment)
    return keys.some((key) => source.includes(`t('${key}'`))
  })
}

export function localeContains(expected) {
  const normalized = normalize(expected)
  return Object.values(locale).some((value) => normalize(value) === normalized)
}
