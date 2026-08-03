import { t } from '@/i18n'
import { appBootstrap } from '@/app/context'
import { bootstrapEndpoint } from '@/domain/bootstrap'
import type { EmoticonCatalog } from '@/emoticons/types'

export interface StaticPageDefinition {
  id: string
  title: string
  html: string
}
export interface BadgeDefinition {
  id: string
  databaseId: number
  badgeName: string
  title: string
  description: string
  image: string | null
  html: string
  pageConfigured: boolean
}

let staticPagesPromise: Promise<Record<string, StaticPageDefinition>> | null = null
let badgesPromise: Promise<readonly BadgeDefinition[]> | null = null
let emoticonsPromise: Promise<EmoticonCatalog> | null = null
const badgePagePromises = new Map<string, Promise<BadgeDefinition>>()

function registryUrl(registry: string): string {
  const endpoint = bootstrapEndpoint(appBootstrap, 'content')
  if (!endpoint) throw new Error(t('engine.content.contentdata.001'))
  const url = new URL(endpoint, window.location.origin)
  url.searchParams.set('registry', registry)
  return url.toString()
}

function loadRegistry<T>(registry: string): Promise<T> {
  return fetch(registryUrl(registry), {
    credentials: 'same-origin',
    cache: 'no-store',
    headers: { Accept: 'application/json' },
  }).then(async (response) => {
    if (!response.ok) {
      const payload = await response.json().catch(() => null) as { requestId?: unknown; error?: unknown } | null
      const requestId = typeof payload?.requestId === 'string' ? `, request ${payload.requestId}` : ''
      const code = typeof payload?.error === 'string' ? `, ${payload.error}` : ''
      throw new Error(t('engine.content.contentdata.002', [response.status, code, requestId]))
    }
    return response.json() as Promise<T>
  })
}

export function loadStaticPages(): Promise<Record<string, StaticPageDefinition>> {
  return staticPagesPromise ??= loadRegistry<Record<string, StaticPageDefinition>>('project-pages')
}

export function loadBadges(): Promise<readonly BadgeDefinition[]> {
  return badgesPromise ??= loadRegistry<readonly BadgeDefinition[]>('badges')
}

export function loadEmoticons(): Promise<EmoticonCatalog> {
  return emoticonsPromise ??= loadRegistry<EmoticonCatalog>('emoticons').catch((error: unknown) => {
    emoticonsPromise = null
    throw error
  })
}


export function loadBadge(slug: string): Promise<BadgeDefinition> {
  const normalized = slug.trim().toLowerCase()
  if (!/^[a-z0-9][a-z0-9-]{0,79}$/.test(normalized)) {
    return Promise.reject(new Error(t('engine.content.contentdata.003')))
  }
  const existing = badgePagePromises.get(normalized)
  if (existing) return existing
  const endpoint = registryUrl('badge')
  const url = new URL(endpoint)
  url.searchParams.set('id', normalized)
  const request = fetch(url.toString(), {
    credentials: 'same-origin',
    cache: 'no-store',
    headers: { Accept: 'application/json' },
  }).then(async (response) => {
    if (!response.ok) {
      const payload = await response.json().catch(() => null) as { requestId?: unknown; error?: unknown } | null
      const requestId = typeof payload?.requestId === 'string' ? `, request ${payload.requestId}` : ''
      const code = typeof payload?.error === 'string' ? `, ${payload.error}` : ''
      throw new Error(t('engine.content.contentdata.004', [response.status, code, requestId]))
    }
    return response.json() as Promise<BadgeDefinition>
  }).catch((error: unknown) => {
    badgePagePromises.delete(normalized)
    throw error
  })
  badgePagePromises.set(normalized, request)
  return request
}

export function invalidateContentRegistry(registry?: 'project-pages' | 'badges' | 'emoticons'): void {
  if (!registry || registry === 'project-pages') staticPagesPromise = null
  if (!registry || registry === 'emoticons') emoticonsPromise = null
  if (!registry || registry === 'badges') {
    badgesPromise = null
    badgePagePromises.clear()
  }
}
