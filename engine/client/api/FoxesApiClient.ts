import type { FoxesCraftBootstrap } from '@/domain/bootstrap'
import { bootstrapEndpoint, bootstrapString } from '@/domain/bootstrap'
import { notifyPayload, showToast } from '@/notifications/toasts'

export class FoxesApiError extends Error {
  constructor(
    message: string,
    readonly status: number,
    readonly responseBody: string,
  ) {
    super(message)
    this.name = 'FoxesApiError'
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
      throw new FoxesApiError('Network request failed', 0, '')
    }
    const text = await response.text()
    if (!response.ok) {
      let payload: unknown
      try { payload = JSON.parse(text) } catch { payload = null }
      if (!notifyPayload(payload)) showToast(`Ошибка сервера: ${response.status}`, 'error')
      throw new FoxesApiError(`Request failed with status ${response.status}`, response.status, text)
    }
    return text
  }

  private actionEndpoint(): string {
    const endpoint = bootstrapEndpoint(this.bootstrap, 'actions')
    if (!endpoint) throw new FoxesApiError('Engine action endpoint is unavailable', 0, '')
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
      throw new FoxesApiError('Network request failed', 0, '')
    }
    const text = await response.text()
    let payload: unknown
    try { payload = JSON.parse(text) }
    catch {
      showToast('Сервер вернул некорректный ответ.', 'error')
      throw new FoxesApiError('Server returned invalid JSON', response.status, text)
    }
    if (!response.ok) {
      if (!notifyPayload(payload)) showToast(`Ошибка запроса: ${response.status}`, 'error')
      throw new FoxesApiError(`Request failed with status ${response.status}`, response.status, text)
    }
    notifyPayload(payload)
    return payload as T
  }
}
