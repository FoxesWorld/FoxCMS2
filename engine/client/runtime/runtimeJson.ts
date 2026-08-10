export class RuntimeJsonHttpError extends Error {
  constructor(public readonly status: number) {
    super(`Runtime JSON request failed with HTTP ${status}.`)
    this.name = 'RuntimeJsonHttpError'
  }
}

/** Shared same-origin transport for mutable runtime JSON resources. */
export async function loadRuntimeJson<T>(url: string): Promise<T> {
  const response = await fetch(url, {
    credentials: 'same-origin',
    cache: 'no-store',
    headers: { Accept: 'application/json' },
  })
  if (!response.ok) throw new RuntimeJsonHttpError(response.status)
  return response.json() as Promise<T>
}
