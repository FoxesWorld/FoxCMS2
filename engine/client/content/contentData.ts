import { appBootstrap } from '@/app/context'
import { bootstrapEndpoint } from '@/domain/bootstrap'

export interface StaticPageSection { title: string; paragraphs?: string[]; items?: string[] }
export interface StaticPageDefinition { eyebrow: string; title: string; summary: string; updated?: string; sections: StaticPageSection[] }
export interface BadgeDefinition { id: string; title: string; description: string; image: string | null; paragraphs: string[] }

let staticPagesPromise: Promise<Record<string, StaticPageDefinition>> | null = null
let badgesPromise: Promise<readonly BadgeDefinition[]> | null = null

function registryUrl(registry: string): string {
  const endpoint = bootstrapEndpoint(appBootstrap, 'content')
  if (!endpoint) throw new Error('Engine content endpoint is unavailable')
  const url = new URL(endpoint, window.location.origin)
  url.searchParams.set('registry', registry)
  return url.toString()
}

function loadRegistry<T>(registry: string): Promise<T> {
  return fetch(registryUrl(registry), { credentials: 'same-origin' }).then((response) => {
    if (!response.ok) throw new Error(`Content registry request failed: ${response.status}`)
    return response.json() as Promise<T>
  })
}

export function loadStaticPages(): Promise<Record<string, StaticPageDefinition>> {
  return staticPagesPromise ??= loadRegistry<Record<string, StaticPageDefinition>>('static-pages')
}

export function loadBadges(): Promise<readonly BadgeDefinition[]> {
  return badgesPromise ??= loadRegistry<readonly BadgeDefinition[]>('badges')
}
