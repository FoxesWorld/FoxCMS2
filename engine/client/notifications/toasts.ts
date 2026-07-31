import { shallowReactive } from 'vue'

export type ToastStatus = 'success' | 'error' | 'warning' | 'info'

export interface ToastItem {
  id: number
  status: ToastStatus
  message: string
  duration: number
}

export const toasts = shallowReactive<ToastItem[]>([])
const timers = new Map<number, number>()
const FLASH_KEY = 'foxescraft.toast.flash'
let sequence = 0

function statusOf(value: unknown): ToastStatus {
  const status = String(value ?? '').toLowerCase()
  if (status === 'success') return 'success'
  if (status === 'error' || status === 'danger') return 'error'
  if (status === 'warning' || status === 'warn') return 'warning'
  return 'info'
}

function defaultDuration(status: ToastStatus): number {
  return status === 'error' ? 8000 : status === 'warning' ? 6500 : status === 'success' ? 4500 : 5500
}

function arm(toast: ToastItem): void {
  window.clearTimeout(timers.get(toast.id))
  if (toast.duration <= 0) return
  timers.set(toast.id, window.setTimeout(() => dismissToast(toast.id), toast.duration))
}

export function showToast(message: string, status: ToastStatus = 'info', duration = defaultDuration(status)): number {
  const text = message.trim()
  if (!text) return 0
  const duplicate = toasts.find((toast) => toast.message === text && toast.status === status)
  if (duplicate) { arm(duplicate); return duplicate.id }
  const toast: ToastItem = { id: ++sequence, status, message: text, duration }
  toasts.push(toast)
  while (toasts.length > 5) dismissToast(toasts[0].id)
  arm(toast)
  return toast.id
}

function payloadToast(payload: unknown): { message: string; status: ToastStatus } | null {
  if (!payload || typeof payload !== 'object' || Array.isArray(payload)) return null
  const value = payload as Record<string, unknown>
  if (typeof value.message !== 'string' || value.message.trim() === '') return null
  return { message: value.message, status: statusOf(value.type) }
}

export function notifyPayload(payload: unknown): boolean {
  const toast = payloadToast(payload)
  if (!toast) return false
  showToast(toast.message, toast.status)
  return true
}

export function toastFeedback<T>(payload: T): T {
  notifyPayload(payload)
  return payload
}

export function queuePayloadToast(payload: unknown): boolean {
  const toast = payloadToast(payload)
  if (!toast) return false
  try { window.sessionStorage.setItem(FLASH_KEY, JSON.stringify({ ...toast, createdAt: Date.now() })) } catch {}
  return true
}

export function restoreQueuedToast(): void {
  let raw = ''
  try { raw = window.sessionStorage.getItem(FLASH_KEY) ?? ''; window.sessionStorage.removeItem(FLASH_KEY) } catch {}
  if (!raw) return
  try {
    const value = JSON.parse(raw) as { message?: unknown; status?: unknown; createdAt?: unknown }
    if (typeof value.message === 'string' && Date.now() - Number(value.createdAt) < 60_000) {
      showToast(value.message, statusOf(value.status))
    }
  } catch {}
}

export function dismissToast(id: number): void {
  window.clearTimeout(timers.get(id))
  timers.delete(id)
  const index = toasts.findIndex((toast) => toast.id === id)
  if (index >= 0) toasts.splice(index, 1)
}

export function pauseToast(id: number): void {
  window.clearTimeout(timers.get(id))
  timers.delete(id)
}

export function resumeToast(id: number): void {
  const toast = toasts.find((item) => item.id === id)
  if (toast) arm(toast)
}
