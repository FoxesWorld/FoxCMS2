<script setup lang="ts">
import { reactive } from 'vue'
import type { CSSProperties } from 'vue'
import { dismissToast, pauseToast, resumeToast, toasts } from '@engine/notifications/toasts'
import type { ToastItem, ToastStatus } from '@engine/notifications/toasts'

const offsets = reactive<Record<number, number>>({})
const dragging = reactive(new Set<number>())
const starts = new Map<number, { x: number; time: number; width: number }>()
const labels: Record<ToastStatus, string> = { success: 'Готово', error: 'Ошибка', warning: 'Внимание', info: 'Информация' }
const marks: Record<ToastStatus, string> = { success: '✓', error: '!', warning: '!', info: 'i' }

function styleFor(toast: ToastItem): CSSProperties {
  const offset = offsets[toast.id] ?? 0
  return {
    '--toast-x': `${offset}px`,
    '--toast-opacity': String(Math.max(.28, 1 - Math.abs(offset) / 360)),
  } as CSSProperties
}

function begin(event: PointerEvent, toast: ToastItem): void {
  if (event.pointerType === 'mouse' && event.button !== 0) return
  const target = event.currentTarget as HTMLElement
  target.setPointerCapture(event.pointerId)
  starts.set(toast.id, { x: event.clientX, time: performance.now(), width: target.offsetWidth })
  dragging.add(toast.id)
  offsets[toast.id] = 0
  pauseToast(toast.id)
}

function move(event: PointerEvent, toast: ToastItem): void {
  const start = starts.get(toast.id)
  if (!start) return
  const offset = event.clientX - start.x
  offsets[toast.id] = offset
  if (Math.abs(offset) > 8) event.preventDefault()
}

function finish(event: PointerEvent, toast: ToastItem): void {
  const start = starts.get(toast.id)
  if (!start) return
  const offset = offsets[toast.id] ?? 0
  const elapsed = Math.max(1, performance.now() - start.time)
  const dismiss = Math.abs(offset) > start.width * .28 || (Math.abs(offset) > 38 && Math.abs(offset) / elapsed > .65)
  starts.delete(toast.id)
  dragging.delete(toast.id)
  if (dismiss) { dismissToast(toast.id); return }
  offsets[toast.id] = 0
  resumeToast(toast.id)
}

function cancel(toast: ToastItem): void {
  starts.delete(toast.id)
  dragging.delete(toast.id)
  offsets[toast.id] = 0
  resumeToast(toast.id)
}
</script>

<template>
  <aside class="toast-viewport" aria-label="Уведомления">
    <TransitionGroup name="toast" tag="div" class="toast-stack">
      <div v-for="toast in toasts" :key="toast.id" class="toast-slot">
        <article
          class="toast"
          :class="[`toast--${toast.status}`, { 'is-dragging': dragging.has(toast.id) }]"
          :style="styleFor(toast)"
          :role="toast.status === 'error' ? 'alert' : 'status'"
          @pointerdown="begin($event, toast)"
          @pointermove="move($event, toast)"
          @pointerup="finish($event, toast)"
          @pointercancel="cancel(toast)"
          @mouseenter="pauseToast(toast.id)"
          @mouseleave="dragging.has(toast.id) || resumeToast(toast.id)"
        >
          <span class="toast__mark" aria-hidden="true">{{ marks[toast.status] }}</span>
          <span class="toast__content"><strong>{{ labels[toast.status] }}</strong><span>{{ toast.message }}</span></span>
          <button type="button" class="toast__close" aria-label="Закрыть уведомление" @pointerdown.stop @click="dismissToast(toast.id)">×</button>
        </article>
      </div>
    </TransitionGroup>
  </aside>
</template>
