import { reactive } from 'vue'
import { appBootstrap } from '@/app/context'
import {
  bootstrapBoolean,
  bootstrapEndpoint,
  bootstrapNumber,
  bootstrapString,
  type BootstrapValue,
} from '@/domain/bootstrap'
import { t } from '@/i18n'

export type NotificationSeverity = 'info' | 'success' | 'warning' | 'security'

export interface UserNotification {
  id: number
  type: string
  severity: NotificationSeverity
  title: string
  message: string
  actionUrl: string | null
  payload: Record<string, BootstrapValue>
  createdAt: number
  readAt: number | null
  unread: boolean
}

interface NotificationPageResponse {
  notifications?: unknown
  unreadCount?: unknown
  hasMore?: unknown
  nextBeforeId?: unknown
  message?: unknown
}

interface NotificationMutationResponse {
  unreadCount?: unknown
  message?: unknown
}

export interface NotificationCenterState {
  items: UserNotification[]
  unreadCount: number
  loading: boolean
  loadingMore: boolean
  markingAll: boolean
  initialized: boolean
  error: string
  hasMore: boolean
  nextBeforeId: number
  lastUpdatedAt: number
}

const POLL_INTERVAL_MS = 60_000

export const notificationCenter = reactive<NotificationCenterState>({
  items: [],
  unreadCount: Math.max(0, Math.floor(bootstrapNumber(appBootstrap, 'notificationsUnread', 0))),
  loading: false,
  loadingMore: false,
  markingAll: false,
  initialized: false,
  error: '',
  hasMore: false,
  nextBeforeId: 0,
  lastUpdatedAt: 0,
})

let pollingTimer: number | null = null
let refreshPromise: Promise<void> | null = null

function record(value: unknown): Record<string, BootstrapValue> {
  return value && typeof value === 'object' && !Array.isArray(value)
    ? value as Record<string, BootstrapValue>
    : {}
}

function integer(value: unknown, fallback = 0): number {
  const number = Number(value)
  return Number.isFinite(number) ? Math.max(0, Math.floor(number)) : fallback
}

function optionalTimestamp(value: unknown): number | null {
  if (value === null || value === undefined || value === '') return null
  const timestamp = integer(value, -1)
  return timestamp >= 0 ? timestamp : null
}

function normalizeSeverity(value: unknown): NotificationSeverity {
  return value === 'success' || value === 'warning' || value === 'security' ? value : 'info'
}

function normalizeNotification(value: unknown): UserNotification | null {
  if (!value || typeof value !== 'object' || Array.isArray(value)) return null
  const row = value as Record<string, unknown>
  const id = integer(row.id)
  const title = String(row.title ?? '').trim()
  const message = String(row.message ?? '').trim()
  if (id <= 0 || !title || !message) return null

  const readAt = optionalTimestamp(row.readAt)
  const actionUrl = typeof row.actionUrl === 'string' && row.actionUrl.startsWith('/')
    ? row.actionUrl
    : null

  return {
    id,
    type: String(row.type ?? 'system.notice').trim() || 'system.notice',
    severity: normalizeSeverity(row.severity),
    title,
    message,
    actionUrl,
    payload: record(row.payload),
    createdAt: integer(row.createdAt),
    readAt,
    unread: readAt === null,
  }
}

function normalizeList(value: unknown): UserNotification[] {
  if (!Array.isArray(value)) return []
  const unique = new Map<number, UserNotification>()
  for (const item of value) {
    const notification = normalizeNotification(item)
    if (notification) unique.set(notification.id, notification)
  }
  return Array.from(unique.values()).sort((left, right) => right.id - left.id)
}

function responseMessage(payload: unknown, fallback: string): string {
  if (payload && typeof payload === 'object' && !Array.isArray(payload)) {
    const message = (payload as Record<string, unknown>).message
    if (typeof message === 'string' && message.trim()) return message.trim()
  }
  return fallback
}

function setUnreadCount(value: unknown): void {
  const count = integer(value)
  notificationCenter.unreadCount = count
  appBootstrap.user.notificationsUnread = count
}

async function request<T>(action: string, parameters: Record<string, string | number> = {}): Promise<T> {
  const endpoint = bootstrapEndpoint(appBootstrap, 'actions')
  if (!endpoint) throw new Error(t('engine.notifications.center.001'))

  const body = new URLSearchParams({ user_doaction: action })
  for (const [key, value] of Object.entries(parameters)) body.set(key, String(value))

  const csrfToken = bootstrapString(appBootstrap, 'csrfToken')
  const login = bootstrapString(appBootstrap, 'login')
  if (csrfToken) body.set('csrf_token', csrfToken)
  if (login) body.set('user', login)

  let response: Response
  try {
    response = await fetch(endpoint, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8' },
      body,
    })
  } catch {
    throw new Error(t('engine.notifications.center.002'))
  }

  const text = await response.text()
  let payload: unknown = null
  try {
    payload = text ? JSON.parse(text) : null
  } catch {
    throw new Error(t('engine.notifications.center.003'))
  }

  if (!response.ok) {
    throw new Error(responseMessage(payload, t('engine.notifications.center.004', [response.status])))
  }
  return payload as T
}

