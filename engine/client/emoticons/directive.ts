import type { Directive } from 'vue'
import { renderEmoticons } from './render'

const pending = new WeakSet<HTMLElement>()
let warned = false

function schedule(element: HTMLElement): void {
  if (pending.has(element)) return
  pending.add(element)
  queueMicrotask(() => {
    pending.delete(element)
    void renderEmoticons(element).catch((error: unknown) => {
      if (warned) return
      warned = true
      console.warn('[FoxesCraft] Emoticon catalog is unavailable', error)
    })
  })
}

export const emoticonsDirective: Directive<HTMLElement> = {
  mounted: schedule,
  updated: schedule,
}
