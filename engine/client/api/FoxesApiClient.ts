import type { FoxesCraftBootstrap } from '@/domain/bootstrap'
import { bootstrapEndpoint, bootstrapString } from '@/domain/bootstrap'
import { notifyPayload, showToast } from '@/notifications/toasts'

export interface FoxesApiErrorPayload {
  message?: unknown
  type?: unknown
  code?: unknown
  field?: unknown
  requestId?: unknown
  correlationId?: unknown
  error?: unknown
  [key: string]: unknown
}

export interface FoxesApiFailureFeedback {
  type: 'error' | 'warning' | 'warn'
  message: string
  code?: string
  field?: string
  requestId?: string
  correlationId?: string
}

function payloadString(payload: FoxesApiErrorPayload | null, key: keyof FoxesApiErrorPayload): string {
  const value = payload?.[key]
  return typeof value === 'string' ? value.trim() : ''
}

function errorMessage(payload: unknown, fallback: string): string {
  if (payload && typeof payload === 'object' && !Array.isArray(payload)) {
    const message = (payload as FoxesApiErrorPayload).message
    if (typeof message === 'string' && message.trim() !== '') return message.trim()
  }
  return fallback
}

function feedbackType(payload: FoxesApiErrorPayload | null): FoxesApiFailureFeedback['type'] {
  const type = payloadString(payload, 'type').toLowerCase()
  return type === 'warning' || type === 'warn' ? type : 'error'
}

export function foxesApiFailureFeedback(
  error: unknown,
  fallback: string,
): FoxesApiFailureFeedback {
  if (!(error instanceof FoxesApiError)) {
    return { type: 'error', message: fallback }
  }

  const feedback: FoxesApiFailureFeedback = {
    type: feedbackType(error.payload),
    message: error.message.trim() || fallback,
  }
  const code = payloadString(error.payload, 'code')
  const field = payloadString(error.payload, 'field')
  const requestId = payloadString(error.payload, 'requestId') || error.requestId
  const correlationId = payloadString(error.payload, 'correlationId') || error.correlationId
  if (code) feedback.code = code
  if (field) feedback.field = field
  if (requestId) feedback.requestId = requestId
  if (correlationId) feedback.correlationId = correlationId
  return feedback
}

export class FoxesApiError extends Error {
  readonly requestId: string
  readonly correlationId: string

  constructor(
    message: string,
    readonly status: number,
    readonly responseBody: string,
    readonly payload: FoxesApiErrorPayload | null = null,
  ) {
    super(message)
    this.name = 'FoxesApiError'
    this.requestId = payloadString(payload, 'requestId')
    this.correlationId = payloadString(payload, 'correlationId')
  }
}

export class FoxesApiClient {
  constructor(private readonly bootstrap: FoxesCraftBootstrap) {}

  async post<T>(payload: Record<string, string | number | boolean | null | undefined>): Promise<T> {
    const body = new URLSearchParams()
    for (const [key, value] of Object.entries(payload)) {
      if (value !== undefined && value !== null) body.set(key, String(value))
    }
    this.appendSessionContext(body)
    return this.request<T>(body, { 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8' })
  }

  async postFormData<T>(body: FormData): Promise<T> {
    this.appendSessionContext(body)
    return this.request<T>(body)
  }

  async postText(payload: Record<string, string | number | boolean | null | undefined>): Promise<string> {
    const body = new URLSearchParams()
    for (const [key, value] of Object.entries(payload)) {
      if (value !== undefined && value !== null) body.set(key, String(value))
    }
    this.appendSessionContext(body)
    let response: Response
    try {
      response = await fetch(this.actionEndpoint(), {
        method: 'POST', credentials: 'same-origin',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8' }, body,
      })
    } catch {
      showToast('Не удалось связаться с сервером.', 'error')
      throw new FoxesApiError('Не удалось связаться с сервером.', 0, '')
    }
    const text = await response.text()
    if (!response.ok) {
      let payload: FoxesApiErrorPayload | null
      try { payload = JSON.parse(text) as FoxesApiErrorPayload } catch { payload = null }
      const message = errorMessage(payload, `Ошибка сервера: HTTP ${response.status}.`)
      if (!notifyPayload(payload)) showToast(message, 'error')
      throw new FoxesApiError(message, response.status, text, payload)
    }
    return text
  }

  private actionEndpoint(): string {
    const endpoint = bootstrapEndpoint(this.bootstrap, 'actions')
    if (!endpoint) throw new FoxesApiError('Endpoint административных операций недоступен.', 0, '')
    return endpoint
  }

  private appendSessionContext(body: URLSearchParams | FormData): void {
    const csrfToken = bootstrapString(this.bootstrap, 'csrfToken')
    const login = bootstrapString(this.bootstrap, 'login')
    if (csrfToken && !body.has('csrf_token')) body.set('csrf_token', csrfToken)
    if (login && !body.has('user')) body.set('user', login)
  }

  private async request<T>(body: BodyInit, headers?: HeadersInit): Promise<T> {
    let response: Response
    try {
      response = await fetch(this.actionEndpoint(), { method: 'POST', credentials: 'same-origin', headers, body })
    } catch {
      showToast('Не удалось связаться с сервером.', 'error')
      throw new FoxesApiError('Не удалось связаться с сервером.', 0, '')
    }
    const text = await response.text()
    let payload: unknown
    try { payload = JSON.parse(text) }
    catch {
      showToast('Сервер вернул некорректный ответ.', 'error')
      throw new FoxesApiError(`Сервер вернул некорректный JSON (HTTP ${response.status}).`, response.status, text)
    }
    if (!response.ok) {
      const errorPayload = payload && typeof payload === 'object' && !Array.isArray(payload)
        ? payload as FoxesApiErrorPayload
        : null
      const message = errorMessage(errorPayload, `Ошибка запроса: HTTP ${response.status}.`)
      if (!notifyPayload(errorPayload)) showToast(message, 'error')
      throw new FoxesApiError(message, response.status, text, errorPayload)
    }
    notifyPayload(payload)
    return payload as T
  }
}
