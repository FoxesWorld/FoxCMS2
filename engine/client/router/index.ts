import { t } from '@/i18n'
import { createRouter, createWebHistory, type RouteLocationRaw, type RouteRecordRaw } from 'vue-router'
import { defineComponent, h } from 'vue'
import { appBootstrap } from '@/app/context'
import type { FrontendRouteDefinition, LegacyRouteDefinition } from '@/domain/bootstrap'

const discoveredViewModules = {
  ...import.meta.glob('../views/*View.vue'),
  ...import.meta.glob('../../classes/modules/*/client/views/*View.vue'),
}

const viewModules = new Map<string, () => Promise<unknown>>()
for (const [path, loader] of Object.entries(discoveredViewModules)) {
  const match = path.match(/\/([^/]+View)\.vue$/)
  if (!match) continue
  if (viewModules.has(match[1])) throw new Error(t('engine.router.index.001', [match[1]]))
  viewModules.set(match[1], loader)
}

const EngineUnavailableView = defineComponent({
  name: 'EngineUnavailableView',
  setup: () => () => h('main', { class: 'system-message system-message--error' }, [
    h('strong', t('engine.router.index.007')),
    h('p', t('engine.router.index.008')),
  ]),
})

/**
 * Migrates URLs produced by the previous Vue hash-history router:
 *
 *   /#/news/15                -> /news/15
 *   /#/badges/developer       -> /badges/developer
 *   /#/server/Azurine         -> /server/Azurine
 *
 * This runs before Vue Router is created so its initial navigation observes
 * the canonical HTML5-history location immediately. Existing outer query
 * parameters are preserved unless the hash route already defines the same key.
 */
function migrateHashHistoryUrl(): void {
  const hash = window.location.hash
  if (!hash.startsWith('#/')) return

  try {
    const destination = new URL(hash.slice(1), window.location.origin)
    const outerQuery = new URLSearchParams(window.location.search)

    for (const [key, value] of outerQuery.entries()) {
      if (!destination.searchParams.has(key)) {
        destination.searchParams.append(key, value)
      }
    }

    window.history.replaceState(
      window.history.state,
      '',
      `${destination.pathname}${destination.search}${destination.hash}`,
    )
  } catch (error) {
    console.warn('[FoxesCraft] Failed to migrate hash-history URL.', error)
  }
}

migrateHashHistoryUrl()

function routeRecord(definition: FrontendRouteDefinition): RouteRecordRaw {
  const base = {
    path: definition.path,
    name: definition.name,
    meta: { title: definition.title ?? '', layout: definition.layout ?? 'standard' },
  }

  if (definition.redirect) return { ...base, redirect: definition.redirect }

  const component = definition.view ? viewModules.get(definition.view) : undefined
  if (!component) throw new Error(t('engine.router.index.002', [definition.view]))

  return {
    ...base,
    component,
    props: definition.props as RouteRecordRaw['props'],
  }
}

const engineRoutes = appBootstrap.frontend.routes.map(routeRecord)
const routes: RouteRecordRaw[] = engineRoutes.length > 0
  ? [...engineRoutes]
  : [{ path: '/:pathMatch(.*)*', name: 'engine-unavailable', component: EngineUnavailableView }]

/*
 * The news feed is rendered by the theme on the home page, while its detail
 * view belongs to the News engine module. A stale or partially cached server
 * bootstrap can therefore expose the feed before it exposes the corresponding
 * named route. Keep the client usable in that state and let the next complete
 * bootstrap restore the manifest-owned definition.
 */
if (engineRoutes.length > 0 && !routes.some((route) => route.name === 'news-list')) {
  const component = viewModules.get('NewsListView')
  if (component) {
    console.warn('[FoxesCraft] News archive route is missing from bootstrap; applying client fallback.')
    routes.push({
      path: '/news',
      name: 'news-list',
      component,
      meta: { title: t('engine.router.index.004'), layout: 'wide' },
    })
  }
}

