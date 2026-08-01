import { createRouter, createWebHashHistory, type RouteLocationRaw, type RouteRecordRaw } from 'vue-router'
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
  if (viewModules.has(match[1])) throw new Error(`Duplicate engine client view: ${match[1]}`)
  viewModules.set(match[1], loader)
}

const EngineUnavailableView = defineComponent({
  name: 'EngineUnavailableView',
  setup: () => () => h('main', { class: 'system-message system-message--error' }, [
    h('strong', 'Frontend manifest is unavailable'),
    h('p', 'The selected theme requires a valid engine bootstrap payload.'),
  ]),
})

function routeRecord(definition: FrontendRouteDefinition): RouteRecordRaw {
  const base = {
    path: definition.path,
    name: definition.name,
    meta: { title: definition.title ?? '' },
  }
  if (definition.redirect) return { ...base, redirect: definition.redirect }

  const component = definition.view ? viewModules.get(definition.view) : undefined
  if (!component) throw new Error(`Engine client view is not available: ${definition.view}`)
  return { ...base, component, props: definition.props as RouteRecordRaw['props'] }
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
if (engineRoutes.length > 0 && !routes.some((route) => route.name === 'news')) {
  const component = viewModules.get('NewsView')
  if (component) {
    console.warn('[FoxesCraft] News route is missing from bootstrap; applying client fallback.')
    routes.push({
      path: '/news/:id',
      name: 'news',
      component,
      props: true,
      meta: { title: 'Новость' },
    })
  }
}

export const router = createRouter({
  history: createWebHashHistory(),
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
  return { name: alias.route, params, query }
}

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

  const target = resolveLegacy(alias, value, legacyQuery)
  const href = router.resolve(target).href
  window.history.replaceState(null, '', `${window.location.pathname}${window.location.search}${href}`)
}

normalizeLegacyHash()

router.afterEach((route) => {
  const title = typeof route.meta.title === 'string' ? route.meta.title : ''
  const siteTitle = appBootstrap.site.title || 'FoxesCraft'
  document.title = !title || title === siteTitle ? siteTitle : `${title} — ${siteTitle}`
})
