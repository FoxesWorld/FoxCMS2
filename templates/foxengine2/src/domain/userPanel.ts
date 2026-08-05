import type { BootstrapValue, FoxesCraftBootstrap } from '@engine/domain/bootstrap'

export interface UserPanelState {
  messagesUrl: string
  notificationsUrl: string
  messagesUnread: number
  notificationsUnread: number
}

const MESSAGE_COUNTER_KEYS = [
  'messagesUnread',
  'unreadMessages',
  'unreadMessagesCount',
  'messagesCount',
] as const

const NOTIFICATION_COUNTER_KEYS = [
  'notificationsUnread',
  'unreadNotifications',
  'unreadNotificationsCount',
  'notificationsCount',
] as const

function bootstrapRecordValue(bootstrap: FoxesCraftBootstrap, key: string): BootstrapValue | undefined {
  return bootstrap.user[key] ?? bootstrap.replaceData[key]
}

function firstUnreadCount(bootstrap: FoxesCraftBootstrap, keys: readonly string[]): number {
  for (const key of keys) {
    const value = bootstrapRecordValue(bootstrap, key)
    if (value === undefined || value === null || value === '') continue

    const count = Number(value)
    if (Number.isFinite(count)) return Math.max(0, Math.floor(count))
  }

  return 0
}

function firstEndpoint(bootstrap: FoxesCraftBootstrap, names: readonly string[], fallback: string): string {
  for (const name of names) {
    const value = bootstrap.frontend.endpoints[name] ?? bootstrap.engine.endpoints[name]
    if (typeof value === 'string' && value.trim()) return value.trim()
  }

  return fallback
}

export function resolveUserPanelState(bootstrap: FoxesCraftBootstrap): UserPanelState {
  return {
    messagesUrl: firstEndpoint(bootstrap, ['messages', 'messenger', 'messagesPage'], '/go/messenger'),
    notificationsUrl: firstEndpoint(bootstrap, ['notifications', 'notificationsPage'], '/#/notifications'),
    messagesUnread: firstUnreadCount(bootstrap, MESSAGE_COUNTER_KEYS),
    notificationsUnread: firstUnreadCount(bootstrap, NOTIFICATION_COUNTER_KEYS),
  }
}

export function formatUnreadCounter(count: number): string {
  return count > 99 ? '99+' : String(Math.max(0, Math.floor(count)))
}
