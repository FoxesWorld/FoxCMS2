import { reactive } from 'vue'
import { appBootstrap } from '@/app/context'
import {
  bootstrapBoolean,
  bootstrapEndpoint,
  bootstrapString,
} from '@/domain/bootstrap'
import { t } from '@/i18n'

export type BrowserSessionType = 'short' | 'remembered'

export interface ActiveUserSession {
  sessionUuid: string
  current: boolean
  sessionType: BrowserSessionType
  remembered: boolean
  ipAddress: string
  browser: string
  operatingSystem: string
  deviceLabel: string
  locationLabel: string
  createdAt: number
  lastSeenAt: number
  expiresAt: number
}

interface ActiveSessionsResponse {
  sessions?: unknown
  activeCount?: unknown
  message?: unknown
}

export interface UserSessionsState {
  items: ActiveUserSession[]
  activeCount: number
  loading: boolean
  initialized: boolean
  error: string
  lastUpdatedAt: number
  revokingSessionUuid: string
}

export const userSessions = reactive<UserSessionsState>({
  items: [],
  activeCount: 0,
  loading: false,
  initialized: false,
  error: '',
  lastUpdatedAt: 0,
  revokingSessionUuid: '',
})

let refreshPromise: Promise<void> | null = null

function integer(value: unknown): number {
  const number = Number(value)
  return Number.isFinite(number) ? Math.max(0, Math.floor(number)) : 0
}

function normalizeSession(value: unknown): ActiveUserSession | null {
  if (!value || typeof value !== 'object' || Array.isArray(value)) return null
  const row = value as Record<string, unknown>
  const sessionUuid = String(row.sessionUuid ?? '').trim()
  const deviceLabel = String(row.deviceLabel ?? '').trim()
  if (!sessionUuid || !deviceLabel) return null
  const sessionType: BrowserSessionType = row.sessionType === 'remembered' ? 'remembered' : 'short'
  return {
    sessionUuid,
    current: row.current === true,
    sessionType,
    remembered: sessionType === 'remembered',
    ipAddress: String(row.ipAddress ?? '').trim(),
    browser: String(row.browser ?? '').trim(),
    operatingSystem: String(row.operatingSystem ?? '').trim(),
    deviceLabel,
    locationLabel: String(row.locationLabel ?? '').trim(),
    createdAt: integer(row.createdAt),
    lastSeenAt: integer(row.lastSeenAt),
    expiresAt: integer(row.expiresAt),
  }
}

function normalizeList(value: unknown): ActiveUserSession[] {
  if (!Array.isArray(value)) return []
  const unique = new Map<string, ActiveUserSession>()
  for (const item of value) {
    const session = normalizeSession(item)
    if (session) unique.set(session.sessionUuid, session)
  }
  return Array.from(unique.values()).sort((left, right) =>
    Number(right.current) - Number(left.current) || right.lastSeenAt - left.lastSeenAt)
}

function responseMessage(payload: unknown, fallback: string): string {
  if (payload && typeof payload === 'object' && !Array.isArray(payload)) {
    const message = (payload as Record<string, unknown>).message
    if (typeof message === 'string' && message.trim()) return message.trim()
  }
  return fallback
}

async function requestActiveSessions(): Promise<ActiveSessionsResponse> {
  const endpoint = bootstrapEndpoint(appBootstrap, 'actions')
  if (!endpoint) throw new Error(t('engine.sessions.center.001'))

  const body = new URLSearchParams({ user_doaction: 'getActiveSessions' })
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
    throw new Error(t('engine.sessions.center.002'))
  }

  const text = await response.text()
  let payload: unknown = null
  try {
    payload = text ? JSON.parse(text) : null
  } catch {
    throw new Error(t('engine.sessions.center.003'))
  }
  if (!response.ok) {
    throw new Error(responseMessage(payload, t('engine.sessions.center.004', [response.status])))
  }
  return payload as ActiveSessionsResponse
}

export async function refreshUserSessions(options: { silent?: boolean } = {}): Promise<void> {
  if (!bootstrapBoolean(appBootstrap, 'isLogged')) return
  if (refreshPromise) return refreshPromise

  const execute = async (): Promise<void> => {
    if (!options.silent) userSessions.loading = true
    userSessions.error = ''
    try {
      const payload = await requestActiveSessions()
      userSessions.items = normalizeList(payload.sessions)
      userSessions.activeCount = integer(payload.activeCount)
      userSessions.initialized = true
      userSessions.lastUpdatedAt = Date.now()
    } catch (error) {
      userSessions.error = error instanceof Error ? error.message : t('engine.sessions.center.005')
    } finally {
      userSessions.loading = false
      refreshPromise = null
    }
  }

  refreshPromise = execute()
  return refreshPromise
}

export async function revokeUserSession(sessionUuid: string): Promise<string> {
  const normalizedSessionUuid = sessionUuid.trim()
  if (!normalizedSessionUuid) throw new Error(t('engine.sessions.center.006'))
  if (userSessions.revokingSessionUuid) throw new Error(t('engine.sessions.center.007'))

  const target = userSessions.items.find((session) => session.sessionUuid === normalizedSessionUuid)
  if (target?.current) throw new Error(t('engine.sessions.center.008'))

  const endpoint = bootstrapEndpoint(appBootstrap, 'actions')
  if (!endpoint) throw new Error(t('engine.sessions.center.001'))
  const body = new URLSearchParams({
    user_doaction: 'revokeActiveSession',
    sessionUuid: normalizedSessionUuid,
  })
  const csrfToken = bootstrapString(appBootstrap, 'csrfToken')
  const login = bootstrapString(appBootstrap, 'login')
  if (csrfToken) body.set('csrf_token', csrfToken)
  if (login) body.set('user', login)

  userSessions.revokingSessionUuid = normalizedSessionUuid
  try {
    let response: Response
    try {
      response = await fetch(endpoint, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8' },
        body,
      })
    } catch {
      throw new Error(t('engine.sessions.center.002'))
    }

    const text = await response.text()
    let payload: unknown = null
    try {
      payload = text ? JSON.parse(text) : null
    } catch {
      throw new Error(t('engine.sessions.center.003'))
    }
    if (!response.ok) {
      throw new Error(responseMessage(payload, t('engine.sessions.center.004', [response.status])))
    }

    const result = payload && typeof payload === 'object' && !Array.isArray(payload)
      ? payload as Record<string, unknown>
      : {}
    userSessions.items = userSessions.items.filter((session) => session.sessionUuid !== normalizedSessionUuid)
    userSessions.activeCount = result.activeCount === undefined
      ? userSessions.items.length
      : integer(result.activeCount)
    userSessions.lastUpdatedAt = Date.now()
    return responseMessage(payload, t('engine.sessions.center.009'))
  } finally {
    userSessions.revokingSessionUuid = ''
  }
}

export function formatSessionTime(timestamp: number): string {
  if (timestamp <= 0) return '—'
  return new Intl.DateTimeFormat('ru-RU', { dateStyle: 'medium', timeStyle: 'short' })
    .format(new Date(timestamp * 1000))
}

export function sessionDeviceIcon(session: ActiveUserSession): string {
  const os = session.operatingSystem.toLocaleLowerCase('ru')
  if (os.includes('android') || os.includes('ios')) return 'fa-solid fa-mobile-screen-button'
  return 'fa-solid fa-laptop'
}
