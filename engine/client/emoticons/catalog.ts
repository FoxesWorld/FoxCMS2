import { loadEmoticons } from '@/content/contentData'
import type { EmoticonCatalog, EmoticonDefinition, EmoticonTextPart } from './types'

const shortcodePattern = /:([A-Za-z][A-Za-z0-9_-]{0,47}):/g
let catalogPromise: Promise<EmoticonCatalog> | null = null
let indexPromise: Promise<ReadonlyMap<string, EmoticonDefinition>> | null = null

export function emoticonCatalog(): Promise<EmoticonCatalog> {
  return catalogPromise ??= loadEmoticons().catch((error: unknown) => {
    catalogPromise = null
    indexPromise = null
    throw error
  })
}

export function emoticonIndex(): Promise<ReadonlyMap<string, EmoticonDefinition>> {
  return indexPromise ??= emoticonCatalog().then((catalog) => {
    const index = new Map<string, EmoticonDefinition>()
    for (const category of catalog.categories) {
      for (const item of category.items) index.set(item.name.toLowerCase(), item)
    }
    return index
  })
}

export function tokenizeEmoticons(
  text: string,
  index: ReadonlyMap<string, EmoticonDefinition>,
): EmoticonTextPart[] {
  if (!text.includes(':')) return [{ type: 'text', value: text }]

  const parts: EmoticonTextPart[] = []
  let cursor = 0
  shortcodePattern.lastIndex = 0
  for (let match = shortcodePattern.exec(text); match; match = shortcodePattern.exec(text)) {
    const emoticon = index.get(match[1].toLowerCase())
    if (!emoticon) continue
    if (match.index > cursor) parts.push({ type: 'text', value: text.slice(cursor, match.index) })
    parts.push({ type: 'emoticon', value: emoticon })
    cursor = match.index + match[0].length
  }
  if (cursor < text.length) parts.push({ type: 'text', value: text.slice(cursor) })
  return parts.length ? parts : [{ type: 'text', value: text }]
}