export async function refreshNotifications(options: { silent?: boolean } = {}): Promise<void> {
  if (!bootstrapBoolean(appBootstrap, 'isLogged')) return
  if (refreshPromise) return refreshPromise

  const execute = async (): Promise<void> => {
    if (!options.silent) notificationCenter.loading = true
    notificationCenter.error = ''
    try {
      const payload = await request<NotificationPageResponse>('getNotifications', { limit: 20 })
      notificationCenter.items = normalizeList(payload.notifications)
      setUnreadCount(payload.unreadCount)
      notificationCenter.hasMore = payload.hasMore === true
      notificationCenter.nextBeforeId = integer(payload.nextBeforeId)
      notificationCenter.initialized = true
      notificationCenter.lastUpdatedAt = Date.now()
    } catch (error) {
      notificationCenter.error = error instanceof Error ? error.message : t('engine.notifications.center.005')
    } finally {
      notificationCenter.loading = false
      refreshPromise = null
    }
  }

  refreshPromise = execute()
  return refreshPromise
}

export async function loadMoreNotifications(): Promise<void> {
  if (notificationCenter.loadingMore || !notificationCenter.hasMore || notificationCenter.nextBeforeId <= 0) return
  notificationCenter.loadingMore = true
  notificationCenter.error = ''
  try {
    const payload = await request<NotificationPageResponse>('getNotifications', {
      limit: 20,
      beforeId: notificationCenter.nextBeforeId,
    })
    const merged = new Map(notificationCenter.items.map((item) => [item.id, item]))
    for (const item of normalizeList(payload.notifications)) merged.set(item.id, item)
    notificationCenter.items = Array.from(merged.values()).sort((left, right) => right.id - left.id)
    setUnreadCount(payload.unreadCount)
    notificationCenter.hasMore = payload.hasMore === true
    notificationCenter.nextBeforeId = integer(payload.nextBeforeId)
  } catch (error) {
    notificationCenter.error = error instanceof Error ? error.message : t('engine.notifications.center.005')
  } finally {
    notificationCenter.loadingMore = false
  }
}

export async function markNotificationRead(notificationId: number): Promise<void> {
  const notification = notificationCenter.items.find((item) => item.id === notificationId)
  if (!notification?.unread) return

  try {
    const payload = await request<NotificationMutationResponse>('markNotificationRead', { notificationId })
    notification.readAt = Math.floor(Date.now() / 1000)
    notification.unread = false
    setUnreadCount(payload.unreadCount)
  } catch (error) {
    notificationCenter.error = error instanceof Error ? error.message : t('engine.notifications.center.006')
    throw error
  }
}

export async function markAllNotificationsRead(): Promise<void> {
  if (notificationCenter.markingAll || notificationCenter.unreadCount <= 0) return
  notificationCenter.markingAll = true
  notificationCenter.error = ''
  try {
    const payload = await request<NotificationMutationResponse>('markAllNotificationsRead')
    const readAt = Math.floor(Date.now() / 1000)
    for (const notification of notificationCenter.items) {
      if (notification.unread) {
        notification.unread = false
        notification.readAt = readAt
      }
    }
    setUnreadCount(payload.unreadCount)
  } catch (error) {
    notificationCenter.error = error instanceof Error ? error.message : t('engine.notifications.center.007')
  } finally {
    notificationCenter.markingAll = false
  }
}

function handleWindowFocus(): void {
  void refreshNotifications({ silent: true })
}

function handleVisibilityChange(): void {
  if (document.visibilityState === 'visible') void refreshNotifications({ silent: true })
}

export function startNotificationPolling(): void {
  if (!bootstrapBoolean(appBootstrap, 'isLogged') || pollingTimer !== null) return
  void refreshNotifications({ silent: notificationCenter.initialized })
  pollingTimer = window.setInterval(() => {
    if (document.visibilityState === 'visible') void refreshNotifications({ silent: true })
  }, POLL_INTERVAL_MS)
  window.addEventListener('focus', handleWindowFocus)
  document.addEventListener('visibilitychange', handleVisibilityChange)
}

export function stopNotificationPolling(): void {
  if (pollingTimer !== null) window.clearInterval(pollingTimer)
  pollingTimer = null
  window.removeEventListener('focus', handleWindowFocus)
  document.removeEventListener('visibilitychange', handleVisibilityChange)
}

export function notificationIcon(type: string): string {
  if (type === 'security.login') return 'fa-solid fa-shield'
  if (type === 'security.password_changed') return 'fa-solid fa-key'
  if (type === 'achievement.badge_awarded') return 'fa-solid fa-award'
  if (type === 'achievement.badge_revoked') return 'fa-solid fa-ban'
  if (type === 'achievement.reward_claimed') return 'fa-solid fa-trophy'
  if (type === 'account.welcome_back') return 'fa-solid fa-house'
  return 'fa-solid fa-bell'
}

export function formatNotificationTime(timestamp: number, compact = false): string {
  if (timestamp <= 0) return ''
  return new Intl.DateTimeFormat('ru-RU', compact
    ? { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' }
    : { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(timestamp * 1000))
}
