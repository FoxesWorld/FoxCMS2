import { emoticonIndex } from './catalog'
import type { EmoticonDefinition } from './types'

const blockedSelector = [
  'code', 'pre', 'kbd', 'samp', 'script', 'style', 'textarea', 'input', 'select', 'option',
  '[contenteditable="true"]', '[data-emoticons="off"]', '.fox-emoticon-picker',
].join(',')
const shortcodePattern = /:([A-Za-z][A-Za-z0-9_-]{0,47}):/g

function image(document: Document, item: EmoticonDefinition): HTMLImageElement {
  const element = document.createElement('img')
  element.className = 'fox-emoticon'
  element.src = item.url
  element.alt = item.shortcode
  element.title = item.shortcode
  element.width = item.width
  element.height = item.height
  element.loading = 'lazy'
  element.decoding = 'async'
  element.draggable = false
  element.dataset.emoticon = item.name
  return element
}

function replaceTextNode(node: Text, index: ReadonlyMap<string, EmoticonDefinition>): void {
  const text = node.data
  if (!text.includes(':')) return

  const document = node.ownerDocument
  const fragment = document.createDocumentFragment()
  let cursor = 0
  let replacements = 0
  shortcodePattern.lastIndex = 0
  for (let match = shortcodePattern.exec(text); match; match = shortcodePattern.exec(text)) {
    const item = index.get(match[1].toLowerCase())
    if (!item) continue
    if (match.index > cursor) fragment.append(document.createTextNode(text.slice(cursor, match.index)))
    fragment.append(image(document, item))
    cursor = match.index + match[0].length
    replacements++
  }
  if (!replacements) return
  if (cursor < text.length) fragment.append(document.createTextNode(text.slice(cursor)))
  node.replaceWith(fragment)
}

export async function renderEmoticons(root: HTMLElement): Promise<void> {
  if (!root.isConnected && root.ownerDocument.documentElement !== root) return
  const index = await emoticonIndex()
  const walker = root.ownerDocument.createTreeWalker(root, NodeFilter.SHOW_TEXT, {
    acceptNode(node) {
      if (!(node instanceof Text) || !node.data.includes(':')) return NodeFilter.FILTER_REJECT
      const parent = node.parentElement
      return parent && !parent.closest(blockedSelector)
        ? NodeFilter.FILTER_ACCEPT
        : NodeFilter.FILTER_REJECT
    },
  })
  const nodes: Text[] = []
  for (let node = walker.nextNode(); node; node = walker.nextNode()) {
    if (node instanceof Text) nodes.push(node)
  }
  for (const node of nodes) replaceTextNode(node, index)
}