if (engineRoutes.length > 0 && !routes.some((route) => route.name === 'news')) {
  const component = viewModules.get('NewsView')
  if (component) {
    console.warn('[FoxesCraft] News route is missing from bootstrap; applying client fallback.')
    routes.push({
      path: '/news/:id',
      name: 'news',
      component,
      props: true,
      meta: { title: t('engine.router.index.003'), layout: 'wide' },
    })
  }
}

if (engineRoutes.length > 0 && !routes.some((route) => route.name === 'devices')) {
  const component = viewModules.get('DevicesView')
  if (component) {
    console.warn('[FoxesCraft] Devices route is missing from bootstrap; applying client fallback.')
    routes.push({
      path: '/devices',
      name: 'devices',
      component,
      meta: { title: t('engine.router.index.005'), layout: 'wide' },
    })
  }
}

if (engineRoutes.length > 0 && !routes.some((route) => route.name === 'achievements')) {
  const component = viewModules.get('AchievementsView')
  if (component) {
    console.warn('[FoxesCraft] Achievements route is missing from bootstrap; applying client fallback.')
    routes.push({
      path: '/achievements/:value?',
      name: 'achievements',
      component,
      props: true,
      meta: { title: t('engine.router.index.006'), layout: 'wide' },
    })
  }
}

export const router = createRouter({
  history: createWebHistory('/'),
  routes,
  scrollBehavior: () => ({ top: 0, behavior: 'smooth' }),
})

function resolveLegacy(alias: LegacyRouteDefinition, rawValue: string, legacyQuery: string): RouteLocationRaw {
  const params: Record<string, string> = {}
  if (alias.param) params[alias.param] = rawValue

  const sourceQuery = new URLSearchParams(legacyQuery)
  const query: Record<string, string> = {}

  for (const [source, target] of Object.entries(alias.query ?? {})) {
    const value = sourceQuery.get(source)
    if (value) query[target] = value
  }

  return {
    name: alias.route,
    params,
    query,
  }
}

/**
 * Migrates the pre-Vue FoxCMS hash format, for example:
 *
 *   #page/rules             -> /rules
 *   #page/info              -> /about
 *   #server/Industrial      -> /server/Industrial
 *   #badge/developer        -> /badges/developer
 *
 * Hashes beginning with #/ are intentionally ignored here because they are
 * handled by migrateHashHistoryUrl() before router creation.
 */
function normalizeLegacyHash(): void {
  const raw = window.location.hash.replace(/^#/, '')
  if (!raw || raw.startsWith('/')) return

  const [kind, ...parts] = raw.split('/')
  const joined = parts.join('/')
  const [value, legacyQuery = ''] = joined.split('?')

  const alias = appBootstrap.frontend.legacy.find((entry) =>
    entry.kind === kind && (entry.value === undefined || entry.value === value),
  )
  if (!alias) return

  try {
    const target = resolveLegacy(alias, value, legacyQuery)
    const href = router.resolve(target).href
    const destination = new URL(href, window.location.origin)
    const outerQuery = new URLSearchParams(window.location.search)

    for (const [key, queryValue] of outerQuery.entries()) {
      if (!destination.searchParams.has(key)) {
        destination.searchParams.append(key, queryValue)
      }
    }

    window.history.replaceState(
      window.history.state,
      '',
      `${destination.pathname}${destination.search}${destination.hash}`,
    )
  } catch (error) {
    console.warn('[FoxesCraft] Failed to migrate legacy URL.', error)
  }
}

normalizeLegacyHash()

router.afterEach((route) => {
  const title = typeof route.meta.title === 'string' ? route.meta.title : ''
  const siteTitle = appBootstrap.site.title || 'FoxesCraft'
  const isHome = route.name === 'home' || route.path === '/'

  if (isHome || !title || title === siteTitle) {
    document.title = appBootstrap.site.homeTitle || siteTitle
    return
  }

  const template = appBootstrap.site.titleTemplate || '%page% — %site%'
  document.title = template
    .replaceAll('%page%', title)
    .replaceAll('%site%', siteTitle)
})
